<?php

namespace Platform\Comms\ChannelWhatsApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;
use Platform\Comms\ChannelWhatsApp\Services\WhatsAppChannelService;

class WebhookController extends Controller
{
    protected WhatsAppChannelService $whatsAppService;

    public function __construct(WhatsAppChannelService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Verifiziert den Webhook bei Meta (GET-Request).
     * Wird beim ersten Setup aufgerufen.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = config('channel-whatsapp.webhook.verify_token');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified', [
                'mode'      => $mode,
                'challenge' => $challenge,
            ]);

            return response($challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed', [
            'mode'         => $mode,
            'token_match' => $token === $verifyToken,
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Verarbeitet eingehende Webhook-Events von Meta (POST-Request).
     */
    public function handle(Request $request): Response
    {
        // Signatur-Validierung (optional, aber empfohlen)
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('channel-whatsapp.webhook.secret');

        if ($secret && $signature) {
            $payload = $request->getContent();
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('WhatsApp webhook signature mismatch');
                return response('Unauthorized', 401);
            }
        }

        $data = $request->json()->all();

        Log::info('WhatsApp webhook received', [
            'object' => $data['object'] ?? null,
            'entry_count' => count($data['entry'] ?? []),
        ]);

        // Verarbeite Webhook-Events
        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                $this->processEntry($entry);
            }
        }

        return response('OK', 200);
    }

    /**
     * Verarbeitet einen einzelnen Webhook-Entry.
     */
    protected function processEntry(array $entry): void
    {
        $changes = $entry['changes'] ?? [];

        foreach ($changes as $change) {
            $value = $change['value'] ?? [];

            // Status-Updates (z.B. Nachricht wurde zugestellt/gelesen)
            if (isset($value['statuses'])) {
                $this->handleStatusUpdates($value['statuses']);
            }

            // Eingehende Nachrichten
            if (isset($value['messages'])) {
                $this->handleIncomingMessages($value['messages'], $value);
            }
        }
    }

    /**
     * Verarbeitet Status-Updates (Nachricht zugestellt, gelesen, etc.).
     */
    protected function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {
            Log::info('WhatsApp status update', [
                'message_id' => $status['id'] ?? null,
                'status'     => $status['status'] ?? null,
                'timestamp'  => $status['timestamp'] ?? null,
            ]);

            // TODO: Status in Datenbank speichern/aktualisieren
        }
    }

    /**
     * Verarbeitet eingehende Nachrichten.
     */
    protected function handleIncomingMessages(array $messages, array $value): void
    {
        foreach ($messages as $message) {
            Log::info('WhatsApp incoming message', [
                'message_id' => $message['id'] ?? null,
                'from'       => $message['from'] ?? null,
                'type'       => $message['type'] ?? null,
                'timestamp'  => $message['timestamp'] ?? null,
            ]);

            // TODO: Nachricht in Datenbank speichern
            // TODO: CommsActivity erstellen
            // TODO: Benachrichtigungen senden
        }
    }
}

