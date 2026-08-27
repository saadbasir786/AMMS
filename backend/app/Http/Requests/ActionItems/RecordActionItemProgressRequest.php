<?php

declare(strict_types=1);

namespace App\Http\Requests\ActionItems;

use Illuminate\Foundation\Http\FormRequest;

class RecordActionItemProgressRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['progress_percent' => ['required', 'integer', 'between:0,100'], 'note' => ['nullable', 'string', 'max:5000']]; }
}
