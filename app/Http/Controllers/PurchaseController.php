<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Watchface;
use App\Models\WatchfaceSale;
use App\Models\WatchfaceDownload;
use App\Models\WatchfaceInstallation;
use App\Models\TinkoffCallback;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class PurchaseController extends Controller
{
    /**
     * Фиксируем покупку циферблата.
     */
    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'watchface_id' => 'required|exists:watchfaces,id',
            'device_sdk'   => 'nullable|integer|min:1|max:100', // SDK часов пользователя
        ]);

        $watchface = Watchface::findOrFail($validated['watchface_id']);

        // Бесплатные или уже купленные — сохраняем покупку локально сразу
        $alreadyPurchased = WatchfaceSale::where('watchface_id', $watchface->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($watchface->is_free || $alreadyPurchased) {
            $sale = WatchfaceSale::firstOrCreate(
                [
            'watchface_id' => $watchface->id,
            'user_id'      => $request->user()->id,
                ],
                [
                    'price'    => $watchface->price,
                    'currency' => "RUB",
                ]
            );

        $apkFile = $this->getCompatibleApkFile($watchface, $validated['device_sdk'] ?? null);

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'apk_url' => $apkFile ? $apkFile->url : null,
            'apk_version' => $apkFile ? $apkFile->version : null,
        ]);
        }

        // Платный, не куплен: создаем платеж в Тинькофф и возвращаем payment_url
        $paymentResponse = $this->initTinkoffPayment($watchface, $request->user());

        if (!$paymentResponse['success']) {
            return response()->json([
                'success' => false,
                'message' => $paymentResponse['message'] ?? 'Не удалось создать платеж',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'payment_url' => $paymentResponse['payment_url'],
            'order_id' => $paymentResponse['order_id'] ?? null,
        ]);
    }

    /**
     * Инициализация платежа в Тинькофф (возвращает PaymentURL).
     */
    private function initTinkoffPayment(Watchface $watchface, User $user): array
    {
        $terminalKey = config('services.tinkoff.terminal_key');
        $password = config('services.tinkoff.password');

        if (!$terminalKey || !$password) {
            return [
                'success' => false,
                'message' => 'Tinkoff не настроен (нет TerminalKey/Password)',
            ];
        }

        $orderId = sprintf('wf-%d-user-%d-%d', $watchface->id, $user->id, time());
        $amount = (int) round($watchface->price * 100); // копейки
        $itemName = mb_substr($watchface->title ?? 'Watchface purchase', 0, 128);
        $email = $user->email ?: 'support@watchapps.ru';

        $payload = [
            'TerminalKey' => $terminalKey,
            'Amount' => $amount,
            'OrderId' => $orderId,
            'Description' => $watchface->title ?? 'Watchface purchase',
            'NotificationURL' => 'https://watchapps.ru/api/payments/tinkoff/callback',
            'SuccessURL' => 'https://watchapps.ru/payments/success?order_id=' . urlencode($orderId),
            'FailURL' => 'https://watchapps.ru/payments/fail?order_id=' . urlencode($orderId),
            'Language' => 'ru',
            'Receipt' => [
                // По требованиям Тинькофф: Email или Phone обязателен
                'Email' => $email,
                'Taxation' => 'osn', // заменить при необходимости на ваш режим
                'Items' => [
                    [
                        'Name' => $itemName,
                        'Price' => $amount,
                        'Quantity' => 1,
                        'Amount' => $amount,
                        'Tax' => 'none', // без НДС; подставьте нужную ставку, если требуется
                        'PaymentMethod' => 'full_prepayment',
                        'PaymentObject' => 'service',
                    ],
                ],
            ],
        ];

        $payload['Token'] = $this->generateTinkoffToken($payload, $password);

        try {
            $response = Http::timeout(10)->post('https://securepay.tinkoff.ru/v2/Init', $payload);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Ошибка сети при обращении к Тинькофф: ' . $e->getMessage(),
            ];
        }

        $data = $response->json();

        if (!$response->successful() || empty($data['Success'])) {
            \Log::warning('Tinkoff Init failed', [
                'order_id' => $orderId,
                'response' => $data,
            ]);
            return [
                'success' => false,
                'message' => $data['Message'] ?? 'Не удалось создать платеж в Тинькофф',
            ];
        }

        // Сохраняем PaymentId для последующих возвратов/статусов
        if (!empty($data['PaymentId'])) {
            TinkoffCallback::create([
                'order_id' => $orderId,
                'payment_id' => $data['PaymentId'],
                'status' => 'INIT',
                'success' => true,
                'token' => $payload['Token'],
                'token_valid' => true,
                'amount' => $amount,
                'payload' => $data,
                'headers' => [],
                'ip' => request()->ip(),
            ]);
        }

        return [
            'success' => true,
            'payment_url' => $data['PaymentURL'] ?? null,
            'order_id' => $orderId,
        ];
    }

    /**
     * Генерация токена Тинькофф для Init.
     */
    private function generateTinkoffToken(array $params, string $password): string
    {
        $data = $params;
        unset($data['Token'], $data['Receipt']); // Receipt не включаем в подпись
        $data['Password'] = $password;
        ksort($data, SORT_STRING);
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = $value ? 'true' : 'false';
            } else {
                $data[$key] = (string) $value;
            }
        }
        return hash('sha256', implode('', $data));
    }
    
    /**
     * Получить совместимый APK файл для часов пользователя
     */
    private function getCompatibleApkFile(Watchface $watchface, ?int $deviceSdk)
    {
        $apkFiles = \App\Models\WatchfaceFile::where('watchface_id', $watchface->id)
            ->where('type', 'apk')
            ->with('upload')
            ->orderByDesc('version_code') // Сначала самые новые версии по versionCode
            ->orderBy('version', 'desc')
            ->orderByDesc('id')
            ->get();
        
        if ($apkFiles->isEmpty()) {
            return null;
        }
        
        // Если SDK не указан, возвращаем самую новую версию
        if ($deviceSdk === null) {
            return $apkFiles->first();
        }
        
        // Ищем самый подходящий APK файл:
        // 1. min_sdk <= device_sdk
        // 2. max_sdk >= device_sdk (если указан)
        // 3. Самая новая версия среди подходящих
        $compatibleApks = $apkFiles->filter(function ($apk) use ($deviceSdk) {
            $minSdk = $apk->min_sdk;
            $maxSdk = $apk->max_sdk;
            
            // Если min_sdk не указан, считаем совместимым
            if ($minSdk === null) {
                return true;
            }
            
            // Проверяем min_sdk
            if ($minSdk > $deviceSdk) {
                return false;
            }
            
            // Проверяем max_sdk (если указан)
            if ($maxSdk !== null && $maxSdk < $deviceSdk) {
                return false;
            }
            
            return true;
        });
        
        // Возвращаем самую новую версию среди совместимых
        return $compatibleApks->first();
    }
    
    /**
     * Получить APK файл для установки
     * GET /api/watchfaces/{id}/apk?device_sdk=34
     */
    public function getApk(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);
        
        // Проверяем, что пользователь купил это приложение
        $hasPurchase = WatchfaceSale::where('watchface_id', $watchface->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        
        // Для бесплатных приложений или если пользователь купил
        if (!$watchface->is_free && !$hasPurchase) {
            return response()->json(['error' => 'You need to purchase this app first'], 403);
        }
        
        $deviceSdk = $request->input('device_sdk');
        if ($deviceSdk !== null) {
            $deviceSdk = (int)$deviceSdk;
        }
        
        $apkFile = $this->getCompatibleApkFile($watchface, $deviceSdk);
        
        if (!$apkFile || !$apkFile->upload) {
            return response()->json(['error' => 'APK file not found'], 404);
        }
        
        // Логируем скачивание
        WatchfaceDownload::create([
            'watchface_id' => $watchface->id,
            'user_id' => $request->user()->id,
            'watchface_file_id' => $apkFile->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
        ]);
        
        return response()->json([
            'success' => true,
            'apk_url' => $apkFile->url,
            'version' => $apkFile->version,
            'min_sdk' => $apkFile->min_sdk,
            'target_sdk' => $apkFile->target_sdk,
            'max_sdk' => $apkFile->max_sdk,
        ]);
    }

    /**
     * Проверить, купил ли пользователь циферблат
     * GET /api/watchfaces/{id}/purchased
     */
    public function checkPurchase(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);
        
        // Для бесплатных приложений считаем, что они "куплены"
        if ($watchface->is_free) {
            return response()->json([
                'purchased' => true,
                'is_free' => true,
            ]);
        }
        
        // Проверяем покупку
        $hasPurchase = WatchfaceSale::where('watchface_id', $watchface->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        
        return response()->json([
            'purchased' => $hasPurchase,
            'is_free' => false,
        ]);
    }

    /**
     * Получить все купленные циферблаты пользователя (только платные)
     * GET /api/my/purchases
     */
    public function myPurchases(Request $request)
    {
        $user = $request->user();
        
        $purchases = $this->buildPurchasesPayload($user);
        
        return response()->json($purchases);
    }

    /**
     * Покупки для часового приложения (аналог /my/purchases, bearer токен)
     * GET /api/device/purchases
     */
    public function devicePurchases(Request $request)
    {
        $purchases = $this->buildPurchasesPayload($request->user());

        return response()->json($purchases);
    }

    /**
     * Получить историю установок пользователя (все установленные циферблаты - платные и бесплатные)
     * GET /api/my/installations
     */
    public function myInstallations(Request $request)
    {
        $user = $request->user();

        // Собираем уникальные ID установленных циферблатов из разных источников
        $installedFromDownloads = WatchfaceDownload::where('user_id', $user->id)
            ->distinct()
            ->pluck('watchface_id')
            ->toArray();

        $installedFromInstallations = WatchfaceInstallation::where('user_id', $user->id)
            ->where('status', 'installed')
            ->whereNotNull('watchface_id')
            ->pluck('watchface_id')
            ->toArray();

        $installedWatchfaceIds = array_values(array_unique(array_merge($installedFromDownloads, $installedFromInstallations)));

        if (empty($installedWatchfaceIds)) {
            return response()->json([]);
        }
        
        // Получаем все установленные циферблаты с файлами и категориями
        $watchfaces = Watchface::whereIn('id', $installedWatchfaceIds)
            ->where('status', 'published')
            ->with(['files.upload', 'categories'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($watchface) {
                return $this->formatWatchface($watchface);
            });
        
        return response()->json($watchfaces);
    }

    /**
     * Собирает список покупок с нужными полями для часов
     * Включает как платные покупки (watchface_sales), так и установленные бесплатные (watchface_installations)
     */
    private function buildPurchasesPayload($user): array
    {
        // Получаем платные покупки
        $sales = WatchfaceSale::where('user_id', $user->id)
            ->with(['watchface.files.upload', 'watchface.categories'])
            ->orderByDesc('created_at')
            ->get();

        // Получаем установленные бесплатные циферблаты
        $installedFree = WatchfaceInstallation::where('user_id', $user->id)
            ->where('status', 'installed')
            ->whereNotNull('watchface_id')
            ->with(['watchface.files.upload', 'watchface.categories'])
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($installation) {
                // Берем только бесплатные циферблаты, которые еще не в покупках
                return $installation->watchface && $installation->watchface->is_free;
            });

        // Объединяем покупки и установленные бесплатные
        $allWatchfaces = collect();
        
        // Добавляем платные покупки
        foreach ($sales as $sale) {
            if ($sale->watchface) {
                $allWatchfaces->push([
                    'watchface' => $sale->watchface,
                    'purchased_at' => $sale->created_at,
                    'source' => 'sale'
                ]);
            }
        }
        
        // Добавляем установленные бесплатные (только те, которых нет в покупках)
        $purchasedWatchfaceIds = $sales->pluck('watchface_id')->toArray();
        foreach ($installedFree as $installation) {
            if ($installation->watchface && !in_array($installation->watchface_id, $purchasedWatchfaceIds)) {
                $allWatchfaces->push([
                    'watchface' => $installation->watchface,
                    'purchased_at' => $installation->created_at,
                    'source' => 'installation'
                ]);
            }
        }

        if ($allWatchfaces->isEmpty()) {
            return [];
        }

        $installedVersions = $this->getInstalledVersionCodesMap($user->id);
        $purchases = [];

        foreach ($allWatchfaces as $item) {
            $watchface = $item['watchface'];

            // Пропускаем дубликаты, берем самую новую покупку/установку
            if (!$watchface || isset($purchases[$watchface->id])) {
                continue;
            }

            $purchases[$watchface->id] = $this->formatWatchfacePurchase(
                $watchface,
                $item['purchased_at'],
                $installedVersions[$watchface->id] ?? null
            );
        }

        return array_values($purchases);
    }

    /**
     * Последние установленные версии по watchface_id
     */
    private function getInstalledVersionCodesMap(int $userId): array
    {
        $installations = WatchfaceInstallation::where('user_id', $userId)
            ->where('status', 'installed')
            ->whereNotNull('watchface_id')
            ->with('watchfaceFile')
            ->orderByDesc('created_at')
            ->get();

        $map = [];

        foreach ($installations as $installation) {
            if (isset($map[$installation->watchface_id])) {
                continue;
            }

            $file = $installation->watchfaceFile;
            $versionCode = $file?->version_code ?? $this->normalizeVersionCode($file?->version);
            $map[$installation->watchface_id] = $versionCode;
        }

        return $map;
    }
    
    /**
     * Форматирование данных watchface для API
     */
    private function formatWatchface($watchface)
    {
        // Получаем иконку
        $iconUrl = $this->getIconUrl($watchface);

        // Получаем баннер
        $bannerUrl = $this->getLatestFileUrl($watchface, 'banner');

        return [
            'id' => $watchface->id,
            'title' => $watchface->title,
            'slug' => $watchface->slug,
            'description' => $watchface->description,
            'price' => $watchface->price,
            'discount_price' => $watchface->discount_price,
            'is_free' => $watchface->is_free,
            'type' => $watchface->type,
            'icon' => $iconUrl,
            'banner' => $bannerUrl,
            'categories' => $watchface->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ];
            }),
        ];
    }

    /**
     * Формат покупок для часов с version_code и статусом доступности
     */
    private function formatWatchfacePurchase($watchface, $purchasedAt, ?int $installedVersionCode): array
    {
        $base = $this->formatWatchface($watchface);

        $latestApk = $this->getLatestApkFile($watchface);

        $packageName = $this->resolvePackageName($watchface, $latestApk);
        $latestVersionCode = $latestApk?->version_code ?? $this->normalizeVersionCode($latestApk?->version);
        $isPublished = $watchface->status === 'published';
        $hasApk = $latestApk && $latestApk->upload && $latestApk->url;
        $isAvailable = $isPublished && $hasApk;

        $unavailableReason = null;
        if (!$isPublished) {
            $unavailableReason = 'not_published';
        } elseif (!$hasApk) {
            $unavailableReason = 'apk_missing';
        }

        $hasUpdate = false;
        if ($installedVersionCode !== null && $latestVersionCode !== null) {
            $hasUpdate = $installedVersionCode < $latestVersionCode;
        }

        return array_merge($base, [
            'package_name' => $packageName,
            'latest_version' => $latestApk?->version,
            'latest_version_code' => $latestVersionCode,
            'purchased_at' => $purchasedAt ? $purchasedAt->toIso8601String() : null,
            'has_update' => $hasUpdate,
            'is_available' => $isAvailable,
            'unavailable_reason' => $unavailableReason,
        ]);
    }

    /**
     * Берем самый свежий APK по version_code/версии
     */
    private function getLatestApkFile($watchface)
    {
        if (!$watchface->relationLoaded('files')) {
            $watchface->load('files.upload');
        }

        return $watchface->files
            ->where('type', 'apk')
            ->sortByDesc(function ($file) {
                $code = $file->version_code ?? -1;
                $version = $file->version ?? '';
                return sprintf('%020d_%s_%010d', $code, $version, $file->id);
            })
            ->first();
    }

    /**
     * URL иконки (квадрат, предпочтительно thumbnail, HTTPS)
     */
    private function getIconUrl($watchface): ?string
    {
        if (!$watchface->relationLoaded('files')) {
            $watchface->load('files.upload');
        }

        $iconFile = $watchface->files
            ->where('type', 'icon')
            ->sortByDesc('id')
            ->first();

        if (!$iconFile) {
            return null;
        }

        $url = $iconFile->thumbnail_url ?? $iconFile->url;

        return $this->forceHttps($url);
    }

    /**
     * Принудительно переключает http → https, если URL задан
     */
    private function forceHttps(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        if (str_starts_with($url, 'http://')) {
            return 'https://' . substr($url, 7);
        }

        return $url;
    }

    /**
     * Гарантирует наличие package_name: берем из watchface, иначе из APK и сохраняем
     */
    private function resolvePackageName($watchface, $latestApk): ?string
    {
        if (!empty($watchface->package_name)) {
            return $watchface->package_name;
        }

        if (!$latestApk || !$latestApk->upload) {
            return null;
        }

        $filePath = $this->findApkPath($latestApk->upload);
        if (!$filePath) {
            return null;
        }

        $pythonScript = base_path('parse_apk.py');
        if (!file_exists($pythonScript)) {
            return null;
        }

        $output = @shell_exec("python3 \"$pythonScript\" \"$filePath\" 2>&1");
        if (!$output) {
            return null;
        }

        $parsed = json_decode(trim($output), true);
        $packageName = $parsed['package_name'] ?? null;

        if ($packageName) {
            // Сохраняем в модели, чтобы больше не парсить
            $watchface->package_name = $packageName;
            $watchface->save();
        }

        return $packageName;
    }

    /**
     * Возвращает путь к APK на диске (ищем по нескольким вариантам)
     */
    private function findApkPath($upload): ?string
    {
        $candidates = [];

        if (!empty($upload->optimized_filename)) {
            $candidates[] = Storage::disk('public')->path('uploads/' . $upload->optimized_filename);
        }

        if (!empty($upload->filename)) {
            $candidates[] = Storage::disk('public')->path('uploads/' . $upload->filename);
            $candidates[] = Storage::disk('public')->path($upload->filename);
        }

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Преобразование версии из строки в целочисленный versionCode
     */
    private function normalizeVersionCode(?string $version): ?int
    {
        if ($version === null) {
            return null;
        }

        if (is_numeric($version)) {
            return (int) $version;
        }

        $digits = preg_replace('/\D/', '', $version);

        return $digits !== '' ? (int) $digits : null;
    }

    /**
     * Возвращает последний загруженный файл указанного типа
     */
    private function getLatestFileUrl($watchface, string $type): ?string
    {
        $file = $watchface->files
            ->where('type', $type)
            ->sortByDesc('id')
            ->first();

        return $file ? $file->url : null;
    }
}
