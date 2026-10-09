@props(['filter', 'articles', 'withSoldToggle' => true, 'withCategories' => false, 'withPending' => false])

@php
    // Jede Gruppe zählt unter allen übrigen Filtern; gewählte Werte bleiben zum Abwählen sichtbar.
    $facet = function (string $key, array $selected, ?callable $label = null) use ($filter, $articles) {
        $counts = $filter->facet($articles, $key) + array_fill_keys($selected, 0);
        $options = collect($counts)->sortKeysUsing(fn ($a, $b) => strnatcasecmp((string) $a, (string) $b))->map(fn ($count, $value) => [(string) $value, $label ? $label((string) $value) : (string) $value, $count]);

        return $label ? $options->sortBy(fn ($option) => mb_strtolower($option[1]))->values()->all() : $options->values()->all();
    };
    $categoryNames = $withCategories ? \App\Models\Category::pluck('name', 'id')->all() : [];
    $groups = array_filter([
        'category' => $withCategories
            ? ['Kategorie', $facet('category', array_map('strval', $filter->categories), fn ($id) => $categoryNames[$id] ?? '?'), array_map('strval', $filter->categories)]
            : null,
        'size' => ['Größe', $facet('size', $filter->sizes), $filter->sizes],
        'brand' => ['Marke', $facet('brand', $filter->brands), $filter->brands],
    ], fn ($group) => $group !== null && (count($group[1]) > 1 || $group[2] !== []));
    $selectedCount = count($filter->sizes) + count($filter->brands) + count($filter->categories);
    $pending = $withPending
        ? array_filter($filter->pendingCounts($articles), fn ($count, $value) => $count > 0 || in_array($value, $filter->pending, true), ARRAY_FILTER_USE_BOTH)
        : [];
@endphp

@if ($groups || $withSoldToggle)
    <form method="GET" action="{{ url()->current() }}" data-live-filter {{ $attributes->merge(['class' => 'group/filter rounded-lg bg-white p-4 shadow-sm']) }}>
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            @if ($withSoldToggle)
                <label class="inline-flex cursor-pointer select-none items-center gap-3">
                    <input type="checkbox" name="hide_sold" value="1" class="peer sr-only" @checked($filter->hideSold)>
                    <span class="relative h-6 w-11 shrink-0 rounded-full bg-gray-300 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-gray-800 peer-checked:after:translate-x-5 peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500 peer-focus-visible:ring-offset-2"></span>
                    <span class="text-sm font-medium text-gray-700">Verkaufte ausblenden</span>
                </label>
            @endif

            @if ($withPending)
                <div class="flex flex-wrap items-center gap-2" data-live-target="filter-pending">
                    @foreach ($pending as $value => $count)
                        <label class="cursor-pointer">
                            <input type="checkbox" name="pending[]" value="{{ $value }}" class="peer sr-only" @checked(in_array($value, $filter->pending, true))>
                            <span class="inline-block rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-sm text-amber-800 transition hover:border-amber-400 peer-checked:border-gray-800 peer-checked:bg-gray-800 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500">{{ \App\Support\ArticleFilter::PENDING[$value] }}<span class="ml-1.5 inline-block min-w-[1.25rem] rounded-full bg-white/70 px-1.5 text-center text-xs leading-5 [.peer:checked~*_&]:bg-white/20">{{ $count }}</span></span>
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="ms-auto flex items-center gap-3">
                <span data-live-target="filter-reset">
                    @if ($filter->isActive())
                        <a href="{{ url()->current() }}" data-live-reset class="text-sm text-gray-500 underline hover:text-gray-700">Zurücksetzen</a>
                    @endif
                </span>
                @if ($groups || $selectedCount > 0)
                    {{-- Ohne name: wird nicht mitgesendet, steuert nur das Aufklappen. --}}
                    <label class="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:border-gray-400 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-gray-500">
                        <input type="checkbox" class="filter-open sr-only" @checked($selectedCount > 0)>
                        Filter
                        <span data-live-target="filter-badge" class="contents">
                            @if ($selectedCount > 0)
                                <span class="inline-block min-w-[1.25rem] rounded-full bg-gray-800 px-1.5 text-center text-xs leading-5 text-white">{{ $selectedCount }}</span>
                            @endif
                        </span>
                        <svg class="h-4 w-4 text-gray-500 transition-transform group-has-[.filter-open:checked]/filter:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </label>
                @endif
            </div>
        </div>

        @if ($groups || $selectedCount > 0)
            <div class="mt-3 hidden space-y-3 border-t border-gray-100 pt-3 group-has-[.filter-open:checked]/filter:block" data-live-target="filter-panel">
                @foreach ($groups as $name => [$label, $options, $selected])
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="w-14 shrink-0 text-sm font-medium text-gray-700">{{ $label }}</span>
                        @foreach ($options as [$value, $option, $count])
                            <label class="cursor-pointer">
                                <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" class="peer sr-only"
                                       @checked(in_array($value, $selected, true))>
                                <span class="inline-block rounded-full border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 transition hover:border-gray-400 peer-checked:border-gray-800 peer-checked:bg-gray-800 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500">{{ $option }}<span class="ml-1.5 inline-block min-w-[1.25rem] rounded-full bg-gray-100 px-1.5 text-center text-xs leading-5 text-gray-500 [.peer:checked~*_&]:bg-white/20 [.peer:checked~*_&]:text-white">{{ $count }}</span></span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <noscript><button class="mt-3 rounded-md bg-gray-800 px-3 py-1.5 text-sm text-white">Filtern</button></noscript>
    </form>
@endif
