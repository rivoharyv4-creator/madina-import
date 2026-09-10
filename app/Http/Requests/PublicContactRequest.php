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
            'reference_image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_image.image'=>'Veuillez choisir une image JPG, PNG ou WebP.',
            'reference_image.mimes'=>'Formats acceptés : JPG, PNG et WebP.',
            'reference_image.max'=>'L’image ne doit pas dépasser 2 Mo.',
            'reference_image.uploaded'=>'L’image n’a pas pu être envoyée. Choisissez un fichier de 2 Mo maximum.',
            'consent.accepted'=>'Votre accord est nécessaire pour que nous puissions vous recontacter.',
            'website.max'=>'Votre demande n’a pas pu être envoyée.',
        ];
    }
}
