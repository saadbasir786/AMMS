<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class ReviewMeetingRequestRequest extends FormRequest { public function authorize(): bool { return $this->user()?->isActive() === true; } public function rules(): array { return ['reason'=>['required','string','max:2000']]; } public function messages(): array { return ['reason.required'=>'نوشتن دلیل برای برگشت یا رد درخواست الزامی است.']; } }