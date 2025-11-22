<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WatchfaceController;
use App\Http\Controllers\CatalogController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // User info + logout
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Upload API
    |--------------------------------------------------------------------------
    */
    Route::post('/upload', [UploadController::class, 'store']);
    Route::get('/uploads', [UploadController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | Dev Console — только для разработчиков
    |--------------------------------------------------------------------------
    */
    Route::middleware(['role:developer'])->group(function () {

        Route::prefix('dev')->group(function () {

            // Список всех watchfaces текущего разработчика
            Route::get('/watchfaces', [WatchfaceController::class, 'index']);

            // Создать watchface
            Route::post('/watchfaces', [WatchfaceController::class, 'store']);

            // Получить один watchface для редактирования
            Route::get('/watchfaces/{id}', [WatchfaceController::class, 'show']);

            // Привязать файлы (apk/icon/banner/screenshot/preview_video)
            Route::post('/watchfaces/{id}/files', [WatchfaceController::class, 'attachFiles']);

            // Удалить файл watchface
            Route::delete('/watchfaces/{watchfaceId}/files/{fileId}', [WatchfaceController::class, 'deleteFile']);

            // Заменить файл watchface
            Route::post('/watchfaces/{watchfaceId}/files/{fileId}/replace', [WatchfaceController::class, 'replaceFile']);

            // Обновить watchface
            Route::put('/watchfaces/{id}', [WatchfaceController::class, 'update']);

            // Удалить watchface
            Route::delete('/watchfaces/{id}', [WatchfaceController::class, 'destroy']);

            // Publish / Unpublish
            Route::post('/watchfaces/{id}/publish', [WatchfaceController::class, 'publish']);
            Route::post('/watchfaces/{id}/unpublish', [WatchfaceController::class, 'unpublish']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| PUBLIC CATALOG API
|--------------------------------------------------------------------------
*/

Route::get('/catalog/top', [CatalogController::class, 'top']);
Route::get('/catalog/new', [CatalogController::class, 'new']);
Route::get('/catalog/discounts', [CatalogController::class, 'discounts']);
Route::get('/catalog/category/{slug}', [CatalogController::class, 'byCategory']);

Route::get('/watchface/{slug}', [CatalogController::class, 'show']);
