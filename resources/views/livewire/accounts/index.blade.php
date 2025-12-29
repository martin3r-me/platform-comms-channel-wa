<div class="h-full flex flex-col bg-white">
    {{-- Header --}}
    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200">
        <div class="min-w-0">
            <div class="text-base font-semibold text-gray-900 truncate">
                {{ $account->name ?? $account->phone_number }}
            </div>
            @if (!empty($context))
                <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700">
                        {{ class_basename($context['model'] ?? '') }} #{{ $context['modelId'] ?? '' }}
                    </span>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2 justify-end">
            @if (!$account->phone_number_id)
                <a
                    href="{{ route('whatsapp.oauth.redirect') }}"
                    class="inline-flex items-center gap-2 rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                >
                    @svg('heroicon-o-link', 'w-4 h-4')
                    <span>Mit Meta verbinden</span>
                </a>
            @else
                <button
                    type="button"
                    wire:click="startNewMessage"
                    class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                >
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neue Nachricht</span>
                </button>
            @endif

            @if (!empty($context))
                <button
                    type="button"
                    wire:click="$toggle('showContextDetails')"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                    title="{{ $showContextDetails ? 'Kontext ausblenden' : 'Kontext einblenden' }}"
                >
                    @svg('heroicon-o-information-circle', 'w-4 h-4')
                    <span class="hidden sm:inline">{{ $showContextDetails ? 'Kontext' : 'Kontext' }}</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Kontextdetails (optional) --}}
    @if ($showContextDetails && !empty($context))
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div><span class="font-semibold">Typ:</span> {{ class_basename($context['model'] ?? '') }}</div>
                <div><span class="font-semibold">ID:</span> {{ $context['modelId'] ?? '' }}</div>
                @if (!empty($context['subject']))
                    <div class="sm:col-span-2"><span class="font-semibold">Betreff:</span> {{ $context['subject'] }}</div>
                @endif
                @if (!empty($context['description']))
                    <div class="sm:col-span-2">
                        <span class="font-semibold">Beschreibung:</span>
                        <div class="mt-1 whitespace-pre-wrap text-gray-600">{{ $context['description'] }}</div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Comms-Mode: Conversations/Reply (MVP) --}}
    @if(($ui_mode ?? 'comms') === 'comms')
        <div class="flex-1 min-h-0 flex divide-x divide-gray-200">
            {{-- Conversation-Liste --}}
            <div class="w-80 lg:w-96 shrink-0 min-h-0 overflow-y-auto {{ ($activeConversationId || $composeMode) ? 'hidden md:block' : '' }}">
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between gap-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Conversations</div>
                    <button
                        type="button"
                        wire:click="startNewMessage"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                        title="Neue Nachricht starten"
                    >
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Neu</span>
                    </button>
                </div>

                <div class="p-2">
                    @if($this->conversations->count() > 0)
                        <div class="space-y-1">
                            @foreach ($this->conversations as $conversation)
                                {{-- TODO: Conversation-Items rendern --}}
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-6 h-6 text-gray-500')
                            </div>
                            <div class="text-sm font-semibold text-gray-900">Keine Conversations</div>
                            <div class="mt-1 text-sm text-gray-500">In diesem Kontext gibt es noch keine Kommunikation.</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Detail / Composer --}}
            <div class="flex-1 min-h-0 flex flex-col">
                @if ($composeMode)
                    <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200">
                        <div class="text-sm font-semibold text-gray-900">Neue Nachricht</div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="backToConversationList"
                                class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10"
                            >
                                @svg('heroicon-o-arrow-left', 'w-4 h-4')
                                Zurück
                            </button>
                            <button
                                type="button"
                                wire:click="sendNewMessage"
                                class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20"
                                title="Nachricht senden"
                            >
                                @svg('heroicon-o-paper-airplane', 'w-4 h-4')
                                <span>Senden</span>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-4">
                        <div class="max-w-3xl space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Empfänger (Telefonnummer)</label>
                                <input
                                    type="text"
                                    wire:model.defer="compose.to"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900/20"
                                    placeholder="+4915123456789, +4915123456790"
                                />
                                <p class="mt-1 text-xs text-gray-500">Mehrere Nummern mit Komma trennen</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Nachricht</label>
                                <textarea
                                    rows="12"
                                    wire:model.defer="compose.message"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900/20"
                                    placeholder="Deine WhatsApp-Nachricht…"
                                ></textarea>
                            </div>

                            @if ($showContextDetails && !empty($context))
                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-700">
                                    <div class="font-semibold mb-2">Kontext wird angehängt</div>
                                    @if (!empty($context['subject']))
                                        <div><strong>Betreff:</strong> {{ $context['subject'] }}</div>
                                    @endif
                                    @if (!empty($context['description']))
                                        <div class="mt-1"><strong>Beschreibung:</strong> {{ $context['description'] }}</div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="sticky bottom-0 border-t border-gray-200 bg-white/95 backdrop-blur px-4 py-3">
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="cancelCompose" class="rounded-md border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-900/10">
                                Abbrechen
                            </button>
                            <button type="button" wire:click="sendNewMessage" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-900/20">
                                Senden
                            </button>
                        </div>
                    </div>
                @elseif ($activeConversationId)
                    {{-- TODO: Conversation-Detail-Ansicht --}}
                    <div class="flex-1 min-h-0 flex items-center justify-center bg-gray-50">
                        <div class="text-center px-6">
                            <div class="text-sm font-semibold text-gray-900">Conversation-Detail</div>
                            <div class="mt-1 text-sm text-gray-500">Wird noch implementiert.</div>
                        </div>
                    </div>
                @else
                    <div class="flex-1 min-h-0 flex items-center justify-center bg-gray-50">
                        <div class="text-center px-6">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-white border border-gray-200">
                                @svg('heroicon-o-chat-bubble-left-right', 'w-7 h-7 text-gray-600')
                            </div>
                            <div class="text-sm font-semibold text-gray-900">Conversation auswählen</div>
                            <div class="mt-1 text-sm text-gray-500">Links eine Conversation auswählen oder eine neue Nachricht starten.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="flex-1 min-h-0 flex items-center justify-center bg-gray-50">
            <div class="text-center px-6">
                <div class="text-sm font-semibold text-gray-900">Dieses Konto wird hier nicht verwaltet.</div>
                <div class="mt-1 text-sm text-gray-500">Bitte nutze „Kanäle verwalten" im Comms-Modal (Board/Project).</div>
            </div>
        </div>
    @endif
</div>

