<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleRequest;
use App\Models\Article;
use App\Models\Category;
use App\Support\ArticleFilter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(Request $request): View
    {
        $filter = ArticleFilter::fromRequest($request);
        $articles = $filter->apply(Article::query())->with('category')->withCount('images')->when($filter->pending, fn ($query) => $query->orderBy('sold_at'), fn ($query) => $query->latest())->paginate(100)->withQueryString();

        return view('articles.index', ['articles' => $articles, 'filter' => $filter, 'total' => Article::count()]);
    }

    public function print(Request $request): View
    {
        $articles = $this->selection($request);

        return view('categories.print', [
            'title' => 'Auswahl',
            'back' => $this->back($request),
            'articles' => $articles,
            'columns' => min(4, max(2, $request->integer('cols', 3))),
            'columnsUrl' => fn (int $cols) => route('articles.print', ['ids' => $articles->modelKeys(), 'back' => $this->back($request), 'cols' => $cols]),
        ]);
    }

    public function collage(Request $request): View
    {
        if ($request->has('ids')) {
            return view('categories.collage', [
                'articles' => $this->selection($request)->map->toCollageArray(),
                'filter' => null,
                'title' => config('app.name'),
                'back' => [$this->back($request), 'Zurück'],
                'withCategories' => true,
            ]);
        }

        // Verkaufte steuert die Collage-Seite selbst über „Verkaufte einbeziehen".
        $filter = ArticleFilter::fromRequest($request->merge(['hide_sold' => null]));

        return view('categories.collage', [
            'articles' => $filter->apply(Article::query())->oldest()->get()->map->toCollageArray(),
            'filter' => $filter,
            'source' => Article::query(),
            'title' => config('app.name'),
            'back' => [route('articles.index', $filter->query()), 'Alle Artikel'],
            'withCategories' => true,
        ]);
    }

    public function regeneratePublicLink(Request $request): RedirectResponse
    {
        $request->user()->regeneratePublicToken();

        return redirect()->route('articles.index')->with('status', 'Neuer öffentlicher Link erzeugt, der alte ist ungültig.');
    }

    public function create(Category $category): View
    {
        return view('articles.form', ['category' => $category, 'article' => new Article]);
    }

    public function quick(Category $category): View
    {
        return view('articles.quick', ['articleCount' => $category->articles()->count()] + $this->suggestions($category));
    }

    public function bulk(Category $category): View
    {
        return view('articles.bulk', $this->suggestions($category));
    }

    public function store(ArticleRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        $data = $request->articleData();
        $data['image_path'] = $request->file('image')->store('articles', 'public');

        $article = $category->articles()->create($data);
        $this->syncPhotos($request, $article);

        if ($request->expectsJson()) {
            return response()->json(['id' => $article->id], 201);
        }

        if ($request->boolean('quick')) {
            return redirect()->route('categories.articles.quick', $category)
                ->with('status', '„'.$article->displayTitle().'" gespeichert.');
        }

        return redirect()->route('categories.show', $category)->with('status', 'Artikel angelegt.');
    }

    public function show(Article $article): View
    {
        return view('articles.show', compact('article'));
    }

    public function edit(Article $article): View
    {
        return view('articles.form', ['category' => $article->category, 'article' => $article]);
    }

    public function update(ArticleRequest $request, Article $article): RedirectResponse
    {
        $data = $request->articleData();
        $oldPath = null;

        if ($request->hasFile('image')) {
            $oldPath = $article->image_path;
            $data['image_path'] = $request->file('image')->store('articles', 'public');
        }

        $article->update($data);
        $this->syncPhotos($request, $article);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('articles.show', $article)->with('status', 'Artikel gespeichert.');
    }

    public function sold(Request $request, Article $article): RedirectResponse|JsonResponse
    {
        $article->markSold($request->validate(['sold' => ['required', 'boolean']])['sold']);

        if ($request->expectsJson()) {
            return response()->json(['sold' => $article->sold]);
        }

        return back()->with('status', $article->sold ? 'Als verkauft markiert.' : 'Wieder als verfügbar markiert.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $category = $article->category;
        $article->delete();

        return redirect()->route('categories.show', $category)->with('status', 'Artikel gelöscht.');
    }

    private function syncPhotos(ArticleRequest $request, Article $article): void
    {
        $article->images()->whereIn('id', $request->input('remove_photos', []))->get()->each->delete();

        $position = (int) $article->images()->max('position');
        $free = Article::MAX_EXTRA_IMAGES - $article->images()->count();
        foreach (array_slice($request->file('photos', []), 0, max(0, $free)) as $photo) {
            $article->images()->create(['path' => $photo->store('articles', 'public'), 'position' => ++$position]);
        }
    }

    private function suggestions(Category $category): array
    {
        return [
            'category' => $category,
            'brands' => Article::query()->distinct()->orderBy('brand')->pluck('brand'),
            'sizes' => $category->articles()->distinct()->orderBy('size')->pluck('size'),
        ];
    }

    private function selection(Request $request): Collection
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
        ], ['ids.required' => 'Bitte mindestens einen Artikel auswählen.'])['ids'];

        return Article::with('category')->whereIn('id', $ids)->oldest()->get();
    }

    private function back(Request $request): string
    {
        $back = $request->string('back')->toString();

        // Nur eigene Seiten, sonst landet ein fremder oder javascript:-Link im href.
        return str_starts_with($back, url('/').'/') ? $back : route('articles.index');
    }
}
