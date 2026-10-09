<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const LIST_LIMIT = 5;

    public function __invoke(): View
    {
        // 0 € gilt als nicht erfasst; ohne Verkaufspreis zählt der Angebotspreis.
        $price = 'coalesce(nullif(sale_price, 0), nullif(price, 0), 0)';
        $amount = DB::raw("sum($price) as total");

        return view('dashboard', [
            'stats' => [
                'total' => Article::count(),
                'available' => Article::where('sold', false)->count(),
                'sold' => Article::where('sold', true)->count(),
                'revenue' => (float) Article::where('sold', true)->select($amount)->value('total'),
                'openAmount' => (float) Article::paymentPending()->select(DB::raw("sum($price + coalesce(shipping_cost, 0)) as total"))->value('total'),
                'discount' => $this->averageDiscount(),
                'soldThisMonth' => Article::where('sold', true)->where('sold_at', '>=', now()->startOfMonth())->count(),
            ],
            // Was in den Summen fehlt, weil Angaben nicht erfasst sind.
            'missing' => collect(['ohne-preis', 'ohne-preise', 'zahlung-ohne-preis', 'ohne-versandkosten'])
                ->mapWithKeys(fn (string $type) => [$type => PendingController::query($type)->count()])
                ->all(),
            'paymentPending' => Article::paymentPending()->with(['category', 'bundle.articles'])->orderBy('sold_at')->limit(self::LIST_LIMIT)->get(),
            'paymentPendingCount' => Article::paymentPending()->count(),
            'shippingPending' => Article::shippingPending()->with(['category', 'bundle.articles'])->orderBy('sold_at')->limit(self::LIST_LIMIT)->get(),
            'shippingPendingCount' => Article::shippingPending()->count(),
            'recentlySold' => Article::where('sold', true)->with(['category', 'bundle.articles'])->latest('sold_at')->limit(self::LIST_LIMIT)->get(),
            'categories' => Category::withCount(['articles', 'articles as available_count' => fn ($query) => $query->where('sold', false)])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Mittlerer Nachlass vom Angebots- zum Verkaufspreis, nur über Verkäufe mit beiden Preisen.
     *
     * @return array{percent: float, amount: float, count: int}|null
     */
    private function averageDiscount(): ?array
    {
        $row = Article::where('sold', true)->where('sale_price', '>', 0)->where('price', '>', 0)
            ->selectRaw('count(*) as total, avg((price - sale_price) / price) as ratio, avg(price - sale_price) as amount')
            ->toBase()
            ->first();

        return $row->total > 0
            ? ['percent' => round(100 * (float) $row->ratio), 'amount' => (float) $row->amount, 'count' => (int) $row->total]
            : null;
    }
}
