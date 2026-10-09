<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Bundle;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BatchArticleController extends Controller
{
    public function edit(Request $request): View
    {
        $articles = $this->selected($request);

        return view('articles.batch', [
            'articles' => $articles,
            'categories' => Category::orderBy('name')->get(),
            'back' => $request->input('back', url()->previous()),
            'bundleable' => $articles->every(fn (Article $article) => $article->bundle_id === null),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(collect(['value', 'shipping_cost', 'total'])->mapWithKeys(fn ($key) => [$key => self::decimal($request->input($key))])->all());
        $request->merge(['sale_price' => array_map(self::decimal(...), (array) $request->input('sale_price', []))]);
        $articles = $this->selected($request);
        $back = $request->input('back', route('articles.index'));

        $status = match ($request->validate(['action' => ['required', Rule::in(['category', 'price', 'bundle'])]])['action']) {
            'category' => $this->moveToCategory($request, $articles),
            'price' => $this->changePrice($request, $articles),
            'bundle' => $this->createBundle($request, $articles),
        };

        if ($request->input('action') === 'bundle') {
            return redirect()->route('orders.show', $articles->first()->fresh()->bundle_id)->with('status', $status);
        }

        return redirect()->to($back)->with('status', $status);
    }

    private function moveToCategory(Request $request, Collection $articles): string
    {
        $category = Category::findOrFail($request->validate(['category_id' => ['required', 'integer', 'exists:categories,id']])['category_id']);
        $articles->each(fn (Article $article) => $article->forceFill(['category_id' => $category->id])->save());

        return self::count($articles->count())." nach „{$category->name}“ verschoben.";
    }

    private function changePrice(Request $request, Collection $articles): string
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['set', 'percent', 'amount'])],
            'value' => ['required', 'numeric', 'gt:0', $request->input('mode') === 'percent' ? 'lt:100' : 'max:99999'],
        ], [], ['value' => 'Wert']);
        $round = $request->boolean('round');

        // Verkaufte bleiben unberührt; Senkungen brauchen einen bisherigen Preis.
        $changed = $articles
            ->reject(fn (Article $article) => $article->sold || ($data['mode'] !== 'set' && (float) $article->price <= 0))
            ->each(function (Article $article) use ($data, $round) {
                $price = match ($data['mode']) {
                    'set' => (float) $data['value'],
                    'percent' => (float) $article->price * (1 - $data['value'] / 100),
                    'amount' => (float) $article->price - $data['value'],
                };
                $price = $round ? round($price * 2) / 2 : round($price, 2);
                $article->update(['price' => max($round ? 0.5 : 0.01, $price)]);
            });

        $skipped = $articles->count() - $changed->count();

        return 'Preis bei '.self::count($changed->count()).' geändert.'.($skipped ? ' '.self::count($skipped).' übersprungen (verkauft oder ohne Preis).' : '');
    }

    private function createBundle(Request $request, Collection $articles): string
    {
        if ($articles->contains(fn (Article $article) => $article->bundle_id !== null)) {
            throw ValidationException::withMessages(['ids' => 'Mindestens ein Artikel gehört schon zu einer Bestellung.']);
        }

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],
            'price_mode' => ['nullable', Rule::in(['total', 'each'])],
            'total' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'sale_price' => ['array'],
            'sale_price.*' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ], [], ['buyer_name' => 'Käufer', 'total' => 'Gesamtpreis', 'sale_price.*' => 'Verkaufspreis', 'shipping_cost' => 'Versandkosten']);
        $pickup = $request->boolean('pickup');
        // Konvolut: Gesamtpreis anteilig verteilen, damit Rabatt je Artikel stimmt.
        $shares = ($data['price_mode'] ?? 'total') === 'total' && isset($data['total'])
            ? Bundle::distribute($articles, (float) $data['total'])
            : array_filter($data['sale_price'] ?? [], fn ($price) => $price !== null);

        $bundle = DB::transaction(function () use ($articles, $data, $pickup, $request, $shares) {
            $bundle = Bundle::create(['buyer_name' => $data['buyer_name']]);
            foreach ($articles->values() as $index => $article) {
                $article->update([
                    'sold' => true,
                    'bundle_id' => $bundle->id,
                    'buyer_name' => $data['buyer_name'],
                    'buyer_address' => $data['buyer_address'] ?? null,
                    'sale_price' => $shares[$article->id] ?? $article->sale_price ?? $article->price,
                    // Versand einmal für die ganze Bestellung, beim ersten Artikel.
                    'shipping_cost' => $pickup ? null : ($index === 0 ? ($data['shipping_cost'] ?? null) : 0),
                    'pickup' => $pickup,
                    'paid' => $request->boolean('paid'),
                ]);
            }

            return $bundle;
        });

        return 'Bestellung von '.$data['buyer_name'].' mit '.self::count($articles->count()).' angelegt.';
    }

    private function selected(Request $request): Collection
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Bitte mindestens einen Artikel auswählen.'])['ids'];

        return Article::with('category')->whereIn('id', $ids)->orderBy('id')->get();
    }

    private static function count(int $n): string
    {
        return $n === 1 ? '1 Artikel' : "$n Artikel";
    }

    private static function decimal(mixed $value): mixed
    {
        return is_string($value) ? (str_replace(',', '.', trim($value)) ?: null) : $value;
    }
}
