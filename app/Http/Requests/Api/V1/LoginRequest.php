<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The user of these credentials. The id and the password are checked together, so a wrong id
     * and a wrong password give the same answer, and a user who cannot enter the system is told why.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        /** @var User|null $user */
        $user = User::query()->whereKey($this->integer('user_id'))->first();

        if (! $user || ! Hash::check($this->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'user_id' => __('api.auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'user_id' => __('api.auth.inactive'),
            ]);
        }

        return $user;
    }
}
