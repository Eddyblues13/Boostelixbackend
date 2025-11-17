<?php

namespace App\Http\Controllers\API;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\ServiceUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class ServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $cacheKey = 'services_category_' . $request->get('category_id', 'all');
            $cacheTime = 300; // 5 minutes

            $services = Cache::remember($cacheKey, $cacheTime, function () use ($request) {
                $query = Service::where('service_status', 1)
                    ->select([
                        'id',
                        'service_title',
                        'category_id',
                        'min_amount',
                        'max_amount',
                        'average_time',
                        'description',
                        'rate_per_1000',
                        'price',
                    ])
                    ->with(['category:id,category_title'])
                    ->orderBy('service_title');

                if ($request->has('category_id')) {
                    $query->where('category_id', $request->category_id);
                }

                return $query->get();
            });

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            Log::error('ServiceController index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load services'
            ], 500);
        }
    }


    public function allServices(): JsonResponse
    {
        try {
            $services = Cache::remember('all_services_essential', 600, function () {
                return Service::select([
                    'id',
                    'service_title',
                    'category_id',
                    'api_provider_id',
                    'min_amount',
                    'max_amount',
                    'average_time',
                    'rate_per_1000',
                    'price',
                    'service_status',
                    'description',
                    'service_type',
                    'drip_feed',
                    'refill',
                    'is_refill_automatic',
                    'featured',
                    'created_at',
                    'updated_at'
                ])->get();
            });

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            Log::error('ServiceController allServices error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load services'
            ], 500);
        }
    }


    // public function allServices(): JsonResponse
    // {
    //     try {
    //         $services = Cache::remember('all_services_essential', 600, function () {
    //             return Service::select([
    //                 'id',
    //                 'service_title',
    //                 'category_id',
    //                 'api_provider_id',
    //                 'min_amount',
    //                 'max_amount',
    //                 'price',
    //                 'rate_per_1000',
    //                 'service_status'
    //             ])
    //                 // ->with([
    //                 //     'category:id,category_title',
    //                 //     'provider:id,name'
    //                 // ])
    //                 ->get();
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $services
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('ServiceController allServices error: ' . $e->getMessage());
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load services'
    //         ], 500);
    //     }
    // }

    public function allSmmServices(Request $request): JsonResponse
    {
        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 15);
            $cacheKey = 'smm_services_' . md5(serialize($request->all()) . '_page_' . $page);

            $result = Cache::remember($cacheKey, 300, function () use ($request, $perPage) {
                $query = Service::select([
                    'id',
                    'service_title',
                    'category_id',
                    'api_provider_id',
                    'min_amount',
                    'max_amount',
                    'price',
                    'rate_per_1000',
                    'average_time',
                    'description',
                    'is_new',
                    'is_recommended',
                    'service_status'
                ])
                    ->with([
                        'category:id,category_title,slug',
                        'apiProvider:id,name'
                    ])
                    ->where('service_status', 1);

                if ($request->filled('category') && $request->category !== 'all') {
                    $query->whereHas('category', function ($q) use ($request) {
                        $q->where('slug', $request->category);
                    });
                }

                if ($request->filled('search')) {
                    $searchTerm = $request->search;
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('service_title', 'LIKE', $searchTerm . '%')
                            ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
                    });
                }

                if ($request->has('is_new')) {
                    $query->where('is_new', boolval($request->is_new));
                }

                if ($request->has('is_recommended')) {
                    $query->where('is_recommended', boolval($request->is_recommended));
                }

                if ($request->has('sort')) {
                    switch ($request->sort) {
                        case 'price-low':
                            $query->orderBy('price', 'asc');
                            break;
                        case 'price-high':
                            $query->orderBy('price', 'desc');
                            break;
                        case 'popular':
                            $query->orderBy('orders_count', 'desc');
                            break;
                        default:
                            $query->orderBy('id', 'desc');
                    }
                } else {
                    $query->orderBy('id', 'desc');
                }

                return $query->paginate($perPage);
            });

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('ServiceController allSmmServices error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load services'
            ], 500);
        }
    }

    public function searchServices(Request $request): JsonResponse
    {
        try {
            $searchTerm = trim($request->get('q', ''));
            $limit = $request->get('limit', 20);

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $cacheKey = 'service_search_' . md5($searchTerm . '_' . $limit);
            $services = Cache::remember($cacheKey, 120, function () use ($searchTerm, $limit) {
                return Service::select([
                    'id',
                    'service_title',
                    'category_id',
                    'min_amount',
                    'max_amount',
                    'price',
                    'rate_per_1000',
                    'description'
                ])
                    ->with(['category:id,category_title'])
                    ->where('service_status', 1)
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('service_title', 'LIKE', $searchTerm . '%')
                            ->orWhere('service_title', 'LIKE', '% ' . $searchTerm . '%')
                            ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
                    })
                    ->limit($limit)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            Log::error('ServiceController searchServices error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Search failed'
            ], 500);
        }
    }

    public function serviceUpdates(): JsonResponse
    {
        try {
            $updates = Cache::remember('service_updates', 3600, function () {
                return ServiceUpdate::select(['id', 'title', 'description', 'date', 'created_at'])
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc')
                    ->get();
            });

            return response()->json([
                'success' => true,
                'data' => $updates
            ]);
        } catch (\Exception $e) {
            Log::error('ServiceController serviceUpdates error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load service updates'
            ], 500);
        }
    }
}
