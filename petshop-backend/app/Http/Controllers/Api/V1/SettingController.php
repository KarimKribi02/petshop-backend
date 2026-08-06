<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Get Store Settings (Publicly accessible for POS Ticket and Next.js Storefront)
     */
    public function show(): JsonResponse
    {
        $setting = Setting::firstOrCreate([], [
            'store_name'        => 'Petshop Boutique',
            'support_email'     => 'contact@petshop.ma',
            'phone_number'      => '+212 6 00 00 00 00',
            'address'           => 'Marrakech, Maroc',
            'store_description' => 'Votre expert en nutrition et accessoires animaux au Maroc.',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $setting,
        ]);
    }

    /**
     * Update Store Settings (Admin Only)
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_name'        => ['required', 'string', 'max:255'],
            'support_email'     => ['nullable', 'email', 'max:255'],
            'phone_number'      => ['required', 'string', 'max:50'],
            'address'           => ['nullable', 'string'],
            'store_description' => ['nullable', 'string'],
            'facebook_url'      => ['nullable', 'url', 'max:255'],
            'instagram_url'     => ['nullable', 'url', 'max:255'],
            'logo_url'          => ['nullable', 'string'],
            'logo_file'         => ['nullable', 'image', 'max:2048'],
        ]);

        $setting = Setting::first();
        if (!$setting) {
            $setting = new Setting();
        }

        $logoPath = $validated['logo_url'] ?? $setting->logo_url;

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('settings', 'public');
            $logoPath = asset('storage/' . $path);
        }

        $setting->fill([
            'store_name'        => $validated['store_name'],
            'support_email'     => $validated['support_email'] ?? null,
            'phone_number'      => $validated['phone_number'],
            'address'           => $validated['address'] ?? null,
            'store_description' => $validated['store_description'] ?? null,
            'facebook_url'      => $validated['facebook_url'] ?? null,
            'instagram_url'     => $validated['instagram_url'] ?? null,
            'logo_url'          => $logoPath,
        ]);

        $setting->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Paramètres de la boutique mis à jour avec succès!',
            'data'    => $setting,
        ]);
    }
}
