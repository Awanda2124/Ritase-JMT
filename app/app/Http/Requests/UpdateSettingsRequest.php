<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate_per_trip' => ['required', 'integer', 'min:0'],
            'radius_m' => ['required', 'integer', 'min:1', 'max:5000'],
            'jmt_lat' => ['required', 'numeric', 'between:-90,90'],
            'jmt_lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate_per_trip.required' => 'Tarif per ritase wajib diisi.',
            'radius_m.required' => 'Radius wajib diisi.',
            'jmt_lat.required' => 'Latitude JMT wajib diisi.',
            'jmt_lng.required' => 'Longitude JMT wajib diisi.',
        ];
    }
}
