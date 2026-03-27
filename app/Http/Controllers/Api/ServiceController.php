<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of services, grouped by category.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = ServiceCategory::with(['services' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }])->where('is_active', true)->orderBy('sort_order')->get();

        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * Display the specified service detail.
     */
    public function show(string $slug): JsonResponse
    {
        $service = Service::with('category')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return response()->json([
            'service' => $service
        ]);
    }
}
