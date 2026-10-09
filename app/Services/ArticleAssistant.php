<?php

namespace App\Services;

use Anthropic\Beta\Messages\BetaBase64ImageSource;
use Anthropic\Beta\Messages\BetaImageBlockParam;
use Anthropic\Beta\Messages\BetaTextBlockParam;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use RuntimeException;

/**
 * Liest Marke und Größe aus Artikelfotos (bevorzugt vom Etikett) oder schlägt einen Titel vor.
 */
class ArticleAssistant
{
    public const MODES = ['brand_size', 'title'];

    // Etiketten brauchen Auflösung, für den Titel reicht eine grobe Ansicht.
    private const MAX_EDGE = ['brand_size' => 1568, 'title' => 800];

    // Haiku kennt keinen serverseitigen Refusal-Fallback.
    private const WITHOUT_FALLBACK = 'claude-haiku-';

    private const SYSTEM = <<<'TXT'
        Du hilfst beim Erfassen gebrauchter Kinderkleidung, die privat verkauft wird.
        Du bekommst Fotos eines Artikels; eines davon zeigt oft das Etikett.
        Antworte nur mit dem verlangten JSON. Was du auf den Fotos nicht sicher erkennst, setzt du auf null – rate nicht.
        TXT;

    public function __construct(
        private readonly ?string $apiKey,
        /** @var array<string, string> Modell je Modus */
        private readonly array $models,
        private readonly ?string $workspaceId = null,
    ) {}

    public function configured(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @param  list<string>  $images  Rohdaten (JPEG/PNG/WebP)
     * @param  array{brands?: list<string>, sizes?: list<string>, brand?: ?string, size?: ?string}  $context
     * @return array{brand: ?string, size: ?string, title: ?string}
     */
    public function suggest(array $images, string $mode, array $context = []): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Die KI ist nicht eingerichtet (ANTHROPIC_API_KEY fehlt).');
        }

        $content = array_map(
            fn (string $image) => BetaImageBlockParam::with(source: BetaBase64ImageSource::with(data: base64_encode(self::shrink($image, self::MAX_EDGE[$mode])), mediaType: 'image/jpeg')),
            $images,
        );
        $content[] = BetaTextBlockParam::with(text: $this->instruction($mode, $context));

        $model = $this->models[$mode];
        $params = [
            'model' => $model,
            'maxTokens' => 16000,
            'system' => self::SYSTEM,
            'messages' => [['role' => 'user', 'content' => $content]],
            'outputConfig' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => self::schema()]],
            'workspaceID' => $this->workspaceId ?: null,
        ];
        if (! str_starts_with($model, self::WITHOUT_FALLBACK)) {
            // Lehnt das Modell aus Richtlinien-Gründen ab, versucht die API es serverseitig mit dem Standard-Ersatzmodell.
            $params += ['fallbacks' => 'default', 'betas' => ['server-side-fallback-2026-07-01']];
        }

        try {
            $message = (new Client(apiKey: $this->apiKey))->beta->messages->create(...$params);
        } catch (APIException $e) {
            throw new RuntimeException('Die KI ist gerade nicht erreichbar. Bitte später erneut versuchen.', previous: $e);
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Die KI konnte zu diesen Fotos nichts sagen.');
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return [
                        'brand' => self::clean($data['brand'] ?? null),
                        'size' => self::clean($data['size'] ?? null),
                        'title' => self::clean($data['title'] ?? null),
                    ];
                }
            }
        }

        throw new RuntimeException('Die KI hat keine verwertbare Antwort geliefert.');
    }

    private function instruction(string $mode, array $context): string
    {
        $brands = implode(', ', array_slice($context['brands'] ?? [], 0, 150));
        $sizes = implode(', ', array_slice($context['sizes'] ?? [], 0, 60));

        if ($mode === 'brand_size') {
            return <<<TXT
                Ermittle Marke und Größe dieses Artikels, am besten vom Etikett. title bleibt null.
                - Marke: Steht sie in dieser Liste bereits erfasster Marken, übernimm deren Schreibweise: {$brands}
                - Größe so, wie sie auf dem Etikett steht, im Stil der bisherigen Angaben: {$sizes}
                  Beispiele: „68 / 72", „6-12 Monate", „92", „110/116", „S". Bei mehreren Systemen auf dem Etikett nimm die deutsche Konfektionsgröße (cm).
                TXT;
        }

        $known = trim(implode(' · ', array_filter([$context['brand'] ?? null, isset($context['size']) ? 'Gr. '.$context['size'] : null])));

        return <<<TXT
            Schlage einen kurzen deutschen Titel für die Verkaufsanzeige vor. brand und size bleiben null.
            - 2 bis 6 Wörter, beschreibt Art des Kleidungsstücks und auffällige Merkmale (Farbe, Muster, Motiv, Schnitt).
            - Ohne Marke, Größe, Preis, Zustand und Emojis. Beispiel: „Gestreifter Langarmbody mit Knopfleiste".
            Bekannt zum Artikel: {$known}
            TXT;
    }

    private static function schema(): array
    {
        $nullableString = ['anyOf' => [['type' => 'string'], ['type' => 'null']]];

        return [
            'type' => 'object',
            'properties' => ['brand' => $nullableString, 'size' => $nullableString, 'title' => $nullableString],
            'required' => ['brand', 'size', 'title'],
            'additionalProperties' => false,
        ];
    }

    private static function clean(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 255) : null;
    }

    public static function shrink(string $image, int $maxEdge = self::MAX_EDGE['brand_size']): string
    {
        $source = @imagecreatefromstring($image);
        if ($source === false) {
            throw new RuntimeException('Ein Foto konnte nicht gelesen werden.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxEdge / max($width, $height));
        $target = imagescale($source, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        $stream = fopen('php://memory', 'w+b');
        imagejpeg($target, $stream, 85);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
