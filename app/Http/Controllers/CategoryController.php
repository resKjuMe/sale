<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::withCount(['articles', 'articles as sold_count' => fn ($query) => $query->where('sold', true)])
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new Category]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = Category::create($this->validated($request));

        return redirect()->route('categories.show', $category)->with('status', 'Kategorie angelegt.');
    }

    public function show(Category $category): View
    {
        $articles = $category->articles()->latest()->paginate(100);

        return view('categories.show', compact('category', 'articles'));
    }

    public function print(Request $request, Category $category): View
    {
        return view('categories.print', [
            'category' => $category,
            'articles' => $category->articles()->oldest()->get(),
            'columns' => min(4, max(2, $request->integer('cols', 3))),
        ]);
    }

    public function regeneratePublicLink(Category $category): RedirectResponse
    {
        $category->regeneratePublicToken();

        return redirect()->route('categories.show', $category)->with('status', 'Neuer öffentlicher Link erzeugt, der alte ist ungültig.');
    }

    public function edit(Category $category): View
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return redirect()->route('categories.show', $category)->with('status', 'Kategorie gespeichert.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Einzeln löschen, damit der deleted-Hook die Bilder entfernt.
        $category->articles->each->delete();
        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Kategorie gelöscht.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [], ['name' => 'Name', 'description' => 'Beschreibung']);
    }
}
