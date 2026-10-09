<?php

namespace App\Http\Requests;

use App\Enums\ArticleCondition;
use App\Models\Article;
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
            'vinted_url' => ['nullable', 'string', 'max:500', 'url:https', 'regex:#^https://([a-z0-9-]+\.)?vinted\.[a-z]{2,3}(\.[a-z]{2})?/#i'],
            'sold' => ['sometimes', 'boolean'],
            'buyer_name' => ['nullable', 'string', 'max:255'],
            'buyer_address' => ['nullable', 'string', 'max:1000'],
            'paid' => ['sometimes', 'boolean'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'shipped' => ['sometimes', 'boolean'],
            'tracking_code' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9]+$/'],
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
            'vinted_url' => 'Vinted-Link',
            'sold' => 'Verkauft',
            'buyer_name' => 'Käufer',
            'buyer_address' => 'Adresse',
            'paid' => 'Bezahlt',
            'sale_price' => 'Verkaufspreis',
            'shipping_cost' => 'Versandkosten',
            'shipped' => 'Versendet',
            'tracking_code' => 'DHL-Sendungsnummer',
        ];
    }

    public function articleData(): array
    {
        $data = $this->safe()->except('image');

        if (array_key_exists('sold', $data) && ! $data['sold']) {
            $data = array_merge($data, Article::UNSOLD_RESET);
        }

        if (array_key_exists('shipped', $data) && ! $data['shipped']) {
            $data['tracking_code'] = null;
        }

        return $data;
    }

    protected function prepareForValidation(): void
    {
        foreach (['price', 'sale_price', 'shipping_cost'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($this->input($field)))]);
            }
        }

        if (is_string($this->input('tracking_code'))) {
            $this->merge(['tracking_code' => preg_replace('/\s+/', '', $this->input('tracking_code')) ?: null]);
        }
    }
}
