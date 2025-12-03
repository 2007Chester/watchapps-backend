<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class DeveloperOnboardingController extends Controller
{
    /**
     * Получить данные онбординга текущего разработчика
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!in_array('developer', $user->getRoleNames())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'brand_name' => $user->brand_name,
                'logo_upload_id' => $user->logo_upload_id,
                'logo_url' => $user->logo ? $this->getFileUrl($user->logo->filename) : null,
                'onboarding_completed' => $user->onboarding_completed,
                'email_verified' => (bool)$user->email_verified_at,
            ],
        ]);
    }

    /**
     * Сохранить данные онбординга
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!in_array('developer', $user->getRoleNames())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:255'],
            'logo_upload_id' => ['nullable', 'integer', 'exists:uploads,id'],
        ]);

        // Проверяем, что upload принадлежит текущему пользователю
        if (isset($validated['logo_upload_id'])) {
            $upload = Upload::find($validated['logo_upload_id']);
            if ($upload && $upload->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Upload does not belong to you',
                ], 403);
            }
        }

        $user->update($validated);

        // Загружаем обновлённые данные
        $user->refresh();

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'brand_name' => $user->brand_name,
                'logo_upload_id' => $user->logo_upload_id,
                'logo_url' => $user->logo ? $this->getFileUrl($user->logo->filename) : null,
                'onboarding_completed' => $user->onboarding_completed,
                'email_verified' => (bool)$user->email_verified_at,
            ],
        ]);
    }

    /**
     * Завершить онбординг
     */
    public function complete(Request $request)
    {
        $user = $request->user();

        if (!in_array('developer', $user->getRoleNames())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user->update([
            'onboarding_completed' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed',
            'user' => [
                'id' => $user->id,
                'onboarding_completed' => $user->onboarding_completed,
            ],
        ]);
    }

    /**
     * Получить URL для файла с правильным протоколом
     */
    private function getFileUrl(string $filename): string
    {
        $request = request();
        
        // Проверяем заголовки прокси для определения реального хоста
        $host = $request->header('X-Forwarded-Host') 
            ?: $request->header('Host') 
            ?: $request->getHost();
        
        // Проверяем Origin или Referer для определения домена фронтенда
        $origin = $request->header('Origin') ?: $request->header('Referer');
        if ($origin) {
            $parsed = parse_url($origin);
            if (isset($parsed['host'])) {
                $host = $parsed['host'];
            }
        }
        
        // Определяем протокол
        $protocol = 'https';
        if ($request->header('X-Forwarded-Proto') === 'https' || 
            $request->isSecure() ||
            str_contains($host, 'watchapps.ru')) {
            $protocol = 'https';
        } elseif ($request->header('X-Forwarded-Proto') === 'http') {
            $protocol = 'http';
        }
        
        // Для production доменов всегда используем правильный домен
        if (str_contains($host, 'dev.watchapps.ru')) {
            $baseUrl = 'https://dev.watchapps.ru';
        } elseif (str_contains($host, 'watchapps.ru')) {
            $baseUrl = 'https://watchapps.ru';
        } else {
            // Для локальной разработки используем текущий хост
            $port = $request->getPort();
            $baseUrl = $protocol . '://' . $host . ($port && $port !== 80 && $port !== 443 ? ':' . $port : '');
        }
        
        return $baseUrl . '/storage/uploads/' . $filename;
    }
}
