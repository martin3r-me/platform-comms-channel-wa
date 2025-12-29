<?php

use Illuminate\Support\Facades\Route;
use Platform\Comms\ChannelWhatsApp\Http\Controllers\WebhookController;

// Webhook-Endpunkt: /api/meta/whatsapp
Route::prefix('api/meta/whatsapp')->middleware('api')->group(function () {
    Route::get('/', [WebhookController::class, 'verify']);
    Route::post('/', [WebhookController::class, 'handle']);
});

