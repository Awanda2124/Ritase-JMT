<?php

namespace App\Http\Requests;

use App\Services\WorkSessionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkSessionRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'route' => ['required', 'string', 'max:255'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'checkin_closes_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'report_closes_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'quota' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Tanggal wajib diisi.',
            'route.required' => 'Rute wajib diisi.',
            'open_time.required' => 'Jam buka wajib diisi.',
            'close_time.required' => 'Jam tutup wajib diisi.',
            'close_time.after' => 'Jam tutup harus setelah jam buka.',
            'checkin_closes_at.date_format' => 'Format batas check-in tidak valid.',
            'report_closes_at.date_format' => 'Format batas lapor tidak valid.',
            'quota.required' => 'Kuota wajib diisi.',
            'quota.min' => 'Kuota minimal 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('route'))) {
            $this->merge([
                'route' => WorkSessionService::DEFAULT_ROUTE,
            ]);
        }
    }
}