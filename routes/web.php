<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment/success', function () {
    $app = request('app', 'client');
    $scheme = $app === 'therapist' ? 'spacalltherapist' : 'spacall';
    $url = $scheme . '://payment/success';

    return redirect($url);
})->name('payment.success');

Route::get('/payment/cancel', function () {
    $app = request('app', 'client');
    $scheme = $app === 'therapist' ? 'spacalltherapist' : 'spacall';
    $url = $scheme . '://payment/cancel';

    return redirect($url);
})->name('payment.cancel');
Route::get('/privacy-policy', function () {
    return view('privacy');
})->name('privacy');

Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);

    if (!file_exists($filePath)) {
        abort(404);
    }

    return response()->file($filePath);
})->where('path', '.*');
