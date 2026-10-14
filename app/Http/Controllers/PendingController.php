<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PendingController extends Controller
{
    public const UNDO_SECONDS = 30;

    // Puffer für Seitenaufbau und Klickweg.
    private const UNDO_GRACE = 5;

    private const LABELS = ['paid' => 'bezahlt', 'shipped' => 'versendet', 'picked_up' => 'abgeholt'];

    private const FIELD_LABELS = ['price' => 'Angebot', 'sale_price' => 'Gezahlt', 'shipping_cost' => 'Versand'];

    // mark: Schnell-Button je Zeile; fill: Beträge direkt in der Liste nachtragen (pickup = Abholung statt Versand).
    public const TYPES = [
        'zahlung' => ['scopes' => ['paymentPending'], 'mark' => 'paid', 'title' => 'Zahlung ausstehend', 'empty' => 'Alle Verkäufe sind bezahlt.'],
        'versand' => ['scopes' => ['shippingPending'], 'mark' => 'shipped', 'title' => 'Versand/Abholung ausstehend', 'empty' => 'Nichts zu verschicken oder abzuholen.'],
        'ohne-preis' => ['scopes' => ['sold', 'withoutPrice'], 'fill' => ['price', 'sale_price'], 'title' => 'Verkäufe ohne Preis', 'hint' => 'Fehlen im Umsatz – Verkaufs- oder Angebotspreis eintragen.'],
        'ohne-preise' => ['scopes' => ['sold', 'withoutBothPrices'], 'fill' => ['price', 'sale_price'], 'title' => 'Verkäufe ohne beide Preise', 'hint' => 'Fehlen im Ø Rabatt – Angebots- und Verkaufspreis eintragen.'],
        'zahlung-ohne-preis' => ['scopes' => ['paymentPending', 'withoutPrice'], 'fill' => ['price', 'sale_price'], 'title' => 'Offene Zahlungen ohne Preis', 'hint' => 'Fehlen in „Noch offen" – Verkaufs- oder Angebotspreis eintragen.'],
        'ohne-versandkosten' => ['scopes' => ['paymentPending', 'withoutShippingCost'], 'fill' => ['shipping_cost'], 'pickup' => true, 'title' => 'Offene Zahlungen ohne Versandkosten', 'hint' => 'Fehlen in „Noch offen" – Versandkosten eintragen oder Selbstabholung wählen.'],
    ];

    public static function query(string $type): Builder
    {
        return array_reduce(self::TYPES[$type]['scopes'], fn (Builder $query, string $scope) => $query->{$scope}(), Article::query());
    }

    public function index(string $type): View
    {
        $config = self::TYPES[$type];
        // Abarbeiten (mark): Älteste zuerst; Nachtragen (fill): Neueste zuerst.
        $articles = self::query($type)->with(['category', 'bundle.articles'])
            ->orderBy('sold_at', isset($config['fill']) ? 'desc' : 'asc')->orderBy('id', isset($config['fill']) ? 'desc' : 'asc')->get();

        return view('pending.index', [
            'type' => $type,
            'title' => $config['title'],
            'hint' => $config['hint'] ?? null,
            'mark' => $config['mark'] ?? null,
            'fill' => $config['fill'] ?? [],
            'pickup' => $config['pickup'] ?? false,
            'empty' => $config['empty'] ?? 'Hier fehlt nichts mehr.',
            'articles' => $articles,
            'sum' => $articles->sum(fn (Article $article) => $article->amountDue()),
        ]);
    }

    /**
     * Beträge aus der Liste heraus nachtragen; leere Felder bleiben unverändert.
     */
    public function fill(Request $request, string $type, Article $article): JsonResponse|RedirectResponse
    {
        $config = self::TYPES[$type];
        abort_unless($article->sold && isset($config['fill']), 422, 'Hier lässt sich nichts nachtragen.');

        foreach ($config['fill'] as $field) {
            if (is_string($request->input($field))) {
                $value = str_replace(',', '.', trim($request->input($field)));
                $request->merge([$field => $value === '' ? null : $value]);
            }
        }
        $data = array_filter($request->validate(
            array_fill_keys($config['fill'], ['nullable', 'numeric', 'min:0', 'max:99999']),
            [],
            ['price' => 'Angebotspreis', 'sale_price' => 'Gezahlter Preis', 'shipping_cost' => 'Versandkosten'],
        ), fn ($value) => $value !== null);

        if (($config['pickup'] ?? false) && $request->boolean('pickup')) {
            $data = ['pickup' => true, 'shipped' => false, 'tracking_code' => null, 'shipping_cost' => null];
        } elseif ($data === []) {
            throw ValidationException::withMessages([$config['fill'][0] => 'Bitte einen Betrag eintragen.']);
        }

        $article->update($data);
        $summary = isset($data['pickup'])
            ? 'Selbstabholung'
            : collect($config['fill'])->map(fn (string $field) => self::FIELD_LABELS[$field].' '.(Article::euro($article->{$field}) ?? '–'))->join(' · ');
        $missing = self::query($type)->whereKey($article->id)->exists();

        if ($request->expectsJson()) {
            return response()->json(['missing' => $missing, 'summary' => $summary]);
        }

        return back()->with('status', "„{$article->displayTitle()}“: $summary".($missing ? ' – es fehlt noch etwas.' : '.'));
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
