<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResourceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user && $user->user_type === 'admin') {
            return true;
        }
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //validation rules for updating a resource
            "name" => ['sometimes', 'string', 'min:3', 'max:100'],
            "description" => ['sometimes', 'string', 'min:5', 'max:500'],
            "location" => ['sometimes', 'string', 'min:3', 'max:100'],
            "capacity" => ['sometimes', 'integer', 'min:1'],
            "category" => ['sometimes', 'string', 'in:classrooms,ict_labs,science_labs,auditorium,sports,cars'],
            "status" => ['sometimes', 'string', 'in:available,unavailable'],
            "image" => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048']
        ];
    }
}
