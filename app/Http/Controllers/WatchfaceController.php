<?php

namespace App\Http\Controllers;

use App\Models\Watchface;
use App\Models\Category;
use App\Models\Upload;
use App\Services\WatchfaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WatchfaceController extends Controller
{
    protected WatchfaceService $service;

    /**
     * Внедрение сервиса через конструктор
     */
    public function __construct(WatchfaceService $service)
    {
        $this->service = $service;
    }

    /**
     * Список watchfaces текущего разработчика (Dev Console)
     */
    public function index(Request $request)
    {
        $developerId = $request->user()->id;

        $items = Watchface::where('developer_id', $developerId)
            ->with(['files', 'categories'])
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'items'   => $items
        ]);
    }

    /**
     * Создание нового Watchface (Dev Console)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'price'           => 'required|integer|min:0',
            'is_free'         => 'boolean',
            'type'            => 'required|in:watchface,app',

            // скидка
            'discount_price'  => 'nullable|integer|min:0',
            'discount_end_at' => 'nullable|date',

            // категории
            'categories'      => 'nullable|array',
            'categories.*'    => 'integer|exists:categories,id',
        ]);

        $watchface = $this->service->create($data, $request->user()->id);

        if (!empty($data['categories'])) {
            $watchface->categories()->sync($data['categories']);
        }

        return response()->json([
            'success'   => true,
            'watchface' => $watchface->load('categories', 'files')
        ]);
    }

    /**
     * Получить один watchface для страницы редактирования (Dev Console)
     */
    public function show(Request $request, $id)
    {
        $watchface = Watchface::with(['files', 'categories'])
            ->findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'success'   => true,
            'watchface' => $watchface
        ]);
    }

    /**
     * Привязать файлы (apk, icon, banner, screenshot, preview_video)
     */
    public function attachFiles(Request $request, $id)
    {
        $data = $request->validate([
            'files'               => 'required|array',
            'files.*.upload_id'   => 'required|integer|exists:uploads,id',
            'files.*.type'        => 'required|in:icon,banner,screenshot,apk,preview_video',
            'files.*.sort_order'  => 'nullable|integer|min:0',
        ]);

        $watchface = Watchface::findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $this->service->attachFiles($watchface, $data['files']);

        return response()->json([
            'success'   => true,
            'watchface' => $watchface->load('files')
        ]);
    }

    /**
     * Обновление watchface + категорий + цены и скидки
     */
    public function update(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'price'           => 'sometimes|integer|min:0',
            'is_free'         => 'sometimes|boolean',
            'type'            => 'sometimes|in:watchface,app',

            // скидка
            'discount_price'  => 'nullable|integer|min:0',
            'discount_end_at' => 'nullable|date',

            // категории
            'categories'      => 'nullable|array',
            'categories.*'    => 'integer|exists:categories,id',
        ]);

        $updated = $this->service->update($watchface, $data);

        if (array_key_exists('categories', $data)) {
            $watchface->categories()->sync($data['categories'] ?? []);
        }

        return response()->json([
            'success'   => true,
            'watchface' => $updated->load('categories', 'files')
        ]);
    }

    /**
     * Удаление watchface (с отвязкой категорий и файлов)
     */
    public function destroy(Request $request, $id)
    {
        $watchface = Watchface::with('files')->findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        // удаляем связанные файлы и uploads
        foreach ($watchface->files as $file) {
            $upload = Upload::find($file->upload_id);
            if ($upload && $upload->path) {
                Storage::delete($upload->path);
                $upload->delete();
            }
            $file->delete();
        }

        $watchface->categories()->detach();
        $this->service->delete($watchface);

        return response()->json(['success' => true]);
    }

    /**
     * Публикация watchface
     */
    public function publish(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $updated = $this->service->publish($watchface);

        return response()->json([
            'success'   => true,
            'watchface' => $updated->load('categories', 'files')
        ]);
    }

    /**
     * Снятие watchface с публикации
     */
    public function unpublish(Request $request, $id)
    {
        $watchface = Watchface::findOrFail($id);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $updated = $this->service->unpublish($watchface);

        return response()->json([
            'success'   => true,
            'watchface' => $updated->load('categories', 'files')
        ]);
    }

    /**
     * Удалить файл (icon/banner/screenshot/apk/preview_video) у watchface
     * c удалением upload-записи и физического файла
     */
    public function deleteFile(Request $request, $watchfaceId, $fileId)
    {
        $watchface = Watchface::with('files')->findOrFail($watchfaceId);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $file = $watchface->files()->where('id', $fileId)->first();

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $upload = Upload::find($file->upload_id);
        if ($upload && $upload->path) {
            Storage::delete($upload->path);
            $upload->delete();
        }

        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'File removed successfully'
        ]);
    }

    /**
     * Заменить файл (icon/banner/screenshot/apk/preview_video) новым upload_id
     * с удалением старого файла
     */
    public function replaceFile(Request $request, $watchfaceId, $fileId)
    {
        $data = $request->validate([
            'upload_id' => 'required|integer|exists:uploads,id',
            'type'      => 'nullable|in:icon,banner,screenshot,apk,preview_video',
        ]);

        $watchface = Watchface::with('files')->findOrFail($watchfaceId);

        if ($watchface->developer_id !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $file = $watchface->files()->where('id', $fileId)->first();

        if (!$file) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // удаляем старый upload-файл
        $oldUpload = Upload::find($file->upload_id);
        if ($oldUpload && $oldUpload->path) {
            Storage::delete($oldUpload->path);
            $oldUpload->delete();
        }

        // привязываем новый upload
        $file->upload_id = $data['upload_id'];

        if (!empty($data['type'])) {
            $file->type = $data['type'];
        }

        $file->save();

        return response()->json([
            'success' => true,
            'file'    => $file
        ]);
    }
}
