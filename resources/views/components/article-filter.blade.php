@props(['filter', 'category', 'withSoldToggle' => true])

@php
    $groups = [
        'size' => ['Größe', \App\Support\ArticleFilter::options($category, 'size'), $filter->sizes],
        'brand' => ['Marke', \App\Support\ArticleFilter::options($category, 'brand'), $filter->brands],
    ];
    $groups = array_filter($groups, fn ($group) => count($group[1]) > 1);
    $selectedCount = count($filter->sizes) + count($filter->brands);
@endphp

@if ($groups || $withSoldToggle)
    <form method="GET" action="{{ url()->current() }}" {{ $attributes->merge(['class' => 'group/filter rounded-lg bg-white p-4 shadow-sm']) }}>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            @if ($withSoldToggle)
                <label class="inline-flex cursor-pointer select-none items-center gap-3">
                    <input type="checkbox" name="hide_sold" value="1" class="peer sr-only" onchange="this.form.submit()" @checked($filter->hideSold)>
                    <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-gray-800 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500 peer-focus-visible:ring-offset-2"></span>
                    <span class="text-sm font-medium text-gray-700">Verkaufte ausblenden</span>
                </label>
            @endif

            <div class="ms-auto flex items-center gap-3">
                @if ($filter->isActive())
                    <a href="{{ url()->current() }}" class="text-sm text-gray-500 underline hover:text-gray-700">Zurücksetzen</a>
                @endif
                @if ($groups)
                    {{-- Ohne name: wird nicht mitgesendet, steuert nur das Aufklappen. --}}
                    <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-gray-400 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gray-500">
                        <input type="checkbox" class="filter-open sr-only" @checked($selectedCount > 0)>
                        Filter
                        @if ($selectedCount > 0)
                            <span class="inline-block min-w-[1.25rem] rounded-full bg-gray-800 px-1.5 text-center text-xs leading-5 text-white">{{ $selectedCount }}</span>
                        @endif
                        <svg class="h-4 w-4 text-gray-500 transition-transform group-has-[.filter-open:checked]/filter:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </label>
                @endif
            </div>
        </div>

        @if ($groups)
            <div class="mt-3 hidden space-y-3 border-t border-gray-100 pt-3 group-has-[.filter-open:checked]/filter:block">
                @foreach ($groups as $name => [$label, $options, $selected])
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-14 shrink-0 text-sm font-medium text-gray-700">{{ $label }}</span>
                        @foreach ($options as $option => $count)
                            @php($option = (string) $option)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="peer sr-only"
                                       onchange="this.form.submit()" @checked(in_array($option, $selected, true))>
                                <span class="inline-block rounded-full border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 transition hover:border-gray-400 peer-checked:border-gray-800 peer-checked:bg-gray-800 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500">{{ $option }}<span @class(['ml-1.5 inline-block min-w-[1.25rem] rounded-full px-1.5 text-center text-xs leading-5', 'bg-gray-100 text-gray-500' => ! in_array($option, $selected, true), 'bg-white/20 text-white' => in_array($option, $selected, true)])>{{ $count }}</span></span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <noscript><button class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm text-white">Filtern</button></noscript>
    </form>
@endif
