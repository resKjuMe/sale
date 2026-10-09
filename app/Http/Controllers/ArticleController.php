<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleRequest;
use App\Models\Article;
use App\Models\Category;
use App\Support\ArticleFilter;
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
}
