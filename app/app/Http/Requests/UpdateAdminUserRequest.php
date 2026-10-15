<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules pakai prefix "edit" supaya error nempel ke property modal edit,
     * bukan ke property form tambah.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function rulesFor(int $userId): array
    {
        return [
            'editName' => ['required', 'string', 'max:255'],
            'editEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'editPassword' => ['nullable', 'string', 'min:8', 'same:editPasswordConfirmation'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesForUser(): array
    {
        return [
            'editName.required' => 'Nama wajib diisi.',
            'editEmail.required' => 'Email wajib diisi.',
            'editEmail.email' => 'Format email tidak valid.',
            'editEmail.unique' => 'Email ini sudah dipakai.',
            'editPassword.min' => 'Password minimal 8 karakter.',
            'editPassword.same' => 'Konfirmasi password tidak sama.',
        ];
    }
}