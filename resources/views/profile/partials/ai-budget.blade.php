@php
    use App\Support\AiBudget;

    $tokens = fn (int $count) => $count >= 1_000_000
        ? number_format($count / 1_000_000, 1, ',', '.').' Mio.'
        : number_format($count, 0, ',', '.');
    $remaining = $ai->remaining();
    $modes = ['brand_size' => 'Marke/Größe erkennen', 'title' => 'Titel vorschlagen', 'price' => 'Preis vorschlagen'];
@endphp

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">KI-Guthaben (Claude)</h2>
        <p class="mt-1 text-sm text-gray-600">
            Ein Anthropic-Konto für alle Nutzer. Das Restguthaben lässt sich nicht abfragen: Trag den Stand aus der
            <a href="https://platform.claude.com/settings/billing" target="_blank" rel="noopener" class="underline hover:text-gray-900">Console</a>
            ein, davon wird der hier gezählte Verbrauch abgezogen.
        </p>
    </header>

    @if ($remaining !== null)
        <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
            <div>
                <dt class="text-sm text-gray-500">Verbleibend</dt>
                <dd @class(['text-2xl font-semibold', 'text-red-600' => $remaining < 1_000_000, 'text-gray-900' => $remaining >= 1_000_000])>{{ AiBudget::dollars($remaining) }}</dd>
            </div>
            @if (($remainingTokens = $ai->remainingTokens()) !== null)
                <div>
                    <dt class="text-sm text-gray-500">≈ Tokens</dt>
                    <dd class="text-2xl font-semibold text-gray-900">{{ $tokens($remainingTokens) }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-sm text-gray-500">Seit {{ $ai->credit->created_at->format('d.m.Y') }} verbraucht</dt>
                <dd class="text-2xl font-semibold text-gray-900">{{ AiBudget::dollars($ai->spentSinceCredit) }}</dd>
            </div>
        </dl>

        @php($requests = collect($modes)->map(fn ($label, $mode) => ($count = $ai->remainingRequests($mode)) !== null ? number_format($count, 0, ",", ".")."× ".$label : null)->filter())
        @if ($requests->isNotEmpty())
            <p class="mt-3 text-sm text-gray-600">Reicht noch für ca. {{ $requests->implode(" bzw. ") }}.</p>
        @endif
    @else
        <p class="mt-6 text-sm text-gray-600">Noch kein Guthaben eingetragen.</p>
    @endif

    <p class="mt-3 text-sm text-gray-600">
        Diesen Monat: {{ AiBudget::dollars($ai->month['cost']) }} für {{ number_format($ai->month['requests'], 0, ',', '.') }} Anfragen
        ({{ $tokens($ai->month['input']) }} Tokens Eingabe, {{ $tokens($ai->month['output']) }} Ausgabe)
    </p>
    @if ($ai->unpriced > 0)
        <p class="mt-1 text-sm text-amber-700">{{ $ai->unpriced }} Anfragen mit Modellen ohne hinterlegten Preis sind nicht eingerechnet.</p>
    @endif

    <form method="post" action="{{ route('profile.ai-credit') }}" class="mt-6 flex flex-wrap items-end gap-4">
        @csrf
        @method('put')

        <div>
            <x-input-label for="ai_credit_amount" value="Aktuelles Guthaben laut Console ($)" />
            <x-text-input id="ai_credit_amount" name="amount" type="text" inputmode="decimal" class="mt-1 block w-40" :value="old('amount')" placeholder="z. B. 12,50" required />
            <x-input-error class="mt-2" :messages="$errors->aiCredit->get('amount')" />
        </div>

        <x-primary-button>Übernehmen</x-primary-button>

        @if (session('status') === 'ai-credit-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gray-600">Gespeichert.</p>
        @endif
    </form>
</section>
