<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\DashboardController;
use App\Support\RoleName;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
});

Route::livewire('/daftar/{token}', 'public.register')->name('public.register');
Route::livewire('/lapor', 'public.report')->name('public.report');
Route::livewire('/checkin', 'public.checkin')->name('public.checkin');
Route::livewire('/sesi-kerja/{workSession}/cek', 'admin.work-sessions.verify')->name('work-sessions.verify');

// Route::middleware(['auth', 'role:'.RoleName::ADMIN])->group(function () {
//     Route::get('/dashboard', DashboardController::class)->name('dashboard');
//     Route::post('/logout', LogoutController::class)->name('logout');
//     Route::livewire('/sesi-kerja', 'admin.work-sessions.index')->name('work-sessions.index');
//     Route::livewire('/sesi-kerja/baru', 'admin.work-sessions.form')->name('work-sessions.create');
//     Route::livewire('/sesi-kerja/{workSession}/edit', 'admin.work-sessions.form')->name('work-sessions.edit');
//     Route::livewire('/sesi-kerja/{workSession}', 'admin.work-sessions.show')->name('work-sessions.show');
//     Route::livewire('/pengaturan', 'admin.settings')->name('settings.edit');
// });
Route::middleware(['auth', 'role:'.RoleName::ADMIN])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', LogoutController::class)->name('logout');
    Route::livewire('/sesi-kerja', 'admin.work-sessions.index')->name('work-sessions.index');
    Route::livewire('/sesi-kerja/baru', 'admin.work-sessions.form')->name('work-sessions.create');
    Route::livewire('/sesi-kerja/{workSession}/edit', 'admin.work-sessions.form')->name('work-sessions.edit');
    Route::livewire('/sesi-kerja/{workSession}', 'admin.work-sessions.show')->name('work-sessions.show');
    Route::livewire('/sesi-kerja/{workSession}/cek', 'admin.work-sessions.verify')->name('work-sessions.verify');
    Route::livewire('/pengaturan', 'admin.settings')->name('settings.edit');
});