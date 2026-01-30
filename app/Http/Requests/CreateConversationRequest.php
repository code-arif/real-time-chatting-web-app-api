<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:private,group'],
            'user_id' => ['required_if:type,private', 'exists:users,id'],
            'user_ids' => ['required_if:type,group', 'array', 'min:2'],
            'user_ids.*' => ['exists:users,id'],
            'name' => ['required_if:type,group', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required_if' => 'User ID is required for private conversations.',
            'user_ids.required_if' => 'At least 2 users are required for group conversations.',
            'user_ids.min' => 'A group must have at least 2 members.',
            'name.required_if' => 'Group name is required.',
        ];
    }
}
