<?php

use Illuminate\Support\Facades\Route;
use Platform\Comms\ChannelWhatsApp\Http\Controllers\MetaOAuthController;

Route::prefix('whatsapp/oauth')->name('whatsapp.oauth.')->middleware('web')->group(function () {
    Route::get('/redirect', [MetaOAuthController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [MetaOAuthController::class, 'callback'])->name('callback');
    Route::get('/select', [MetaOAuthController::class, 'select'])->name('select');
    Route::post('/create-account', [MetaOAuthController::class, 'createAccount'])->name('create-account');
    Route::get('/success', [MetaOAuthController::class, 'success'])->name('success');
    Route::get('/error', [MetaOAuthController::class, 'error'])->name('error');
});

