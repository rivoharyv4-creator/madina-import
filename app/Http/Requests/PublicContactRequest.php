<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['consent'=>$this->boolean('consent')]);
    }

    public function rules(): array
    {
        return [
            'name'=>['required','string','max:120'],
            'contact'=>['required','string','max:120'],
            'client_type'=>['required','in:revendeur,entrepreneur,particulier,hotel,entreprise'],
            'need'=>['required','string','max:160'],
            'message'=>['required','string','min:10','max:3000'],
            'consent'=>['accepted'],
            'website'=>['nullable','max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted'=>'Votre accord est nécessaire pour que nous puissions vous recontacter.',
            'website.max'=>'Votre demande n’a pas pu être envoyée.',
        ];
    }
}
