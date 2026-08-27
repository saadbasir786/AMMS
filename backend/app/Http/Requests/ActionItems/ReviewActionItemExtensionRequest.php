<?php

declare(strict_types=1);

namespace App\Http\Requests\ActionItems;

use Illuminate\Foundation\Http\FormRequest;

class ReviewActionItemExtensionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['approve' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:5000', 'required_if:approve,false']]; }
}
