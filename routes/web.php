<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Add web routes for authentication if needed
Route::get('/login', function () {
    return view('welcome'); // Redirect to welcome page or your frontend
})->name('login');

Route::get('/register', function () {
    return view('welcome'); // Redirect to welcome page or your frontend
})->name('register');

// Password reset redirect routes
Route::get('/reset-password-success', function () {
    return redirect()->away(config('app.frontend_url') . '/reset-password?status=success');
})->name('password.reset.success');

Route::get('/reset-password-error', function () {
    return redirect()->away(config('app.frontend_url') . '/reset-password?status=error');
})->name('password.reset.error');
