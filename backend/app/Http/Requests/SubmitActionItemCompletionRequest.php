<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitActionItemCompletionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'completion_note' => ['required', 'string', 'max:10000'],
            'evidence' => ['sometimes', 'array'],
            'evidence.*.storage_path' => ['required', 'string', 'max:500'],
            'evidence.*.storage_disk' => ['nullable', 'string', 'max:64'],
            'evidence.*.original_filename' => ['required', 'string', 'max:255'],
            'evidence.*.mime_type' => ['required', 'string', 'max:120'],
            'evidence.*.file_size' => ['required', 'integer', 'min:0'],
        ];
    }
}
