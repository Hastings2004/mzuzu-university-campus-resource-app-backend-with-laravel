<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'booking_reference' => 'nullable|string|max:255',
            'resource_id' => 'required|exists:resources,id',
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'required|date|after:start_time',
            'status' => 'string|in:approved,pending,rejected',
            'purpose' => 'required|string|max:500',
            'supporting_document' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:2048',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'supporting_document.file' => 'The supporting document must be a valid file.',
            'supporting_document.mimes' => 'The supporting document must be a file of type: pdf, doc, docx, jpg, jpeg, png.',
            'supporting_document.max' => 'The supporting document may not be greater than 2MB.',
        ];
    }
}
