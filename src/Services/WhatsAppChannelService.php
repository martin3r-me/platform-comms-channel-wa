<?php

namespace Platform\Comms\ChannelWhatsApp\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;
use Illuminate\Support\Facades\Log;

class WhatsAppChannelService
{
    protected Client $client;

    public function __construct()
    {
        // API Version anpassbar machen (Standard: v21.0 wie im bestehenden Code)
        $apiVersion = config('channel-whatsapp.api_version', 'v21.0');
        $apiUrl = config('channel-whatsapp.api_url');
        
        // Wenn api_url nicht gesetzt ist, aus api_version bauen
        if (!$apiUrl) {
            $apiUrl = "https://graph.facebook.com/{$apiVersion}";
        }
        
        $this->client = new Client([
            'base_uri' => $apiUrl,
            'timeout'  => 30,
        ]);
    }

    /**
     * Sendet eine Textnachricht über die WhatsApp Business Cloud API.
     *
     * @param CommsChannelWhatsAppAccount $account
     * @param string $to Telefonnummer im internationalen Format (z.B. 4915123456789)
     * @param string $message Nachrichtentext
     * @param array $options Zusätzliche Optionen (z.B. preview_url, context)
     * @return array
     * @throws \Exception
     */
    public function sendMessage(
        CommsChannelWhatsAppAccount $account,
        string $to,
        string $message,
        array $options = []
    ): array {
        if (!$account->phone_number_id) {
            throw new \InvalidArgumentException('Phone Number ID ist nicht konfiguriert.');
        }

        // API Token kann pro Account oder geteilt sein (z.B. über business_id)
        $apiToken = $account->api_token ?? config('channel-whatsapp.api_token');
        
        if (!$apiToken) {
            throw new \InvalidArgumentException('API Token ist nicht konfiguriert (weder im Account noch in der Config).');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $this->normalizePhoneNumber($to),
            'type'              => 'text',
            'text'              => [
                'body' => $message,
            ],
        ];

        // Preview URL deaktivieren (Standard: true)
        if (isset($options['preview_url'])) {
            $payload['text']['preview_url'] = (bool) $options['preview_url'];
        }

        // Context für Antworten (wenn vorhanden)
        if (isset($options['context_message_id'])) {
            $payload['context'] = [
                'message_id' => $options['context_message_id'],
            ];
        }

        try {
            $response = $this->client->post("{$account->phone_number_id}/messages", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Log::info('WhatsApp message sent', [
                'account_id' => $account->id,
                'to'         => $to,
                'message_id' => $result['messages'][0]['id'] ?? null,
            ]);

            return $result;
        } catch (GuzzleException $e) {
            Log::error('WhatsApp message send failed', [
                'account_id' => $account->id,
                'to'         => $to,
                'error'      => $e->getMessage(),
            ]);

            throw new \Exception('Fehler beim Senden der WhatsApp-Nachricht: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Normalisiert eine Telefonnummer ins internationale Format.
     * Entfernt Leerzeichen, Bindestriche, Klammern und führende Nullen.
     *
     * @param string $phoneNumber
     * @return string
     */
    protected function normalizePhoneNumber(string $phoneNumber): string
    {
        // Entferne alle nicht-numerischen Zeichen außer +
        $normalized = preg_replace('/[^\d+]/', '', $phoneNumber);

        // Wenn mit + beginnt, belasse es; sonst füge + hinzu
        if (!str_starts_with($normalized, '+')) {
            // Entferne führende 0 und füge + hinzu (für deutsche Nummern)
            $normalized = preg_replace('/^0+/', '', $normalized);
            $normalized = '+' . $normalized;
        }

        return $normalized;
    }

    /**
     * Validiert eine Webhook-Signatur von Meta.
     *
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }
}

