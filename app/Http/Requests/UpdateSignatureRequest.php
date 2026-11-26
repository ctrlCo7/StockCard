<?php

namespace App\Http\Requests;

use App\Services\SignatureService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** A signature drawn on the in-app pad (PNG data URI) or an uploaded scan (PNG/JPG). */
class UpdateSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'signature_data' => ['nullable', 'required_without:signature_file', 'string', 'max:700000', function (string $attribute, mixed $value, Closure $fail) {
                if (SignatureService::decodeDrawn((string) $value) === null) {
                    $fail('The drawn signature could not be read. Please sign again.');
                }
            }],
            'signature_file' => ['nullable', 'required_without:signature_data', 'file', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:min_width=100,min_height=30,max_width=3000,max_height=2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signature_data.required_without' => 'Draw your signature in the box, or upload an image of it.',
            'signature_file.required_without' => 'Draw your signature in the box, or upload an image of it.',
            'signature_file.mimes' => 'Upload the signature as a PNG or JPG image.',
            'signature_file.max' => 'The signature image may not be larger than 1 MB.',
            'signature_file.dimensions' => 'The signature image must be between 100×30 and 3000×2000 pixels.',
        ];
    }
}
