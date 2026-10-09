@props(['filter', 'category', 'withStatus' => true])

@php
    $sizes = \App\Support\ArticleFilter::sizeOptions($category);
    $brands = \App\Support\ArticleFilter::brandOptions($category);
@endphp

@if ($sizes->count() > 1 || $brands->count() > 1 || $withStatus)
    <form method="GET" action="{{ url()->current() }}" {{ $attributes->merge(['class' => 'space-y-3 rounded-lg bg-white p-4 shadow-sm']) }}>
        @if ($sizes->count() > 1)
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-14 shrink-0 text-sm font-medium text-gray-700">Größe</span>
                @foreach ($sizes as $size => $count)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="size[]" value="{{ $size }}" class="peer sr-only"
                               onchange="this.form.submit()" @checked(in_array((string) $size, $filter->sizes, true))>
                        <span class="inline-block rounded-full border border-gray-300 bg-white px-3 py-1 text-sm text-gray-700 transition hover:border-gray-400 peer-checked:border-gray-800 peer-checked:bg-gray-800 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-gray-500">
                            {{ $size }} <span class="opacity-60">{{ $count }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            @if ($brands->count() > 1)
                <label class="flex items-center gap-2">
                    <span class="w-14 shrink-0 text-sm font-medium text-gray-700">Marke</span>
                    <select name="brand" onchange="this.form.submit()"
                            class="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">Alle</option>
                        @foreach ($brands as $brand => $count)
                            <option value="{{ $brand }}" @selected($filter->brand === (string) $brand)>{{ $brand }} ({{ $count }})</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($withStatus)
                <label class="flex items-center gap-2">
                    <span class="w-14 shrink-0 text-sm font-medium text-gray-700">Status</span>
                    <select name="status" onchange="this.form.submit()"
                            class="rounded-md border-gray-300 py-1.5 text-sm shadow-sm focus:border-gray-500 focus:ring-gray-500">
                        <option value="">Alle</option>
                        @foreach (\App\Support\ArticleFilter::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($filter->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <noscript><button class="rounded-md bg-gray-800 px-3 py-1.5 text-sm text-white">Filtern</button></noscript>
            @if ($filter->isActive())
                <a href="{{ url()->current() }}"
                   class="text-sm text-gray-500 underline hover:text-gray-700">Filter zurücksetzen</a>
            @endif
        </div>
    </form>
@endif
