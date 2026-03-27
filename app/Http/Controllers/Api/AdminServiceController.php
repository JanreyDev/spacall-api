<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminServiceController extends Controller
{
    /**
     * Display a listing of all services.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->orderBy('sort_order')->get();

        return response()->json([
            'services' => $services
        ]);
    }

    /**
     * Display a listing of all categories.
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::orderBy('sort_order')->get();
        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'duration_minutes' => 'required|integer|min:1',
            'base_price' => 'required|numeric|min:0',
            'vip_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'image_url' => 'nullable|url|max:2048',
        ]);

        if (!array_key_exists('vip_price', $validated) || $validated['vip_price'] === null) {
            $validated['vip_price'] = $validated['base_price'];
        }

        $service = Service::create($validated);

        return response()->json([
            'message' => 'Service created successfully',
            'service' => $service->load('category')
        ], 201);
    }

    /**
     * Display the specified service.
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::with('category')->findOrFail($id);

        return response()->json([
            'service' => $service
        ]);
    }

    /**
     * Update the specified service.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|required|exists:service_categories,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:255',
            'duration_minutes' => 'sometimes|required|integer|min:1',
            'base_price' => 'sometimes|required|numeric|min:0',
            'vip_price' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'image_url' => 'nullable|url|max:2048',
        ]);

        if (array_key_exists('vip_price', $validated) && $validated['vip_price'] === null) {
            $validated['vip_price'] = $validated['base_price'] ?? $service->base_price;
        }

        if (isset($validated['name']) && $validated['name'] !== $service->name) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully',
            'service' => $service->load('category')
        ]);
    }

    /**
     * Remove the specified service.
     */
    public function destroy(int $id): JsonResponse
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully'
        ]);
    }

    /**
     * Upload an image for a service.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->store('services', 'public');
            $url = asset('storage/' . $path);

            return response()->json([
                'url' => $url,
                'message' => 'Image uploaded successfully'
            ]);
        }

        return response()->json(['message' => 'No image provided'], 400);
    }
}
