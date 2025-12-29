<?php

namespace Platform\Comms\ChannelWhatsApp;

use Platform\Comms\Registry\ChannelRegistry;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;

class ChannelWhatsAppRegistrar
{
    public static function registerChannels(): void
    {
        // Registriere **alle** Accounts – Comms filtert später auf User/Team
        CommsChannelWhatsAppAccount::query()
            ->get()
            ->each(function (CommsChannelWhatsAppAccount $account) {
                ChannelRegistry::register([
                    'id'        => 'whatsapp:' . $account->id,   // eindeutig, lesbar
                    'type'      => 'whatsapp',
                    'label'     => $account->name ?? $account->phone_number,
                    'component' => \Platform\Comms\ChannelWhatsApp\Http\Livewire\Accounts\Index::class,
                    'group'     => 'WhatsApp',
                    'team_id'   => $account->team_id,
                    'user_id'   => $account->user_id, // darf null sein
                    'payload'   => [
                        'account_id' => $account->id,
                    ],
                ]);
            });
    }
}

