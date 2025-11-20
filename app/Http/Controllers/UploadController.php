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
            'url' => asset('storage/uploads/' . $filename),
        ]);
    }
}
