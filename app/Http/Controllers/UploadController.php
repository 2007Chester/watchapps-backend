<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        // Проверка файла
        $request->validate([
            'file' => 'required|file|max:50000', // до 50 MB
        ]);

        // Получаем файл
        $file = $request->file('file');

        // Генерируем имя
        $filename = time() . '_' . $file->getClientOriginalName();

        // Сохраняем в storage/app/public/uploads
        $path = $file->storeAs('uploads', $filename, 'public');

        // Запись в БД
        $upload = Upload::create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'user_id' => $request->user()->id ?? null,
        ]);

        return response()->json([
            'id' => $upload->id,
            'filename' => $filename,
            'url' => $this->getFileUrl($filename),
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
