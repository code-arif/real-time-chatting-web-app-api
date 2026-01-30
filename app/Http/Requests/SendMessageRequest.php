<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => ['required', 'exists:conversations,id'],
            'type' => ['required', 'in:text,image,video,file,audio'],
            'content' => ['required_if:type,text', 'nullable', 'string', 'max:10000'],
            'media' => ['required_unless:type,text', 'file', 'max:10240'], // 10MB max
            'reply_to' => ['nullable', 'exists:messages,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required_if' => 'Message content is required for text messages.',
            'media.required_unless' => 'Media file is required for non-text messages.',
            'media.max' => 'File size must not exceed 10MB.',
        ];
    }
}
