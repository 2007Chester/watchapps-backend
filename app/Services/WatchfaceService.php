<?php

namespace App\Services;

use App\Models\Watchface;
use App\Models\WatchfaceFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WatchfaceService
{
    /**
     * Создание нового watchface
     */
    public function create(array $data, int $developerId): Watchface
    {
        return Watchface::create([
            'developer_id'   => $developerId,
            'title'          => $data['title'],
            'slug'           => Str::slug($data['title']).'-'.uniqid(),
            'description'    => $data['description'] ?? null,
            'price'          => $data['price'],
            'discount_price' => $data['discount_price'] ?? null,
            'discount_end_at'=> $data['discount_end_at'] ?? null,
            'is_free'        => $data['is_free'] ?? false,
            'type'           => $data['type'],
            'status'         => 'draft',
        ]);
    }

    /**
     * Обновление watchface
     */
    public function update(Watchface $watchface, array $data): Watchface
    {
        // Изменили название → обновляем slug
        if (isset($data['title']) && $data['title'] !== $watchface->title) {
            $data['slug'] = Str::slug($data['title']).'-'.uniqid();
        }

        $watchface->update($data);

        return $watchface;
    }

    /**
     * Добавление файлов
     */
    public function attachFiles(Watchface $watchface, array $files): void
    {
        DB::transaction(function () use ($watchface, $files) {
            foreach ($files as $file) {
                WatchfaceFile::create([
                    'watchface_id' => $watchface->id,
                    'upload_id'    => $file['upload_id'],
                    'type'         => $file['type'],
                    'sort_order'   => $file['sort_order'] ?? 0,
                ]);
            }
        });
    }

    /**
     * Публикация
     */
    public function publish(Watchface $watchface): Watchface
    {
        $watchface->update(['status' => 'published']);
        return $watchface;
    }

    /**
     * Снятие публикации
     */
    public function unpublish(Watchface $watchface): Watchface
    {
        $watchface->update(['status' => 'draft']);
        return $watchface;
    }

    /**
     * Удаление товара
     */
    public function delete(Watchface $watchface): void
    {
        $watchface->delete();
    }
}
