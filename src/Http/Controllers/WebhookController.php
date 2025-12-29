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
        // Raw JSON für Debugging speichern (wie im bestehenden Code)
        $rawJson = $request->getContent();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "wa_raw_{$timestamp}.json";
        file_put_contents(storage_path("logs/{$filename}"), $rawJson);
        Log::info("📄 Webhook-JSON gespeichert unter: {$filename}");

        // Signatur-Validierung (optional, aber empfohlen)
        $signature = $request->header('X-Hub-Signature-256');
        $secret = config('channel-whatsapp.webhook.secret');

        if ($secret && $signature) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawJson, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                Log::warning('WhatsApp webhook signature mismatch');
                return response('Unauthorized', 401);
            }
        }

        // JSON dekodieren mit hoher Tiefe (wie im bestehenden Code)
        $data = json_decode($rawJson, true, 2048);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("❌ JSON-Parsing fehlgeschlagen: " . json_last_error_msg());
            return response('Ungültiger JSON-Body', 400);
        }

        Log::info('WhatsApp webhook received', [
            'object' => $data['object'] ?? null,
            'entry_count' => count($data['entry'] ?? []),
        ]);

        // Verarbeite Webhook-Events
        if (!empty($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                try {
                    $this->processEntry($entry);
                } catch (\Throwable $e) {
                    Log::error("💥 Fehler bei Verarbeitung des Entries: {$e->getMessage()}", [
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }

        return response()->json(['status' => 'ok', 'saved' => $filename], 200);
    }

    /**
     * Verarbeitet einen einzelnen Webhook-Entry.
     */
    protected function processEntry(array $entry): void
    {
        // Account über business_id finden (wie im bestehenden Code)
        $businessAccountId = $entry['id'] ?? null;
        $account = null;

        if ($businessAccountId) {
            $account = \Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount::where('business_id', $businessAccountId)->first();
        }

        if (!$account) {
            Log::warning("⚠️ Kein WhatsApp-Account mit business_id {$businessAccountId} gefunden.");
            return;
        }

        $changes = $entry['changes'] ?? [];

        foreach ($changes as $change) {
            $value = $change['value'] ?? [];

            // Status-Updates (z.B. Nachricht wurde zugestellt/gelesen)
            if (isset($value['statuses'])) {
                $this->handleStatusUpdates($value['statuses'], $account);
            }

            // Eingehende Nachrichten
            if (isset($value['messages'])) {
                $this->handleIncomingMessages($value['messages'], $value, $account);
            }
        }
    }

    /**
     * Verarbeitet Status-Updates (Nachricht zugestellt, gelesen, etc.).
     */
    protected function handleStatusUpdates(array $statuses, \Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount $account): void
    {
        foreach ($statuses as $status) {
            $msgId = $status['id'] ?? '[unknown]';
            $statusType = $status['status'] ?? '[unknown]';

            Log::info("📊 Status-Update: {$msgId} -> {$statusType}", [
                'message_id' => $msgId,
                'status'     => $statusType,
                'timestamp'  => $status['timestamp'] ?? null,
                'account_id' => $account->id,
            ]);

            // TODO: Status in Datenbank speichern/aktualisieren
            // z.B. wenn es Threads/Messages gibt, deren Status aktualisiert werden muss
        }
    }

    /**
     * Verarbeitet eingehende Nachrichten.
     */
    protected function handleIncomingMessages(array $messages, array $value, \Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount $account): void
    {
        foreach ($messages as $message) {
            $msgId = $message['id'] ?? '[unknown]';
            $msgFrom = $message['from'] ?? '[unknown]';
            $msgType = $message['type'] ?? '[unknown]';

            Log::info("📩 Eingehende Nachricht: {$msgId} von {$msgFrom} (Typ: {$msgType})", [
                'message_id' => $msgId,
                'from'       => $msgFrom,
                'type'       => $msgType,
                'timestamp'  => $message['timestamp'] ?? null,
                'account_id' => $account->id,
            ]);

            // Optional: Image-ID prüfen (wie im bestehenden Code)
            if (
                isset($message['image']['id']) &&
                is_string($message['image']['id']) &&
                !str_contains($message['image']['id'], 'Over 9 levels deep')
            ) {
                Log::info("🖼 Bild-ID erkannt: " . $message['image']['id']);
            }

            try {
                // TODO: Nachricht in Datenbank speichern
                // TODO: Thread/Conversation erstellen oder finden
                // TODO: CommsActivity erstellen
                // TODO: Benachrichtigungen senden
                
                // Beispiel-Struktur (muss noch implementiert werden):
                // $this->processIncomingMessage($message, $account);
                
            } catch (\Throwable $e) {
                Log::error("💥 Fehler bei Verarbeitung der Nachricht: {$e->getMessage()}", [
                    'message_id' => $msgId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
}

