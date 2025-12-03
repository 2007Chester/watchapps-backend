<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

Route::get('/', function () {
    return response()->make(
        '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WatchApps API</title>
    <style>
        body {
            background: #0d1117;
            color: #c9d1d9;
            font-family: Arial, sans-serif;
            padding: 40px;
        }
        .box {
            max-width: 480px;
            margin: 80px auto;
            background: #161b22;
            padding: 30px 40px;
            border-radius: 12px;
            border: 1px solid #30363d;
        }
        h1 {
            margin: 0;
            font-size: 28px;
            color: #58a6ff;
        }
        p {
            margin-top: 14px;
            font-size: 15px;
            line-height: 1.6;
        }
        .ok {
            margin-top: 20px;
            padding: 10px 16px;
            background: #238636;
            color: #fff;
            border-radius: 6px;
            display: inline-block;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="box">
    <h1>WatchApps API</h1>
    <p>Welcome to the backend API for WatchApps.<br>
       The server is working correctly and secured with HTTPS.</p>
    <div class="ok">Status: OK</div>
</div>
</body>
</html>',
        200,
        ['Content-Type' => 'text/html']
    );
});

// Sanctum CSRF cookie route for SPA authentication
Route::get('/sanctum/csrf-cookie', [CsrfCookieController::class, 'show']);

