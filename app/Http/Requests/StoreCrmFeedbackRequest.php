<?php

namespace App\Http\Requests;

use App\Dto\DtoCrmFeedback;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCrmFeedbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'=> 'string|required',
            'city'=> 'string|required',
            'email'=> 'string|nullable',
            'phone'=> 'string|nullable',
            'content'=> 'string|nullable',
            'image'=> 'file|nullable',
            'prompt'=> 'string|nullable',
            'source'=> 'integer|nullable',
        ];
    }

    public function dto(): DtoCrmFeedback
    {
        return new DtoCrmFeedback(
            name: $this->get('name'),
            city: $this->get('city'),
            email: $this->get('email'),
            phone: $this->get('phone'),
            content: $this->get('content'),
            image: $this->file('image'),
            prompt: $this->get('prompt'),
            source: $this->integer('source'),
        );
    }
}
