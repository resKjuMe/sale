<?php

namespace App\Http\Requests;

use App\Enums\ArticleCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => [$this->route('article') ? 'nullable' : 'required', 'image', 'max:10240'],
            'title' => ['nullable', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'size' => ['required', 'string', 'max:50'],
            'condition' => ['nullable', Rule::enum(ArticleCondition::class)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'image' => 'Bild',
            'title' => 'Titel',
            'brand' => 'Marke',
            'size' => 'Größe',
            'condition' => 'Zustand',
            'price' => 'Preis',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->price)) {
            $this->merge(['price' => str_replace(',', '.', trim($this->price))]);
        }
    }
}
