<?php

namespace Platform\Comms\ChannelWhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modell: Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount
 *
 * Repräsentiert eine WhatsApp Phone Number (eine Nummer = ein Account).
 * Analog zum E-Mail-Channel: Ein Account = eine Adresse/Nummer.
 * Kann einem Team und optional einem Nutzer zugeordnet sein.
 * 
 * Ein Meta Business Account kann mehrere Phone Numbers haben,
 * daher können mehrere Accounts die gleiche business_id und api_token teilen.
 */
class CommsChannelWhatsAppAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'phone_number',
        'phone_number_id',
        'name',
        'business_id',
        'api_token',
        'webhook_token',
        'webhook_verify_token',
        'team_id',
        'created_by_user_id',
        'user_id',
        'ownership_type',
        'sender_type',
        'sender_id',
        'meta',
        'is_default',
    ];

    protected $casts = [
        'meta'       => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Team-Zugehörigkeit
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Benutzer, der das Konto erstellt hat
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
    }

    /**
     * Optionaler Benutzer (z. B. persönliche Konten)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * Polymorpher technischer Absender (z. B. Bot, SystemNutzer)
     */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Prüft, ob ein Benutzer Zugriff auf dieses Konto hat
     */
    public function hasUserAccess(\Platform\Core\Models\User $user): bool
    {
        // Ersteller hat immer Zugriff
        if ($this->created_by_user_id === $user->id) {
            return true;
        }

        // Privater Besitzer hat Zugriff
        if ($this->ownership_type === 'user' && $this->user_id === $user->id) {
            return true;
        }

        // Team-Mitglieder haben Zugriff auf Team-Konten
        if ($this->ownership_type === 'team' && $this->team_id === $user->currentTeam?->id) {
            return true;
        }

        return false;
    }
}

