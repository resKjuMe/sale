<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ArticleAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class ArticleAssistController extends Controller
{
    private const MAX_IMAGES = 5;

    public function __invoke(Request $request, ArticleAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(ArticleAssistant::MODES)],
            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            'images.*' => ['image', 'max:10240'],
            'article_id' => ['nullable', 'integer'],
            'brand' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
        ]);

        if (! $assistant->configured()) {
            return response()->json(['message' => 'Die KI ist nicht eingerichtet (ANTHROPIC_API_KEY fehlt).'], 503);
        }

        // Neu gewählte Fotos zuerst, danach die schon gespeicherten des Artikels.
        $images = array_map(fn (UploadedFile $file) => $file->getContent(), $request->file('images', []));
        if (isset($data['article_id'])) {
            $images = [...$images, ...$this->storedImages(Article::findOrFail($data['article_id']))];
        }
        $images = array_slice(array_values(array_filter($images)), 0, self::MAX_IMAGES);

        if ($images === []) {
            return response()->json(['message' => 'Bitte zuerst ein Foto auswählen.'], 422);
        }

        try {
            $suggestion = $assistant->suggest($images, $data['mode'], [
                'brands' => Article::query()->distinct()->orderBy('brand')->pluck('brand')->all(),
                'sizes' => Article::query()->distinct()->orderBy('size')->pluck('size')->all(),
                'brand' => $data['brand'] ?? null,
                'size' => $data['size'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json($suggestion);
    }

    // Für „Titel per KI" auf der Kategorieseite: Vorschlag direkt speichern.
    public function title(Article $article, ArticleAssistant $assistant): JsonResponse
    {
        if (! $assistant->configured()) {
            return response()->json(['message' => 'Die KI ist nicht eingerichtet (ANTHROPIC_API_KEY fehlt).'], 503);
        }

        $images = array_slice(array_values(array_filter($this->storedImages($article))), 0, self::MAX_IMAGES);
        if ($images === []) {
            return response()->json(['message' => 'Der Artikel hat kein Foto.'], 422);
        }

        try {
            $title = $assistant->suggest($images, 'title', ['brand' => $article->brand, 'size' => $article->size])['title'];
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        if ($title !== null) {
            $article->update(['title' => $title]);
        }

        return response()->json(['title' => $title]);
    }

    /**
     * @return list<?string>
     */
    private function storedImages(Article $article): array
    {
        $paths = [$article->image_path, ...$article->images()->pluck('path')];

        return array_map(fn (?string $path) => $path ? Storage::disk('public')->get($path) : null, $paths);
    }
}
