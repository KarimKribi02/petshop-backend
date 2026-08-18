<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PostController extends Controller
{
        /**
     * Public / Admin List Blog Posts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::latest('published_at');

        if (!$request->has('include_drafts')) {
            $query->where(function ($q) {
                $q->where('status', 'PUBLISHED')
                  ->orWhereNull('status');
            });
        }

        // Search Filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%")
                  ->orWhere('excerpt', 'LIKE', "%{$search}%")
                  ->orWhere('category_name', 'LIKE', "%{$search}%");
            });
        }

        // Category Filter
        if ($request->has('category') && !empty($request->category) && $request->category !== 'ALL' && $request->category !== 'Toutes les catégories') {
            $cat = $request->category;
            $query->where('category_name', 'LIKE', "%{$cat}%");
        }

        $perPage = (int) $request->get('per_page', 50);
        $posts = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $posts,
        ]);
    }

    /**
     * Show single Article by Slug or ID
     */
    public function show(Request $request, string $identifier): JsonResponse
    {
        $post = Post::where('slug', $identifier)
            ->orWhere('id', $identifier)
            ->first();

        if (!$post) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Article introuvable.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $post,
        ]);
    }

    /**
     * Store new Article
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt'       => ['nullable', 'string'],
            'content'       => ['required', 'string'],
            'image'         => ['nullable', 'string'],
            'image_file'    => ['nullable', 'image', 'max:2048'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'tags'          => ['nullable', 'string'],
            'status'        => ['required', 'in:DRAFT,PUBLISHED'],
            'published_at'  => ['nullable', 'date'],
        ]);

        $imagePath = $validated['image'] ?? null;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('blog', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $post = Post::create([
            'title'         => $validated['title'],
            'slug'          => !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']),
            'excerpt'       => $validated['excerpt'] ?? null,
            'content'       => $validated['content'],
            'image'         => $imagePath,
            'category_name' => $validated['category_name'] ?? 'General',
            'tags'          => $validated['tags'] ?? null,
            'author_name'   => $request->user()?->name ?? 'Admin',
            'status'        => $validated['status'],
            'published_at'  => $validated['published_at'] ?? now(),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Article publié avec succès!',
            'data'    => $post,
        ], 201);
    }

    /**
     * Update Article
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'title'         => ['required', 'string', 'max:255'],
            'slug'          => ['nullable', 'string', 'max:255', 'unique:posts,slug,' . $post->id],
            'excerpt'       => ['nullable', 'string'],
            'content'       => ['required', 'string'],
            'image'         => ['nullable', 'string'],
            'image_file'    => ['nullable', 'image', 'max:2048'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'tags'          => ['nullable', 'string'],
            'status'        => ['required', 'in:DRAFT,PUBLISHED'],
            'published_at'  => ['nullable', 'date'],
        ]);

        $imagePath = $validated['image'] ?? $post->image;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('blog', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $post->update([
            'title'         => $validated['title'],
            'slug'          => !empty($validated['slug']) ? Str::slug($validated['slug']) : $post->slug,
            'excerpt'       => $validated['excerpt'] ?? $post->excerpt,
            'content'       => $validated['content'],
            'image'         => $imagePath,
            'category_name' => $validated['category_name'] ?? $post->category_name,
            'tags'          => $validated['tags'] ?? $post->tags,
            'status'        => $validated['status'],
            'published_at'  => $validated['published_at'] ?? $post->published_at,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Article mis à jour avec succès!',
            'data'    => $post,
        ]);
    }

    /**
     * Delete Article
     */
    public function destroy(Post $post): JsonResponse
    {
        $post->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Article supprimé avec succès.',
        ]);
    }
}
