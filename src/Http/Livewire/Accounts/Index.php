<?php

namespace Platform\Comms\ChannelWhatsApp\Http\Livewire\Accounts;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Str;
use Platform\Comms\Services\CommsActivityService;
use Platform\Comms\ChannelWhatsApp\Models\CommsChannelWhatsAppAccount;
use Platform\Comms\ChannelWhatsApp\Services\WhatsAppChannelService;

class Index extends Component
{
    public int $account_id;
    public CommsChannelWhatsAppAccount $account;
    public string $ui_mode = 'comms'; // comms|admin

    public bool $composeMode = false;
    public ?string $activeConversationId = null;

    public array $compose = [
        'to' => '',
        'message' => '',
    ];

    public string $replyBody = '';
    public array $context = [];
    public bool $showContextDetails = false;
    public string $activeTab = 'messages';

    // Settings-Properties
    public string $editName = '';
    public string $userSearch = '';
    public bool $showUserModal = false;

    #[Computed]
    public function conversations()
    {
        // TODO: Implementiere Conversations/Threads für WhatsApp
        // Für jetzt: leere Collection
        return collect([]);
    }

    public function mount(int $account_id, array $context = [], string $ui_mode = 'comms'): void
    {
        $this->account_id = $account_id;
        $this->context = $context;
        $this->ui_mode = $ui_mode ?: 'comms';
        $this->account = CommsChannelWhatsAppAccount::findOrFail($this->account_id);

        $this->activeConversationId = null;
        $this->composeMode = false;
        $this->replyBody = '';
    }

    public function backToConversationList(): void
    {
        $this->reset('activeConversationId', 'replyBody');
        $this->composeMode = false;
    }

    public function startNewMessage(): void
    {
        $this->reset('activeConversationId', 'replyBody');
        $this->reset('compose');
        $this->composeMode = true;

        // Optional: Empfänger aus Kontext
        $this->compose['to'] = collect($this->context['recipients'] ?? [])
            ->pluck('phone')
            ->filter()
            ->implode(', ');
    }

    public function cancelCompose(): void
    {
        $this->reset('compose', 'composeMode');
    }

    public function selectConversation(string $conversationId): void
    {
        $this->activeConversationId = $conversationId;
        $this->composeMode = false;
        $this->replyBody = '';

        // Unread für diesen Kontext/Channel als gesehen markieren
        if (!empty($this->context['model']) && !empty($this->context['modelId'])
            && class_exists(CommsActivityService::class) && CommsActivityService::enabled()
        ) {
            $userId = auth()->id();
            if ($userId) {
                CommsActivityService::markSeen(
                    userId: (int) $userId,
                    channelId: 'whatsapp:' . $this->account_id,
                    contextType: (string) $this->context['model'],
                    contextId: (int) $this->context['modelId'],
                    teamId: auth()->user()?->currentTeam?->id,
                );

                $this->dispatch('comms-indicator-refresh');
            }
        }
    }

    public function sendNewMessage(): void
    {
        $this->validate([
            'compose.to' => 'required|string',
            'compose.message' => 'required|string',
        ]);

        // Mehrere Empfänger erlauben (comma/semicolon separated)
        $toRaw = (string) ($this->compose['to'] ?? '');
        $phones = collect(preg_split('/[;,]+/', $toRaw))
            ->map(fn ($p) => trim((string) $p))
            ->filter();

        if ($phones->isEmpty()) {
            $this->addError('compose.to', 'Bitte eine oder mehrere gültige Telefonnummern angeben (kommagetrennt).');
            return;
        }

        // Normalisiere Empfänger
        $this->compose['to'] = $phones->implode(', ');

        $message = trim($this->compose['message']);

        // Kontext anhängen, falls sichtbar
        if ($this->showContextDetails && !empty($this->context)) {
            $contextText = "\n\n---\n";
            if (!empty($this->context['subject'])) {
                $contextText .= "Betreff: " . $this->context['subject'] . "\n";
            }
            if (!empty($this->context['description'])) {
                $contextText .= "Beschreibung: " . $this->context['description'] . "\n";
            }
            $message .= $contextText;
        }

        $whatsAppService = app(WhatsAppChannelService::class);

        // Sende an alle Empfänger
        foreach ($phones as $phone) {
            try {
                $whatsAppService->sendMessage(
                    account: $this->account,
                    to: $phone,
                    message: $message,
                    options: [
                        'sender' => auth()->user(),
                        'meta'   => $this->context['meta'] ?? [],
                        'context' => $this->context ?? null,
                    ]
                );
            } catch (\Exception $e) {
                $this->addError('compose.to', 'Fehler beim Senden an ' . $phone . ': ' . $e->getMessage());
                return;
            }
        }

        $this->reset('compose', 'composeMode');
        $this->account->refresh();
    }

    public function sendReply(): void
    {
        if (!$this->activeConversationId) {
            return;
        }

        $message = trim($this->replyBody);

        if (empty($message)) {
            $this->addError('replyBody', 'Bitte eine Nachricht eingeben.');
            return;
        }

        // TODO: Hole Conversation und Empfänger
        // Für jetzt: Fehler
        $this->addError('replyBody', 'Reply-Funktion noch nicht implementiert.');

        // $whatsAppService = app(WhatsAppChannelService::class);
        // $whatsAppService->sendMessage(...);

        $this->reset('replyBody');
        $this->account->refresh();
    }

    // Settings-Methoden
    public function startEditName(): void
    {
        $this->editName = $this->account->name ?? '';
    }

    public function saveName(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
        ]);

        $this->account->update(['name' => $this->editName]);
        $this->editName = '';
    }

    public function cancelEditName(): void
    {
        $this->editName = '';
    }

    public function updatedAccountOwnershipType(): void
    {
        // Wenn zu Team gewechselt wird, user_id auf null setzen
        if ($this->account->ownership_type === 'team') {
            $this->account->user_id = null;
        }

        // Wenn zu User gewechselt wird, user_id auf aktuellen User setzen
        if ($this->account->ownership_type === 'user') {
            $this->account->user_id = auth()->user()->id;
        }

        $this->account->save();

        $this->dispatch('comms-account-updated', accountId: $this->account->id);
    }

    public function updatedAccountUserId(): void
    {
        $this->account->save();

        $this->dispatch('comms-account-updated', accountId: $this->account->id);
    }

    /**
     * Leitet zum Meta OAuth-Flow weiter.
     */
    public function connectMeta(): void
    {
        return redirect()->route('whatsapp.oauth.redirect');
    }

    public function render()
    {
        return view('comms-channel-whatsapp::livewire.accounts.index');
    }
}

