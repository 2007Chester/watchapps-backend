<?php

namespace App\Http\Controllers;

use App\Models\Watchface;
use App\Models\WatchfaceView;
use App\Models\WatchfaceClick;
use App\Models\WatchfaceSale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WatchfaceStatsController extends Controller
{
    /**
     * Логируем просмотр страницы циферблата.
     * Публичный эндпоинт, дергается с фронта при открытии страницы watchface.
     */
    public function logView(Request $request, $id)
    {
        // Убедимся, что циферблат существует
        $watchface = Watchface::findOrFail($id);

        WatchfaceView::create([
            'watchface_id' => $watchface->id,
            'user_id'      => $request->user()?->id,
            'ip'           => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 2000),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Логируем клик по кнопке "Купить".
     */
    public function logClick(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);

        WatchfaceClick::create([
            'watchface_id' => $watchface->id,
            'user_id'      => $request->user()?->id,
            'ip'           => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Статистика для Dev Console.
     * Только для разработчика, владельца циферблата (или админа, если так сделаем позже).
     *
     * GET /api/dev/watchfaces/{id}/stats?period=30d
     */
    public function stats(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);

        // TODO: здесь можно добавить проверку, что текущий разработчик владеет этим watchface

        $period = $request->query('period', '30d');

        $days = match ($period) {
            '24h' => 1,
            '7d'  => 7,
            '30d' => 30,
            '90d' => 90,
            'all' => 3650, // 10 лет — фактически "всё время"
            default => 30,
        };

        $from = Carbon::now()->subDays($days)->startOfDay();

        // Тотал
        $viewsCount  = WatchfaceView::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->count();

        $clicksCount = WatchfaceClick::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->count();

        $salesCount  = WatchfaceSale::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->count();

        $conversion = $clicksCount > 0
            ? $salesCount / $clicksCount
            : 0;

        // Графики по дням
        $viewsByDay = WatchfaceView::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $clicksByDay = WatchfaceClick::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        $salesByDay = WatchfaceSale::where('watchface_id', $watchface->id)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt')
            ->groupBy('day')
            ->pluck('cnt', 'day');

        // Строим массив по каждому дню периода
        $chart = [];
        $cursor = $from->clone();
        $today = Carbon::now()->startOfDay();

        while ($cursor <= $today) {
            $day = $cursor->toDateString();

            $chart[$day] = [
                'views'  => (int) ($viewsByDay[$day] ?? 0),
                'clicks' => (int) ($clicksByDay[$day] ?? 0),
                'sales'  => (int) ($salesByDay[$day] ?? 0),
            ];

            $cursor->addDay();
        }

        return response()->json([
            'views'      => $viewsCount,
            'clicks'     => $clicksCount,
            'sales'      => $salesCount,
            'conversion' => $conversion,
            'chart'      => $chart,
        ]);
    }
}
