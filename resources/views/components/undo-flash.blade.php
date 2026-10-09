@php($undo = session('undo'))
@php($left = $undo ? max(0, $undo['expires'] - now()->getTimestamp()) : 0)

@if (session('status'))
    <div {{ $attributes->class('mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-md bg-green-50 px-4 py-3 text-sm text-green-800') }}
         @if ($left > 0) x-data="{ left: {{ $left }} }" x-init="const timer = setInterval(() => { if (--left <= 0) clearInterval(timer) }, 1000)" @endif>
        <span>{{ session('status') }}</span>
        @if ($left > 0)
            <form method="POST" action="{{ $undo['url'] }}" x-show="left > 0" class="shrink-0">
                @csrf
                @method('PATCH')
                <button class="rounded-md border border-green-700/30 bg-white px-3 py-1 text-xs font-semibold text-green-800 shadow-sm hover:bg-green-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-600">
                    Rückgängig <span class="tabular-nums" x-text="`(${left} s)`">({{ $left }} s)</span>
                </button>
            </form>
        @endif
    </div>
@endif
