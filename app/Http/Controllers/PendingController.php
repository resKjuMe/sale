<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PendingController extends Controller
{
    public const UNDO_SECONDS = 30;

    // Puffer für Seitenaufbau und Klickweg.
    private const UNDO_GRACE = 5;

    private const LABELS = ['paid' => 'bezahlt', 'shipped' => 'versendet', 'picked_up' => 'abgeholt'];

    // mark: Schnell-Button je Zeile; ohne mark führen die Zeilen in die Bearbeitung, um Angaben nachzutragen.
    public const TYPES = [
        'zahlung' => ['scopes' => ['paymentPending'], 'mark' => 'paid', 'title' => 'Zahlung ausstehend', 'empty' => 'Alle Verkäufe sind bezahlt.'],
        'versand' => ['scopes' => ['shippingPending'], 'mark' => 'shipped', 'title' => 'Versand/Abholung ausstehend', 'empty' => 'Nichts zu verschicken oder abzuholen.'],
        'ohne-preis' => ['scopes' => ['sold', 'withoutPrice'], 'title' => 'Verkäufe ohne Preis', 'hint' => 'Fehlen im Umsatz – Verkaufs- oder Angebotspreis eintragen.'],
        'ohne-preise' => ['scopes' => ['sold', 'withoutBothPrices'], 'title' => 'Verkäufe ohne beide Preise', 'hint' => 'Fehlen im Ø Rabatt – Angebots- und Verkaufspreis eintragen.'],
        'zahlung-ohne-preis' => ['scopes' => ['paymentPending', 'withoutPrice'], 'title' => 'Offene Zahlungen ohne Preis', 'hint' => 'Fehlen in „Noch offen" – Verkaufs- oder Angebotspreis eintragen.'],
        'ohne-versandkosten' => ['scopes' => ['paymentPending', 'withoutShippingCost'], 'title' => 'Offene Zahlungen ohne Versandkosten', 'hint' => 'Fehlen in „Noch offen" – Versandkosten eintragen oder Selbstabholung wählen.'],
    ];

    public static function query(string $type): Builder
    {
        return array_reduce(self::TYPES[$type]['scopes'], fn (Builder $query, string $scope) => $query->{$scope}(), Article::query());
    }

    public function index(string $type): View
    {
        $config = self::TYPES[$type];
        $articles = self::query($type)->with(['category', 'bundle.articles'])->orderBy('sold_at')->get();

        return view('pending.index', [
            'type' => $type,
            'title' => $config['title'],
            'hint' => $config['hint'] ?? null,
            'mark' => $config['mark'] ?? null,
            'empty' => $config['empty'] ?? 'Hier fehlt nichts mehr.',
            'articles' => $articles,
            'sum' => $articles->sum(fn (Article $article) => $article->amountDue()),
        ]);
    }

    public function mark(Article $article, string $flag): RedirectResponse
    {
        abort_unless($article->sold, 422, 'Der Artikel ist nicht verkauft.');
        abort_if($flag !== 'paid' && ($flag === 'picked_up') !== $article->pickup, 422, 'Passt nicht zur Übergabeart.');
        $article->update([$flag => true]);
        $others = $article->bundle_id ? Article::where('bundle_id', $article->bundle_id)->count() - 1 : 0;
        $subject = $others ? "Bestellung ({$article->displayTitle()} und {$others} weitere)" : "„{$article->displayTitle()}“";

        return back()
            ->with('status', "$subject als ".self::LABELS[$flag].' markiert.')
            ->with('undo', ['url' => route('articles.unmark', [$article, $flag]), 'expires' => now()->addSeconds(self::UNDO_SECONDS)->getTimestamp()]);
    }

    public function unmark(Article $article, string $flag): RedirectResponse
    {
        $markedAt = $article->{"{$flag}_at"};

        if (! $article->{$flag} || $markedAt === null || $markedAt->lt(now()->subSeconds(self::UNDO_SECONDS + self::UNDO_GRACE))) {
            return back()->with('status', 'Rückgängig ist nur kurz nach dem Markieren möglich – bitte im Artikel ändern.');
        }

        $article->update([$flag => false]);

        return back()->with('status', "Rückgängig: „{$article->displayTitle()}“ ist wieder nicht ".self::LABELS[$flag].'.');
    }
}
