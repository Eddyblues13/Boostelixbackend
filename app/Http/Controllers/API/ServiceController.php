<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Service::where('service_status', 1)
                ->with('category:id,category_title')
                ->orderBy('service_title');

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $services = $query->get([
                'id',
                'service_title',
                'category_id',
                'min_amount',
                'max_amount',
                'average_time',
                'description',
                'rate_per_1000',
                'price',
                'description'
            ]);

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load services'
            ], 500);
        }
    }

    public function allServices(): JsonResponse
    {
        try {
            $services = Service::get();

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load categories'
            ], 500);
        }
    }
}
