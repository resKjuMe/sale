<?php

namespace App\Enums;

enum ArticleCondition: string
{
    case NewWithTags = 'new_with_tags';
    case New = 'new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Satisfactory = 'satisfactory';

    public function label(): string
    {
        return match ($this) {
            self::NewWithTags => 'Neu mit Etikett',
            self::New => 'Neu ohne Etikett',
            self::VeryGood => 'Sehr gut',
            self::Good => 'Gut',
            self::Satisfactory => 'Zufriedenstellend',
        };
    }
}
