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
        // Ohne erfassten Verkaufspreis zählt der Angebotspreis.
        $amount = DB::raw('sum(coalesce(sale_price, price, 0)) as total');

        return view('dashboard', [
            'stats' => [
                'total' => Article::count(),
                'available' => Article::where('sold', false)->count(),
                'sold' => Article::where('sold', true)->count(),
                'revenue' => (float) Article::where('sold', true)->select($amount)->value('total'),
                'openAmount' => (float) Article::paymentPending()->select($amount)->value('total'),
                'soldThisMonth' => Article::where('sold', true)->where('sold_at', '>=', now()->startOfMonth())->count(),
            ],
            'paymentPending' => Article::paymentPending()->with('category')->orderBy('sold_at')->limit(self::LIST_LIMIT)->get(),
            'paymentPendingCount' => Article::paymentPending()->count(),
            'shippingPending' => Article::shippingPending()->with('category')->orderBy('sold_at')->limit(self::LIST_LIMIT)->get(),
            'shippingPendingCount' => Article::shippingPending()->count(),
            'recentlySold' => Article::where('sold', true)->with('category')->latest('sold_at')->limit(self::LIST_LIMIT)->get(),
            'categories' => Category::withCount(['articles', 'articles as available_count' => fn ($query) => $query->where('sold', false)])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
