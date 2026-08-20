<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActionItemExtensionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['requested_due_date' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:5000']]; }
}
