<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnblockActionItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['note' => ['required', 'string', 'max:5000']]; }
}
