<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PendingController extends Controller
{
    public const TYPES = [
        'zahlung' => ['scope' => 'paymentPending', 'flag' => 'paid', 'title' => 'Zahlung ausstehend', 'empty' => 'Alle Verkäufe sind bezahlt.'],
        'versand' => ['scope' => 'shippingPending', 'flag' => 'shipped', 'title' => 'Versand/Abholung ausstehend', 'empty' => 'Nichts zu verschicken oder abzuholen.'],
    ];

    public function index(string $type): View
    {
        $config = self::TYPES[$type];
        $articles = Article::query()->{$config['scope']}()->with('category')->orderBy('sold_at')->get();

        return view('pending.index', [
            'type' => $type,
            'title' => $config['title'],
            'empty' => $config['empty'],
            'articles' => $articles,
            'sum' => $articles->sum(fn (Article $article) => $article->amountDue()),
        ]);
    }

    public function mark(Article $article, string $flag): RedirectResponse
    {
        abort_unless($article->sold, 422, 'Der Artikel ist nicht verkauft.');
        abort_if($flag !== 'paid' && ($flag === 'picked_up') !== $article->pickup, 422, 'Passt nicht zur Übergabeart.');
        $article->update([$flag => true]);

        $label = ['paid' => 'bezahlt', 'shipped' => 'versendet', 'picked_up' => 'abgeholt'][$flag];

        return back()->with('status', "„{$article->displayTitle()}“ als $label markiert.");
    }
}
