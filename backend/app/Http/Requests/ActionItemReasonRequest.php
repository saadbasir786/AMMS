<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActionItemReasonRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['reason' => ['required', 'string', 'max:5000'], 'note' => ['sometimes', 'nullable', 'string', 'max:5000']]; }
}
