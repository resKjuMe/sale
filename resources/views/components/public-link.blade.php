@props(['url', 'regenerate'])

<div class="mb-6 flex flex-wrap items-center gap-2 bg-white p-4 shadow-sm sm:rounded-lg"
     x-data="{ copied: false, copy() { navigator.clipboard.writeText(this.$refs.url.value).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000) }) } }">
    <span class="text-sm font-medium text-gray-700">Öffentlicher Link:</span>
    <input x-ref="url" type="text" readonly value="{{ $url }}" x-on:focus="$el.select()"
           class="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 text-sm text-gray-700 shadow-sm">
    <x-secondary-button type="button" x-on:click="copy()">
        <span x-text="copied ? 'Kopiert ✓' : 'Kopieren'">Kopieren</span>
    </x-secondary-button>
    <a href="{{ $url }}" target="_blank">
        <x-secondary-button type="button">Öffnen</x-secondary-button>
    </a>
    <form method="POST" action="{{ $regenerate }}"
          onsubmit="return confirm('Neuen Link erzeugen? Der bisherige Link funktioniert danach nicht mehr.')">
        @csrf
        <button class="px-2 text-sm text-gray-500 underline hover:text-gray-700">Neu erzeugen</button>
    </form>
</div>
