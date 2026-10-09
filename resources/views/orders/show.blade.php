<x-app-layout>
    @php
        $euro = fn ($amount) => \App\Models\Article::euro($amount);
        $discount = $order->discount();
        $listTotal = $order->listTotal();
    @endphp

    <x-slot name="header">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Zurück</a>
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bestellung von {{ $order->buyer_name }}</h2>
            <x-sale-badge :article="$first" />
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-undo-flash />

            <section class="overflow-hidden rounded-lg bg-white shadow-sm">
                <ul class="divide-y divide-gray-100">
                    @foreach ($order->articles as $article)
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <a href="{{ route('articles.show', $article) }}" class="flex min-w-0 flex-1 items-center gap-3">
                                <img src="{{ $article->imageUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-md bg-gray-100 object-cover">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-gray-900">{{ $article->displayTitle() }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $article->brand }} · Gr. {{ $article->size }} · {{ $article->category->name }}</span>
                                </span>
                            </a>
                            <span class="shrink-0 text-right text-sm tabular-nums">
                                <span class="block font-semibold text-gray-900">{{ $article->formattedSalePrice() ?? '–' }}</span>
                                @if ($article->price !== null && (float) $article->price > 0)
                                    <span class="block text-xs text-gray-500">statt {{ $article->formattedPrice() }}</span>
                                @endif
                            </span>
                            @if ($order->articles->count() > 1)
                                <form method="POST" action="{{ route('orders.articles.remove', [$order, $article]) }}"
                                      onsubmit="return confirm('Artikel aus der Bestellung nehmen? Er bleibt verkauft.')" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="Aus der Bestellung nehmen" aria-label="Aus der Bestellung nehmen">✕</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <dl class="grid grid-cols-[1fr,auto] gap-x-6 gap-y-1 border-t border-gray-100 bg-gray-50 px-4 py-3 text-sm tabular-nums">
                    @if ($listTotal > 0)
                        <dt class="text-gray-500">Angebotspreise zusammen</dt>
                        <dd class="text-right text-gray-700">{{ $euro($listTotal) }}</dd>
                    @endif
                    <dt class="text-gray-500">Gezahlter Preis</dt>
                    <dd class="text-right font-semibold text-gray-900">{{ $euro($order->saleTotal()) }}</dd>
                    @if ($discount)
                        <dt class="text-gray-500">Rabatt</dt>
                        <dd @class(['text-right', 'text-rose-600' => $discount > 0, 'text-green-600' => $discount < 0])>
                            {{ $discount > 0 ? '−' : '+' }}{{ $euro(abs($discount)) }} ({{ number_format(abs($discount) / $listTotal * 100, 0, ',', '.') }} %)
                        </dd>
                    @endif
                    <dt class="text-gray-500">{{ $first->pickup ? 'Selbstabholung' : 'Versand' }}</dt>
                    <dd class="text-right text-gray-700">{{ $first->pickup ? '–' : ($order->shippingTotal() > 0 ? $euro($order->shippingTotal()) : 'nicht erfasst') }}</dd>
                    <dt class="border-t border-gray-200 pt-1 font-medium text-gray-900">Gesamt</dt>
                    <dd class="border-t border-gray-200 pt-1 text-right font-semibold text-gray-900">{{ $euro($order->amountDue()) }}</dd>
                </dl>
            </section>

            <form method="POST" action="{{ route('orders.update', $order) }}" class="space-y-4 rounded-lg bg-white p-4 shadow-sm sm:p-6"
                  x-data="{ pickup: @js((bool) old('pickup', $first->pickup)), paid: @js((bool) old('paid', $first->paid)), shipped: @js((bool) old('shipped', $first->shipped)), picked_up: @js((bool) old('picked_up', $first->picked_up)) }">
                @csrf
                @method('PUT')
                @if ($errors->any())
                    <div class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="buyer_name" value="An wen" />
                        <x-text-input id="buyer_name" name="buyer_name" type="text" required class="mt-1 block w-full" :value="old('buyer_name', $order->buyer_name)" />
                    </div>
                    <div>
                        <x-input-label for="total" value="Gesamtpreis neu verteilen (optional)" />
                        <x-text-input id="total" name="total" type="text" inputmode="decimal" class="mt-1 block w-full" :placeholder="str_replace('.', ',', number_format($order->saleTotal(), 2, '.', ''))" />
                        <p class="mt-1 text-xs text-gray-500">Wird anteilig nach Angebotspreis auf die Artikel verteilt.</p>
                    </div>
                    <div class="sm:col-span-2" x-show="! pickup">
                        <x-input-label for="buyer_address" value="Adresse" />
                        <textarea id="buyer_address" name="buyer_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('buyer_address', $first->buyer_address) }}</textarea>
                    </div>
                    <div x-show="! pickup">
                        <x-input-label for="shipping_cost" value="Versandkosten gesamt in €" />
                        <x-text-input id="shipping_cost" name="shipping_cost" type="text" inputmode="decimal" placeholder="0,00" class="mt-1 block w-full"
                                      :value="old('shipping_cost', $order->shippingTotal() > 0 ? str_replace('.', ',', number_format($order->shippingTotal(), 2, '.', '')) : null)" />
                    </div>
                    <div x-show="shipped && ! pickup" x-cloak>
                        <x-input-label for="tracking_code" value="DHL-Sendungsnummer (optional)" />
                        <x-text-input id="tracking_code" name="tracking_code" type="text" autocapitalize="characters" class="mt-1 block w-full font-mono" :value="old('tracking_code', $first->tracking_code)" />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3 border-t border-gray-100 pt-4">
                    <x-toggle name="paid" label="Bezahlt" model="paid" />
                    <x-toggle name="pickup" label="Selbstabholung" model="pickup" />
                    <div x-show="! pickup"><x-toggle name="shipped" label="Versendet" model="shipped" /></div>
                    <div x-show="pickup" x-cloak><x-toggle name="picked_up" label="Abgeholt" model="picked_up" /></div>
                    <x-primary-button class="ms-auto">Speichern</x-primary-button>
                </div>
            </form>

            <form method="POST" action="{{ route('orders.destroy', $order) }}" onsubmit="return confirm('Bestellung auflösen? Die Artikel bleiben verkauft.')" class="text-right">
                @csrf
                @method('DELETE')
                <button class="text-sm text-gray-500 underline hover:text-gray-800">Bestellung auflösen</button>
            </form>
        </div>
    </div>
</x-app-layout>
