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


    public function allSmmServices(Request $request)
    {
        $query = Service::with(['category', 'apiProvider'])
            ->when($request->has('category'), function ($query) use ($request) {
                if ($request->category !== 'all') {
                    $query->whereHas('category', function ($q) use ($request) {
                        $q->where('slug', $request->category);
                    });
                }
            })
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('service_title', 'like', '%' . $request->search . '%');
            })
            ->when($request->has('is_new'), function ($query) {
                $query->where('is_new', true);
            })
            ->when($request->has('is_recommended'), function ($query) {
                $query->where('is_recommended', true);
            });

        // Sorting
        if ($request->has('sort')) {
            switch ($request->sort) {
                case 'price-low':
                    $query->orderBy('price');
                    break;
                case 'price-high':
                    $query->orderByDesc('price');
                    break;
                case 'popular':
                    // You would need an order_count column or similar for this
                    $query->orderByDesc('order_count');
                    break;
                default:
                    $query->latest();
            }
        } else {
            $query->latest();
        }

        return response()->json($query->paginate(15));
    }
}
