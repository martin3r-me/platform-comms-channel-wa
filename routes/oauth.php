<?php

use Illuminate\Support\Facades\Route;
use Platform\Comms\ChannelWhatsApp\Http\Controllers\MetaOAuthController;

// WhatsApp-spezifische OAuth-Routes (nutzen Meta-OAuth-Package)
Route::prefix('whatsapp/oauth')->name('whatsapp.oauth.')->middleware('web')->group(function () {
    // Redirect nutzt Meta-OAuth-Package
    Route::get('/redirect', [MetaOAuthController::class, 'redirect'])->name('redirect');
    
    // Callback nutzt Meta-OAuth-Package
    Route::get('/callback', [MetaOAuthController::class, 'callback'])->name('callback');
    
    // WhatsApp-spezifische Routes
    Route::get('/select', [MetaOAuthController::class, 'select'])->name('select');
    Route::post('/create-account', [MetaOAuthController::class, 'createAccount'])->name('create-account');
    Route::get('/success', [MetaOAuthController::class, 'success'])->name('success');
    Route::get('/error', [MetaOAuthController::class, 'error'])->name('error');
});

