<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\ArticleFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Request $request, Category $category): View
    {
        $filter = ArticleFilter::fromRequest($request);
        $articles = $filter->apply($category->articles())->orderBy('sold')->latest()->get();

        return view('public.category', compact('category', 'articles', 'filter'));
    }
}
