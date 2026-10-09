<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Bundle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Bundle $bundle): View
    {
        $bundle->load('articles.category');

        return view('orders.show', ['order' => $bundle, 'first' => $bundle->articles->first()]);
    }

    public function update(Request $request, Bundle $bundle): RedirectResponse
    {
        foreach (['total', 'shipping_cost'] as $key) {
            if (is_string($request->input($key))) {
                $request->merge([$key => str_replace(',', '.', trim($request->input($key))) ?: null]);
            }
        }
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],
            'total' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'tracking_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\s]+$/'],
        ], [], ['buyer_name' => 'Käufer', 'total' => 'Gesamtpreis', 'shipping_cost' => 'Versandkosten', 'tracking_code' => 'Sendungsnummer']);

        $articles = $bundle->articles;
        $pickup = $request->boolean('pickup');
        $shipped = ! $pickup && $request->boolean('shipped');
        $shares = isset($data['total']) ? Bundle::distribute($articles, (float) $data['total']) : [];

        DB::transaction(function () use ($articles, $bundle, $data, $pickup, $shipped, $shares, $request) {
            $bundle->update(['buyer_name' => $data['buyer_name']]);
            foreach ($articles->values() as $index => $article) {
                $article->update([
                    'buyer_name' => $data['buyer_name'],
                    'buyer_address' => $data['buyer_address'] ?? null,
                    'sale_price' => $shares[$article->id] ?? $article->sale_price,
                    'shipping_cost' => $pickup ? null : ($index === 0 ? ($data['shipping_cost'] ?? null) : 0),
                    'pickup' => $pickup,
                    'paid' => $request->boolean('paid'),
                    'shipped' => $shipped,
                    'picked_up' => $pickup && $request->boolean('picked_up'),
                    'tracking_code' => $shipped && filled($data['tracking_code'] ?? null) ? preg_replace('/\s+/', '', $data['tracking_code']) : null,
                ]);
            }
        });

        return redirect()->route('orders.show', $bundle)->with('status', 'Bestellung gespeichert.');
    }

    // Artikel bleibt verkauft, gehört aber nicht mehr zur Bestellung.
    public function removeArticle(Bundle $bundle, Article $article): RedirectResponse
    {
        abort_unless($article->bundle_id === $bundle->id, 404);

        DB::transaction(function () use ($bundle, $article) {
            $shipping = (float) $article->shipping_cost;
            $article->update(['bundle_id' => null, 'shipping_cost' => null]);
            $rest = $bundle->articles()->get();
            if ($shipping > 0 && $rest->isNotEmpty()) {
                $rest->first()->update(['shipping_cost' => $shipping]);
            }
        });

        return $bundle->articles()->exists()
            ? redirect()->route('orders.show', $bundle)->with('status', "„{$article->displayTitle()}“ aus der Bestellung genommen.")
            : redirect()->route('articles.show', $article)->with('status', 'Bestellung aufgelöst.');
    }

    public function destroy(Bundle $bundle): RedirectResponse
    {
        $first = $bundle->articles->first();
        $bundle->articles->each->update(['bundle_id' => null]);

        return redirect()->route('articles.show', $first)->with('status', 'Bestellung aufgelöst – die Artikel bleiben verkauft.');
    }
}
