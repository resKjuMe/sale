<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\ArticleFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Request $request, Category $category): View
    {
        $filter = ArticleFilter::fromRequest($request, internal: false);
        $articles = $filter->apply($category->articles())->with('images')->orderBy('sold')->latest()->get();

        return view('public.category', [
            'title' => $category->name,
            'description' => $category->description,
            'articles' => $articles,
            'filter' => $filter,
            'source' => $category->articles(),
        ]);
    }

    public function overview(Request $request, string $token): View
    {
        User::where('public_token', $token)->firstOrFail();
        $filter = ArticleFilter::fromRequest($request, internal: false);
        $articles = $filter->apply(Article::query())->with(['category', 'images'])->orderBy('sold')->latest()->get();

        return view('public.category', [
            'title' => config('app.name'),
            'description' => null,
            'articles' => $articles,
            'filter' => $filter,
            'source' => Article::query(),
            'overview' => true,
        ]);
    }
}
