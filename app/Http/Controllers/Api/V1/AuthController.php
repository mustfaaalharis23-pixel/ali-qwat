<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Signs the user in and gives their device a token. The token text is returned once here and never again,
     * so the app saves it and sends it with every request after it.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticate();

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->value())->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * The user of the token, so the app knows who is signed in.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Signs the user out of this device by deleting its token only; the other devices keep working.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }
}
