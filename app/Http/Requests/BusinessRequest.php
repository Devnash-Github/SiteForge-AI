<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');

        return $business ? $this->user()->can('update', $business) : true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['website_url', 'instagram_url', 'facebook_url', 'linkedin_url', 'google_business_url'] as $field) {
            $value = trim((string) $this->input($field, ''));
            if ($value !== '' && ! preg_match('#^https?://#i', $value)) {
                $value = 'https://'.$value;
            }
            $normalized[$field] = $value === '' ? null : $value;
        }
        $other = $this->input('other_urls', '');
        $list = is_array($other) ? $other : preg_split('/[\s,]+/', (string) $other);
        $normalized['other_urls'] = array_values(array_filter(array_map(function ($u) {
            $u = trim((string) $u);

            return $u === '' ? null : (preg_match('#^https?://#i', $u) ? $u : 'https://'.$u);
        }, $list ?: [])));

        $this->merge($normalized);
    }

    public function rules(): array
    {
        $url = ['nullable', 'string', 'max:2048', 'url:http,https'];

        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'city' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'additional_info' => ['nullable', 'string', 'max:5000'],
            'website_url' => $url,
            'instagram_url' => $url,
            'facebook_url' => $url,
            'linkedin_url' => $url,
            'google_business_url' => $url,
            'other_urls' => ['array', 'max:10'],
            'other_urls.*' => ['string', 'max:2048', 'url:http,https'],
            'files' => ['nullable', 'array', 'max:'.config('siteforge.max_files_per_request', 20)],
            'files.*' => UploadRules::file(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $checks = ['instagram_url' => 'instagram.com', 'facebook_url' => 'facebook.com', 'linkedin_url' => 'linkedin.com'];
            foreach ($checks as $field => $domain) {
                $value = $this->input($field);
                if ($value && ! str_contains(strtolower((string) parse_url($value, PHP_URL_HOST)), str_replace('.com', '', $domain))) {
                    $v->errors()->add($field, "This doesn't look like a {$domain} link.");
                }
            }
        });
    }

    public function messages(): array
    {
        return array_merge(UploadRules::messages(), [
            'name.required' => 'Enter your business name.',
            'city.required' => 'Enter the city or location of your business.',
            '*.url' => 'Enter a valid web address.',
            'other_urls.*.url' => 'One of the additional links is not a valid web address.',
        ]);
    }
}
