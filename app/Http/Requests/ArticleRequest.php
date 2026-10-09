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
            'sold' => ['sometimes', 'boolean'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],
            'paid' => ['sometimes', 'boolean'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
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
            'sold' => 'Verkauft',
            'buyer_name' => 'Käufer',
            'buyer_address' => 'Adresse',
            'paid' => 'Bezahlt',
            'sale_price' => 'Verkaufspreis',
        ];
    }

    public function articleData(): array
    {
        $data = $this->safe()->except('image');

        if (array_key_exists('sold', $data) && ! $data['sold']) {
            $data = array_merge($data, ['buyer_name' => null, 'buyer_address' => null, 'paid' => false, 'sale_price' => null]);
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        foreach (['price', 'sale_price'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }
    }
}
