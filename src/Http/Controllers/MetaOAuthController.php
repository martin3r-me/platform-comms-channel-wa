<?php

namespace Platform\Comms\ChannelWhatsApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;
use Platform\Comms\Registry\ChannelProviderRegistry;

class MetaOAuthController extends Controller
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => config('channel-whatsapp.api_url', 'https://graph.facebook.com/v18.0'),
            'timeout'  => 30,
        ]);
    }

    /**
     * Startet den OAuth-Flow und leitet zum Meta-Login weiter.
     */
    public function redirect(Request $request)
    {
        $appId = config('channel-whatsapp.app_id');
        $redirectUri = route('whatsapp.oauth.callback');
        $state = Str::random(32);
        
        // State in Session speichern für Verifizierung
        session(['whatsapp_oauth_state' => $state]);

        $scopes = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];

        $params = http_build_query([
            'client_id'     => $appId,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'scope'         => implode(',', $scopes),
            'response_type' => 'code',
        ]);

        $authUrl = "https://www.facebook.com/v18.0/dialog/oauth?{$params}";

        return redirect($authUrl);
    }

    /**
     * Callback nach Meta-OAuth-Authentifizierung.
     */
    public function callback(Request $request)
    {
        // State verifizieren
        $sessionState = session('whatsapp_oauth_state');
        $requestState = $request->query('state');

        if (!$sessionState || $sessionState !== $requestState) {
            Log::error('WhatsApp OAuth state mismatch');
            return redirect()->route('whatsapp.oauth.error')
                ->with('error', 'Ungültiger OAuth-State. Bitte versuche es erneut.');
        }

        session()->forget('whatsapp_oauth_state');

        $code = $request->query('code');
        if (!$code) {
            $error = $request->query('error');
            return redirect()->route('whatsapp.oauth.error')
                ->with('error', $error ?? 'OAuth-Fehler: Kein Code erhalten.');
        }

        // Access Token holen
        try {
            $accessToken = $this->exchangeCodeForToken($code);
            $businessAccounts = $this->getBusinessAccounts($accessToken);
            $phoneNumbers = $this->getPhoneNumbers($accessToken, $businessAccounts);

            // Phone Numbers in Session speichern für Auswahl
            session([
                'whatsapp_oauth_token' => $accessToken,
                'whatsapp_phone_numbers' => $phoneNumbers,
            ]);

            return redirect()->route('whatsapp.oauth.select');
        } catch (\Exception $e) {
            Log::error('WhatsApp OAuth callback error', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('whatsapp.oauth.error')
                ->with('error', 'Fehler beim Abrufen der Daten: ' . $e->getMessage());
        }
    }

    /**
     * Zeigt die Auswahl der verfügbaren Phone Numbers.
     */
    public function select(Request $request)
    {
        $phoneNumbers = session('whatsapp_phone_numbers', []);
        $accessToken = session('whatsapp_oauth_token');

        if (empty($phoneNumbers) || !$accessToken) {
            return redirect()->route('whatsapp.oauth.error')
                ->with('error', 'Keine Phone Numbers gefunden. Bitte starte den OAuth-Flow erneut.');
        }

        return view('comms-channel-whatsapp::oauth.select', [
            'phoneNumbers' => $phoneNumbers,
            'accessToken'  => $accessToken,
        ]);
    }

    /**
     * Erstellt einen Account für die ausgewählte Phone Number.
     */
    public function createAccount(Request $request)
    {
        $request->validate([
            'phone_number_id' => 'required|string',
            'name'            => 'nullable|string|max:255',
        ]);

        $phoneNumbers = session('whatsapp_phone_numbers', []);
        $accessToken = session('whatsapp_oauth_token');
        $businessId = session('whatsapp_business_id');

        // Finde die ausgewählte Phone Number
        $selected = collect($phoneNumbers)->firstWhere('id', $request->phone_number_id);

        if (!$selected) {
            return back()->withErrors(['phone_number_id' => 'Ungültige Phone Number ausgewählt.']);
        }

        try {
            // Account erstellen
            $channelId = ChannelProviderRegistry::create('whatsapp', [
                'phone_number'    => $selected['phone_number'] ?? $selected['display_phone_number'] ?? $request->phone_number,
                'phone_number_id'  => $request->phone_number_id,
                'name'            => $request->name ?? $selected['display_phone_number'] ?? null,
                'business_id'     => $businessId,
                'api_token'       => $accessToken, // Temporär, sollte später erneuert werden
                'team_id'         => Auth::user()?->currentTeam?->id,
            ]);

            // Session aufräumen
            session()->forget(['whatsapp_oauth_token', 'whatsapp_phone_numbers', 'whatsapp_business_id']);

            return redirect()->route('whatsapp.oauth.success')
                ->with('success', 'WhatsApp-Account erfolgreich verbunden!')
                ->with('channel_id', $channelId);
        } catch (\Exception $e) {
            Log::error('WhatsApp account creation failed', [
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Fehler beim Erstellen des Accounts: ' . $e->getMessage()]);
        }
    }

    /**
     * Erfolgsseite nach Account-Erstellung.
     */
    public function success(Request $request)
    {
        return view('comms-channel-whatsapp::oauth.success');
    }

    /**
     * Fehlerseite.
     */
    public function error(Request $request)
    {
        return view('comms-channel-whatsapp::oauth.error', [
            'error' => $request->session()->get('error'),
        ]);
    }

    /**
     * Tauscht den OAuth-Code gegen ein Access Token.
     */
    protected function exchangeCodeForToken(string $code): string
    {
        $appId = config('channel-whatsapp.app_id');
        $appSecret = config('channel-whatsapp.app_secret');
        $redirectUri = route('whatsapp.oauth.callback');

        $response = $this->client->get('/oauth/access_token', [
            'query' => [
                'client_id'     => $appId,
                'client_secret' => $appSecret,
                'redirect_uri'  => $redirectUri,
                'code'          => $code,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['access_token'])) {
            throw new \Exception('Kein Access Token erhalten: ' . json_encode($data));
        }

        return $data['access_token'];
    }

    /**
     * Holt alle Business Accounts des Nutzers.
     */
    protected function getBusinessAccounts(string $accessToken): array
    {
        $response = $this->client->get('/me/businesses', [
            'query' => [
                'access_token' => $accessToken,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data['data'] ?? [];
    }

    /**
     * Holt alle Phone Numbers für die Business Accounts.
     */
    protected function getPhoneNumbers(string $accessToken, array $businessAccounts): array
    {
        $phoneNumbers = [];

        foreach ($businessAccounts as $business) {
            $businessId = $business['id'] ?? null;
            if (!$businessId) {
                continue;
            }

            // Speichere business_id in Session
            session(['whatsapp_business_id' => $businessId]);

            try {
                $response = $this->client->get("/{$businessId}/phone_numbers", [
                    'query' => [
                        'access_token' => $accessToken,
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                foreach ($data['data'] ?? [] as $phone) {
                    $phoneNumbers[] = [
                        'id'                  => $phone['id'] ?? null,
                        'phone_number'        => $phone['phone_number'] ?? null,
                        'display_phone_number' => $phone['display_phone_number'] ?? null,
                        'verified_name'       => $phone['verified_name'] ?? null,
                        'code_verification_status' => $phone['code_verification_status'] ?? null,
                        'quality_rating'      => $phone['quality_rating'] ?? null,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to fetch phone numbers for business', [
                    'business_id' => $businessId,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        return $phoneNumbers;
    }
}

