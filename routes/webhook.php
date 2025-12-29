<?php

use Illuminate\Support\Facades\Route;
use Platform\Comms\ChannelWhatsApp\Http\Controllers\WebhookController;

Route::prefix('whatsapp/webhook')->group(function () {
    Route::get('/', [WebhookController::class, 'verify']);
    Route::post('/', [WebhookController::class, 'handle']);
});

