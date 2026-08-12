<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class StoreController extends Controller
{
    /**
     * Display a listing of stores (Multi-Magasin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Store::withCount(['users', 'orders']);

        if ($request->has('active_only') && filter_var($request->active_only, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('is_active', true);
        }

        $stores = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $stores,
        ]);
    }

    /**
     * Store a newly created store in storage
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:50', 'unique:stores,code'],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $store = Store::create([
            'name'      => $validated['name'],
            'code'      => strtoupper(str_replace(' ', '_', $validated['code'])),
            'address'   => $validated['address'] ?? null,
            'phone'     => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Magasin créé avec succès!',
            'data'    => $store,
        ], 201);
    }

    /**
     * Display the specified store
     */
    public function show(Store $store): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $store->load(['users', 'orders' => fn($q) => $q->latest()->take(10)]),
        ]);
    }

    /**
     * Update the specified store in storage
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'code'      => ['required', 'string', 'max:50', Rule::unique('stores')->ignore($store->id)],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $store->update([
            'name'      => $validated['name'],
            'code'      => strtoupper(str_replace(' ', '_', $validated['code'])),
            'address'   => $validated['address'] ?? $store->address,
            'phone'     => $validated['phone'] ?? $store->phone,
            'is_active' => $validated['is_active'] ?? $store->is_active,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Magasin mis à jour avec succès!',
            'data'    => $store,
        ]);
    }

    /**
     * Remove the specified store from storage
     */
    public function destroy(Store $store): JsonResponse
    {
        // Don't allow deletion if store has orders or users assigned
        if ($store->orders()->count() > 0 || $store->users()->count() > 0) {
            $store->update(['is_active' => false]);
            return response()->json([
                'status'  => 'success',
                'message' => 'Le magasin contient des données historiques. Il a été désactivé au lieu d\'être supprimé.',
                'data'    => $store,
            ]);
        }

        $store->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Magasin supprimé avec succès.',
        ]);
    }
}
