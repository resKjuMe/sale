<?php

namespace App\Http\Controllers;

use App\Http\Requests\ArticleRequest;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ArticleController extends Controller
{
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
        $data = $request->safe()->except('image');
        $data['image_path'] = $request->file('image')->store('articles', 'public');

        $article = $category->articles()->create($data);

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
        $data = $request->safe()->except('image');
        $oldPath = null;

        if ($request->hasFile('image')) {
            $oldPath = $article->image_path;
            $data['image_path'] = $request->file('image')->store('articles', 'public');
        }

        $article->update($data);

        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('articles.show', $article)->with('status', 'Artikel gespeichert.');
    }

    public function destroy(Article $article): RedirectResponse
    {
        $category = $article->category;
        $article->delete();

        return redirect()->route('categories.show', $category)->with('status', 'Artikel gelöscht.');
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
