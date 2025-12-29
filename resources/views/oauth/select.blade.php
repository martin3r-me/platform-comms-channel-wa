<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Phone Number auswählen</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    WhatsApp Phone Number auswählen
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Wähle eine Phone Number aus, die du mit deinem Account verbinden möchtest.
                </p>
            </div>

            <form action="{{ route('whatsapp.oauth.create-account') }}" method="POST" class="mt-8 space-y-6">
                @csrf

                <div class="space-y-4">
                    @forelse($phoneNumbers as $phone)
                        <label class="relative flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                            <input
                                type="radio"
                                name="phone_number_id"
                                value="{{ $phone['id'] }}"
                                class="mt-1 h-4 w-4 text-gray-900 focus:ring-gray-900 border-gray-300"
                                required
                            >
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $phone['display_phone_number'] ?? $phone['phone_number'] ?? 'Unbekannt' }}
                                        </div>
                                        @if($phone['phone_number'] && $phone['phone_number'] !== ($phone['display_phone_number'] ?? ''))
                                            <div class="text-xs text-gray-400 mt-1">
                                                {{ $phone['phone_number'] }}
                                            </div>
                                        @endif
                                        @if($phone['verified_name'])
                                            <div class="text-xs text-gray-500 mt-1">
                                                Verifiziert: {{ $phone['verified_name'] }}
                                            </div>
                                        @endif
                                        @if(isset($phone['quality_rating']))
                                            <div class="text-xs text-gray-500 mt-1">
                                                Qualität: {{ $phone['quality_rating'] }}
                                            </div>
                                        @endif
                                    </div>
                                    @if(($phone['code_verification_status'] ?? null) === 'VERIFIED')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Verifiziert
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            Keine Phone Numbers gefunden.
                        </div>
                    @endforelse
                </div>

                @if(count($phoneNumbers) > 0)
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">
                            Name (optional)
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-gray-900 focus:ring-gray-900/20"
                            placeholder="z.B. Support, Sales, etc."
                        >
                    </div>

                    <div class="flex items-center justify-between">
                        <a
                            href="{{ route('whatsapp.oauth.error') }}"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Abbrechen
                        </a>
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900"
                        >
                            Account verbinden
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</body>
</html>

