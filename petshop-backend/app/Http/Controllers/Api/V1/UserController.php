<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of staff users (ADMIN, CAISSIER, MAGASINIER)
     */
    public function index(): JsonResponse
    {
        $users = User::with(['roles:id,name', 'store:id,name,code'])
            ->select('id', 'name', 'email', 'store_id', 'created_at')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $users,
        ]);
    }

    /**
     * Store a newly created staff user and assign role & store
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['required', 'string', 'in:ADMIN,CAISSIER,MAGASINIER'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'store_id' => $validated['store_id'] ?? null,
        ]);

        // Assign Spatie Role
        $user->assignRole($validated['role']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur créé avec succès!',
            'data'    => $user->load(['roles:id,name', 'store:id,name,code']),
        ], 201);
    }

    /**
     * Update specified staff user details, role & store
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role'     => ['required', 'string', 'in:ADMIN,CAISSIER,MAGASINIER'],
            'password' => ['nullable', 'string', 'min:6'],
            'store_id' => ['nullable', 'exists:stores,id'],
        ]);

        $userData = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($request->has('store_id')) {
            $userData['store_id'] = $validated['store_id'];
        }

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        // Sync Spatie Role
        $user->syncRoles([$validated['role']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Compte utilisateur mis à jour!',
            'data'    => $user->load(['roles:id,name', 'store:id,name,code']),
        ]);
    }

    /**
     * Remove the specified staff user
     */
    public function destroy(User $user, Request $request): JsonResponse
    {
        // Prevent admin from deleting their own current account
        if ($user->id === $request->user()->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte actuellement connecté.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }
}
