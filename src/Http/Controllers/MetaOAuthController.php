<?php

namespace Platform\Comms\ChannelWhatsApp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;
use Platform\Comms\Registry\ChannelProviderRegistry;
use Platform\MetaOAuth\Services\MetaOAuthService;

class MetaOAuthController extends Controller
{
    protected MetaOAuthService $metaOAuthService;

    public function __construct(MetaOAuthService $metaOAuthService)
    {
        $this->metaOAuthService = $metaOAuthService;
    }

    /**
     * Startet den OAuth-Flow und leitet zum Meta-Login weiter.
     */
    public function redirect(Request $request)
    {
        $scopes = [
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ];

        $redirectUrl = $this->metaOAuthService->getRedirectUrl($scopes);

        return redirect($redirectUrl);
    }

    /**
     * Callback nach Meta-OAuth-Authentifizierung.
     */
    public function callback(Request $request)
    {
        // State verifizieren
        $requestState = $request->query('state');
        if ($requestState && !$this->metaOAuthService->verifyState($requestState)) {
            Log::error('WhatsApp OAuth state mismatch');
            return redirect()->route('whatsapp.oauth.error')
                ->with('error', 'Ungültiger OAuth-State. Bitte versuche es erneut.');
        }

        $code = $request->query('code');
        if (!$code) {
            $error = $request->query('error');
            return redirect()->route('whatsapp.oauth.error')
                ->with('error', $error ?? 'OAuth-Fehler: Kein Code erhalten.');
        }

        // Access Token holen
        try {
            $tokenData = $this->metaOAuthService->exchangeCodeForToken($code);
            $accessToken = $tokenData['access_token'];

            $businessAccounts = $this->metaOAuthService->getBusinessAccounts($accessToken);
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
                $phoneData = $this->metaOAuthService->getWhatsAppPhoneNumbers($accessToken, $businessId);

                foreach ($phoneData as $phone) {
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

