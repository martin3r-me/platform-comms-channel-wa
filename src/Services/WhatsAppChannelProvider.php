<?php

namespace Platform\Comms\ChannelWhatsApp\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Platform\Comms\Contracts\ChannelProviderInterface;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;

class WhatsAppChannelProvider implements ChannelProviderInterface
{
    public function getType(): string
    {
        return 'whatsapp';
    }

    /**
     * Erwartet mindestens: phone_number (oder phone), phone_number_id und team_id.
     * Optional: user_id (für persönliche Konten), name, meta, is_default, api_token, business_id.
     * 
     * Hinweis: 
     * - phone_number_id ist die Meta Phone Number ID (z.B. 366239283XXXXX) - PFLICHT
     * - business_id ist die WhatsApp Business Account ID (z.B. 3870XXXXXXXX) - optional, aber empfohlen
     * Ein Business Account kann mehrere Phone Numbers haben - jede bekommt ihren eigenen Account.
     */
    public function createChannel(array $data): string
    {
        $phoneNumber = $data['phone_number'] ?? $data['phone'] ?? null;
        $phoneNumberId = $data['phone_number_id'] ?? null;
        $teamId = $data['team_id'] ?? Auth::user()?->currentTeam?->id;

        if (!$phoneNumber) {
            throw new \InvalidArgumentException('phone_number (oder phone) ist erforderlich.');
        }

        if (!$phoneNumberId) {
            throw new \InvalidArgumentException('phone_number_id ist erforderlich (Meta Phone Number ID).');
        }

        if (!$teamId) {
            throw new \InvalidArgumentException('team_id ist erforderlich.');
        }

        $userId = $data['user_id'] ?? null;
        $createdBy = $data['created_by_user_id'] ?? Auth::id();
        $ownership = $data['ownership_type'] ?? ($userId ? 'user' : 'team');

        $account = CommsChannelWhatsAppAccount::create([
            'phone_number'          => $phoneNumber,
            'phone_number_id'       => $phoneNumberId, // Meta Phone Number ID (Pflicht)
            'name'                  => $data['name'] ?? null,
            'business_id'           => $data['business_id'] ?? null, // Optional, kann geteilt werden
            'api_token'             => $data['api_token'] ?? null, // Optional, kann geteilt werden
            'webhook_token'         => $data['webhook_token'] ?? Str::random(32),
            'webhook_verify_token'   => $data['webhook_verify_token'] ?? Str::random(32),
            'team_id'               => $teamId,
            'user_id'               => $ownership === 'user' ? $userId : null,
            'created_by_user_id'    => $createdBy,
            'ownership_type'        => $ownership,
            'meta'                  => $data['meta'] ?? [],
            'is_default'            => $data['is_default'] ?? false,
        ]);

        return 'whatsapp:' . $account->id;
    }

    public function deleteChannel(string $channelId): void
    {
        // Erwartetes Format: whatsapp:{id}
        if (!str_starts_with($channelId, 'whatsapp:')) {
            throw new \InvalidArgumentException('Channel-ID gehört nicht zu diesem Provider.');
        }

        $id = (int) str_replace('whatsapp:', '', $channelId);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Ungültige Channel-ID.');
        }

        $account = CommsChannelWhatsAppAccount::find($id);
        if ($account) {
            $account->delete();
        }
    }
}

