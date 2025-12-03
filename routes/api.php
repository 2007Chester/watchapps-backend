<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WatchfaceStatsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\WatchfaceController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DeveloperOnboardingController;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

/*
|--------------------------------------------------------------------------
| Sanctum CSRF Cookie (для SPA аутентификации)
|--------------------------------------------------------------------------
*/

Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

/*
|--------------------------------------------------------------------------
| AUTH (Публичные методы)
|--------------------------------------------------------------------------
*/

Route::post('/auth/register',      [AuthController::class, 'register']);
Route::post('/auth/login',         [AuthController::class, 'login']);
Route::post('/auth/check-email',   [AuthController::class, 'checkEmail']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| AUTHENTICATED ROUTES (Общие для всех ролей)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // User info + logout
    Route::get('/auth/user',   [AuthController::class, 'user']);
    Route::post('/auth/logout',[AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | EMAIL VERIFICATION
    |--------------------------------------------------------------------------
    */
    Route::post('/auth/send-verification', [AuthController::class, 'sendVerification']);

    /*
    |--------------------------------------------------------------------------
    | DEVELOPER ONBOARDING (доступно без verified, но только для developer)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:developer')
        ->prefix('dev/onboarding')
        ->group(function () {
            Route::get('/', [DeveloperOnboardingController::class, 'show']);
            Route::put('/', [DeveloperOnboardingController::class, 'update']);
            Route::post('/complete', [DeveloperOnboardingController::class, 'complete']);
        });
});

/*
|--------------------------------------------------------------------------
| ROUTES FOR VERIFIED ACCOUNTS
|--------------------------------------------------------------------------
|
| Любые действия, которые должны работать только после подтверждения email,
| пишем здесь. Это защищает от покупок / загрузок / dev console без верификации.
|
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Upload API
    |--------------------------------------------------------------------------
    */
    Route::post('/upload',   [UploadController::class, 'store']);
    Route::get('/uploads',   [UploadController::class, 'index']);

    /*
    |--------------------------------------------------------------------------
    | PURCHASE API
    |--------------------------------------------------------------------------
    */
    Route::post('/purchase', [PurchaseController::class, 'purchase']);

    /*
    |--------------------------------------------------------------------------
    | DEV CONSOLE (только для роли developer + verified)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'verified', 'role:developer'])
     ->prefix('dev')
     ->group(function () {

              // Статистика
              Route::get('/watchfaces/{id}/stats', [WatchfaceStatsController::class, 'stats']);

              // Список всех watchfaces текущего разработчика
              Route::get('/watchfaces', [WatchfaceController::class, 'index']);

              // Создать watchface
              Route::post('/watchfaces', [WatchfaceController::class, 'store']);

              // Получить один watchface
              Route::get('/watchfaces/{id}', [WatchfaceController::class, 'show']);

              // Файлы
              Route::post('/watchfaces/{id}/files', [WatchfaceController::class, 'attachFiles']);
              Route::post('/watchfaces/{watchfaceId}/files/{fileId}/replace', [WatchfaceController::class, 'replaceFile']);
              Route::delete('/watchfaces/{watchfaceId}/files/{fileId}', [WatchfaceController::class, 'deleteFile']);

              // Обновить watchface
              Route::put('/watchfaces/{id}', [WatchfaceController::class, 'update']);

              // Удалить watchface
              Route::delete('/watchfaces/{id}', [WatchfaceController::class, 'destroy']);

              // Publish / Unpublish
              Route::post('/watchfaces/{id}/publish', [WatchfaceController::class, 'publish']);
              Route::post('/watchfaces/{id}/unpublish', [WatchfaceController::class, 'unpublish']);
      });

});

/*
|--------------------------------------------------------------------------
| EMAIL CONFIRM (public callback)
|--------------------------------------------------------------------------
*/

Route::get("/auth/verify/{token}", [AuthController::class, "verifyEmail"]);

/*
|--------------------------------------------------------------------------
| PUBLIC CATALOG API
|--------------------------------------------------------------------------
*/

Route::get('/catalog/top',       [CatalogController::class, 'top']);
Route::get('/catalog/new',       [CatalogController::class, 'new']);
Route::get('/catalog/discounts', [CatalogController::class, 'discounts']);
Route::get('/catalog/category/{slug}', [CatalogController::class, 'byCategory']);

Route::get('/watchface/{slug}',  [CatalogController::class, 'show']);

/*
|--------------------------------------------------------------------------
| PUBLIC STATS API
|--------------------------------------------------------------------------
*/

Route::post('/watchface/{id}/log/view',  [WatchfaceStatsController::class, 'logView']);
Route::post('/watchface/{id}/log/click', [WatchfaceStatsController::class, 'logClick']);
