<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * User Login API Endpoint
     * Supports: ADMIN, CAISSIER, MAGASINIER
     */
    public function login(Request $request): JsonResponse
    {
        // 1. Validation dyal Input
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // 2. Vérification dyal User & Password
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Les identifiants sont incorrects.',
            ], 401);
        }

        // 3. Delete old tokens (Optionnel: Max 1 active session per user)
        $user->tokens()->delete();

        // 4. Generate Sanctum Token
        // Token name incorporates user role for clarity
        $roleName = $user->getRoleNames()->first() ?? 'USER';
        $token = $user->createToken("auth_token_{$roleName}")->plainTextToken;

        // 5. Response payload with User details, Roles & Token
        return response()->json([
            'status'  => 'success',
            'message' => 'Connexion réussie',
            'data'    => [
                'user' => [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'roles'       => $user->getRoleNames(), // Spatie helper -> Array d les roles
                    'permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ], 200);
    }

    /**
     * Get Current Authenticated User Profile
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ]
        ]);
    }

    /**
     * Logout / Revoke Token
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current user token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Déconnexion réussie',
        ]);
    }
}
