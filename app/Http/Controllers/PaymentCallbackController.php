<?php

namespace App\Http\Controllers;

use App\Models\TinkoffCallback;
use App\Models\WatchfaceSale;
use App\Models\Watchface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    /**
     * Приём callback от Тинькофф по протоколу HTTP.
     * URL: /api/payments/tinkoff/callback
     */
    public function tinkoff(Request $request)
    {
        $payload = $request->all();

        $token = $payload['Token'] ?? $payload['token'] ?? null;
        $expectedToken = $this->generateTinkoffToken($payload);
        $tokenValid = $expectedToken && $token && hash_equals($expectedToken, $token);

        $callback = TinkoffCallback::create([
            'order_id' => $payload['OrderId'] ?? $payload['orderId'] ?? null,
            'payment_id' => $payload['PaymentId'] ?? $payload['paymentId'] ?? null,
            'status' => $payload['Status'] ?? $payload['status'] ?? null,
            'success' => filter_var($payload['Success'] ?? $payload['success'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'error_code' => $payload['ErrorCode'] ?? $payload['errorCode'] ?? null,
            'message' => $payload['Message'] ?? $payload['message'] ?? null,
            'amount' => isset($payload['Amount']) ? (int) $payload['Amount'] : (isset($payload['amount']) ? (int) $payload['amount'] : null),
            'token' => $token,
            'token_valid' => (bool) $tokenValid,
            'payload' => $payload,
            'headers' => $request->headers->all(),
            'ip' => $request->ip(),
        ]);

        if (!$tokenValid) {
            Log::warning('Tinkoff callback: invalid token', [
                'order_id' => $callback->order_id,
                'payment_id' => $callback->payment_id,
            ]);
        }

        $normalizedStatus = strtoupper((string) ($callback->status ?? ''));
        $refundStatuses = ['CANCELED', 'CANCELLED', 'REVERSED', 'PARTIAL_REVERSED', 'REFUNDED'];
        $successStatuses = ['CONFIRMED', 'AUTHORIZED', 'AUTHORIZED_AND_CONFIRM'];

        // Если подпись валидна — фиксируем покупку или отмену
        if ($tokenValid) {
            if ($callback->success || in_array($normalizedStatus, $successStatuses, true)) {
                $this->storeSaleFromOrderId($callback->order_id, $callback->amount);
            } elseif (in_array($normalizedStatus, $refundStatuses, true)) {
                $this->removeSaleFromOrderId($callback->order_id);
            }
        }

        return response()->json([
            'success' => $tokenValid,
            'token_valid' => $tokenValid,
        ]);
    }

    /**
     * Публичный статус по order_id (используется на страницах success/fail).
     */
    public function status(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
        ]);

        $orderId = $request->string('order_id');
        $callback = TinkoffCallback::where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();

        if (!$callback) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $callback->order_id,
                'payment_id' => $callback->payment_id,
                'status' => $callback->status,
                'tinkoff_success' => (bool) $callback->success,
                'error_code' => $callback->error_code,
                'message' => $callback->message,
                'amount' => $callback->amount,
            ],
        ]);
    }

    /**
     * Ручной возврат/отмена по order_id (использует PaymentId).
     */
    public function refund(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
            'amount' => 'nullable|integer|min:1', // в копейках
        ]);

        $orderId = $request->string('order_id');

        $record = TinkoffCallback::where('order_id', $orderId)
            ->whereNotNull('payment_id')
            ->orderByDesc('id')
            ->first();

        if (!$record || !$record->payment_id) {
            return response()->json([
                'success' => false,
                'message' => 'PaymentId не найден для этого order_id',
            ], 404);
        }

        $terminalKey = config('services.tinkoff.terminal_key');
        $password = config('services.tinkoff.password');

        if (!$terminalKey || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Tinkoff не настроен (TerminalKey/Password)',
            ], 500);
        }

        $amount = $request->integer('amount') ?: $record->amount;
        $payload = [
            'TerminalKey' => $terminalKey,
            'PaymentId' => $record->payment_id,
        ];

        if ($amount) {
            $payload['Amount'] = (int) $amount;
        }

        $payload['Token'] = $this->generateSimpleToken($payload, $password);

        try {
            $url = $amount ? 'https://securepay.tinkoff.ru/v2/Refund' : 'https://securepay.tinkoff.ru/v2/Cancel';
            $resp = \Illuminate\Support\Facades\Http::timeout(10)->post($url, $payload);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка запроса к Тинькофф: ' . $e->getMessage(),
            ], 500);
        }

        $data = $resp->json();
        if (!$resp->successful() || empty($data['Success'])) {
            return response()->json([
                'success' => false,
                'message' => $data['Message'] ?? 'Тинькофф вернул ошибку',
                'details' => $data['Details'] ?? null,
                'error_code' => $data['ErrorCode'] ?? null,
            ], 422);
        }

        // Удаляем покупку у пользователя
        $this->removeSaleFromOrderId($orderId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Ручное подтверждение покупки по OrderId после возврата с SuccessURL.
     * Используется, если callback от Тинькофф не дошёл.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'order_id' => 'required|string',
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Auth required'], 401);
        }

        $orderId = $request->string('order_id');
        $parsed = $this->parseOrderId($orderId);
        if (!$parsed) {
            return response()->json(['success' => false, 'message' => 'Invalid OrderId'], 422);
        }

        if ($parsed['user_id'] !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Order does not belong to this user'], 403);
        }

        WatchfaceSale::firstOrCreate(
            [
                'watchface_id' => $parsed['watchface_id'],
                'user_id' => $user->id,
            ],
            [
                'price' => $this->resolveAmountRub($parsed['watchface_id'], $orderId, $parsed['amount']),
                'currency' => 'RUB',
            ]
        );

        return response()->json(['success' => true]);
    }

    /**
     * OrderId имеет формат: wf-{watchface_id}-user-{user_id}-{timestamp}
     */
    private function storeSaleFromOrderId(?string $orderId, ?int $amount): void
    {
        $parsed = $this->parseOrderId($orderId, $amount);
        if (!$parsed) {
            return;
        }

        WatchfaceSale::updateOrCreate(
            [
                'watchface_id' => $parsed['watchface_id'],
                'user_id' => $parsed['user_id'],
            ],
            [
                'price' => $this->resolveAmountRub($parsed['watchface_id'], $orderId, $parsed['amount']),
                'currency' => 'RUB',
            ]
        );
    }

    /**
     * Удаление покупки при отмене/возврате.
     */
    private function removeSaleFromOrderId(?string $orderId): void
    {
        $parsed = $this->parseOrderId($orderId, null);
        if (!$parsed) {
            return;
        }

        WatchfaceSale::where('watchface_id', $parsed['watchface_id'])
            ->where('user_id', $parsed['user_id'])
            ->delete();
    }

    /**
     * Разбор OrderId wf-{watchface_id}-user-{user_id}-{timestamp}
     */
    private function parseOrderId(?string $orderId, ?int $amountCents = null): ?array
    {
        if (!$orderId) {
            return null;
        }

        $matches = [];
        if (!preg_match('/^wf-(\d+)-user-(\d+)-(\d+)$/', $orderId, $matches)) {
            return null;
        }

        $watchfaceId = (int) $matches[1];
        $userId = (int) $matches[2];

        if ($watchfaceId <= 0 || $userId <= 0) {
            return null;
        }

        return [
            'watchface_id' => $watchfaceId,
            'user_id' => $userId,
            'amount' => $amountCents ? $amountCents / 100 : null,
        ];
    }

    /**
     * Генерация подписи Тинькофф согласно документации:
     * добавляем Password, сортируем по ключу, конкатенируем значения и хэшируем SHA-256.
     */
    private function generateTinkoffToken(array $payload): ?string
    {
        $password = config('services.tinkoff.password') ?? env('TINKOFF_PASSWORD');

        if (!$password) {
            return null;
        }

        $data = [];
        foreach ($payload as $key => $value) {
            // Токен из запроса не включаем в расчёт
            if (strcasecmp($key, 'Token') === 0) {
                continue;
            }
            $data[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        $data['Password'] = $password;

        ksort($data, SORT_STRING);

        return hash('sha256', implode('', $data));
    }

    /**
     * Упрощённый токен для Refund/Cancel.
     */
    private function generateSimpleToken(array $params, string $password): string
    {
        $data = $params;
        unset($data['Token']);
        $data['Password'] = $password;
        ksort($data, SORT_STRING);
        foreach ($data as $k => $v) {
            $data[$k] = (string) $v;
        }
        return hash('sha256', implode('', $data));
    }

    /**
     * Определить сумму в рублях: приоритет — amount из callback, затем amount из сохранённого INIT, затем цена watchface.
     */
    private function resolveAmountRub(int $watchfaceId, ?string $orderId, ?float $amountRubFromParse): float
    {
        // Если уже есть рублевая сумма из parse (Amount/100)
        if ($amountRubFromParse !== null) {
            return (float) $amountRubFromParse;
        }

        // Пробуем взять amount (в копейках) из последнего TinkoffCallback с этим order_id
        if ($orderId) {
            $cb = TinkoffCallback::where('order_id', $orderId)->orderByDesc('id')->first();
            if ($cb && $cb->amount) {
                return $cb->amount / 100;
            }
        }

        // Фолбэк — цена watchface
        $wf = Watchface::find($watchfaceId);
        return $wf ? (float) $wf->price : 0.0;
    }
}

