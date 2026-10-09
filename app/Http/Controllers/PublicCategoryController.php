<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\View\View;

class PublicCategoryController extends Controller
{
    public function show(Category $category): View
    {
        $articles = $category->articles()->orderBy('sold')->latest()->get();

        return view('public.category', compact('category', 'articles'));
    }
}
