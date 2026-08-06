<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers
     */
    public function index(): JsonResponse
    {
        $suppliers = Supplier::latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $suppliers,
        ]);
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string'],
            'ice'          => ['nullable', 'string', 'max:50'],
            'is_active'    => ['boolean'],
        ]);

        $supplier = Supplier::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Fournisseur enregistré avec succès!',
            'data'    => $supplier,
        ], 201);
    }

    /**
     * Update specified supplier
     */
    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone'        => ['required', 'string', 'max:50'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string'],
            'ice'          => ['nullable', 'string', 'max:50'],
            'is_active'    => ['boolean'],
        ]);

        $supplier->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Informations du fournisseur mises à jour!',
            'data'    => $supplier,
        ]);
    }

    /**
     * Remove supplier
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Fournisseur supprimé avec succès.',
        ]);
    }
}
