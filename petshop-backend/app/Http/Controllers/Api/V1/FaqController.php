<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /**
     * Public / Admin List Active FAQs
     */
    public function index(): JsonResponse
    {
        $faqs = Faq::orderBy('order', 'asc')->latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $faqs,
        ]);
    }

    /**
     * Store new FAQ
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question'  => ['required', 'string', 'max:255'],
            'answer'    => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $faq = Faq::create([
            'question'  => $validated['question'],
            'answer'    => $validated['answer'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'FAQ enregistrée avec succès!',
            'data'    => $faq,
        ], 201);
    }

    /**
     * Update FAQ
     */
    public function update(Request $request, Faq $faq): JsonResponse
    {
        $validated = $request->validate([
            'question'  => ['required', 'string', 'max:255'],
            'answer'    => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        $faq->update([
            'question'  => $validated['question'],
            'answer'    => $validated['answer'],
            'is_active' => $request->boolean('is_active', $faq->is_active),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'FAQ mise à jour avec succès!',
            'data'    => $faq,
        ]);
    }

    /**
     * Delete FAQ
     */
    public function destroy(Faq $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'FAQ supprimée avec succès.',
        ]);
    }
}
