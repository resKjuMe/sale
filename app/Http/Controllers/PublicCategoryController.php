<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\PageView;
use App\Models\User;
use App\Support\ArticleFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $category = Category::withoutGlobalScope('tenant')->where('public_token', $token)->firstOrFail();
        PageView::record($request, $category->tenant_id, $category->id);
        $source = Article::forTenant($category->tenant_id)->where('category_id', $category->id);
        $filter = ArticleFilter::fromRequest($request, internal: false);
        $articles = $filter->apply(clone $source)->with('images')->orderBy('sold')->latest()->get();

        return view('public.category', [
            'title' => $category->name,
            'description' => $category->description,
            'articles' => $articles,
            'filter' => $filter,
            'source' => $source,
        ]);
    }

    public function overview(Request $request, string $token): View
    {
        $tenantId = User::where('public_token', $token)->firstOrFail()->tenant_id;
        PageView::record($request, $tenantId, null);
        $source = Article::forTenant($tenantId);
        $filter = ArticleFilter::fromRequest($request, internal: false);
        $articles = $filter->apply(clone $source)->with(['category' => fn ($query) => $query->withoutGlobalScope('tenant'), 'images'])->orderBy('sold')->latest()->get();

        return view('public.category', [
            'title' => config('app.name'),
            'description' => null,
            'articles' => $articles,
            'filter' => $filter,
            'source' => $source,
            'overview' => true,
        ]);
    }
}
