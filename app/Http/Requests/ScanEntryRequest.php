<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'turnstile_id' => ['required', 'integer', 'exists:turnstiles,id'],
            'qr_code' => ['required', 'string'],
        ];
    }
}
