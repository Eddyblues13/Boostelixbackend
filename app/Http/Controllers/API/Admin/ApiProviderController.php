<?php

namespace App\Http\Controllers\API\Admin;

use App\Models\Service;
use App\Models\Category;
use App\Models\ApiProvider;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class ApiProviderController extends Controller
{




    public function index()
    {
        $providers = ApiProvider::with(['services'])->get();
        return response()->json($providers);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'api_name' => 'required|string|max:255',
            'url' => 'required|url|max:255',
            'api_key' => 'required|string|max:255',
            'balance' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'convention_rate' => 'required|numeric|min:0.01',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
        ]);

        $provider = ApiProvider::create($validated);
        return response()->json($provider, 201);
    }

    public function show($id)
    {
        $provider = ApiProvider::with(['services', 'logs'])->findOrFail($id);
        return response()->json($provider);
    }

    public function update(Request $request, $id)
    {
        $provider = ApiProvider::findOrFail($id);

        $validated = $request->validate([
            'api_name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:255',
            'api_key' => 'sometimes|string|max:255',
            'balance' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'convention_rate' => 'sometimes|numeric|min:0.01',
            'status' => 'sometimes|boolean',
            'description' => 'nullable|string',
        ]);

        $provider->update($validated);
        return response()->json($provider);
    }

    public function destroy($id)
    {
        $provider = ApiProvider::findOrFail($id);
        $provider->delete();
        return response()->json(null, 204);
    }

    public function updateStatus(Request $request, $id)
    {
        $provider = ApiProvider::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|boolean',
        ]);

        $provider->update(['status' => $validated['status']]);
        return response()->json($provider);
    }
    // public function index()
    // {
    //     $providers = ApiProvider::all();

    //     return response()->json([
    //         'message' => 'API Providers fetched successfully.',
    //         'data' => $providers
    //     ], 200);
    // }



    // public function store(Request $request)
    // {
    //     $data = $request->all();

    //     // Clean ONLY description field (if present)
    //     if (isset($data['description'])) {
    //         $data['description'] = Purifier::clean($data['description']);
    //     }

    //     $rules = [
    //         'api_name'         => 'required|string',
    //         'api_key'          => 'required|string',
    //         'url'              => 'required|url',
    //         'convention_rate'  => 'required|numeric',
    //         'status'           => 'required|in:0,1',
    //         'description'      => 'nullable|string',
    //     ];

    //     $validator = Validator::make($data, $rules);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Validation failed.',
    //             'errors'  => $validator->errors(),
    //         ], 422);
    //     }

    //     try {
    //         // Convert numeric fields to proper types
    //         $conventionRate = (float) $data['convention_rate'];
    //         $status = (int) $data['status'];

    //         $response = Http::asForm()->post($data['url'], [
    //             'key'    => $data['api_key'],
    //             'action' => 'balance',
    //         ]);

    //         $balanceData = $response->json();

    //         if (isset($balanceData['error'])) {
    //             return response()->json([
    //                 'message' => 'Failed to retrieve balance.',
    //                 'error'   => $balanceData['error'],
    //             ], 400);
    //         }

    //         if (!isset($balanceData['balance']) || !isset($balanceData['currency'])) {
    //             return response()->json([
    //                 'message' => 'Invalid API response. Missing balance or currency.',
    //             ], 400);
    //         }

    //         $provider = new ApiProvider();
    //         $provider->api_name        = $data['api_name'];
    //         $provider->api_key         = $data['api_key'];
    //         $provider->url             = $data['url'];
    //         $provider->balance         = $balanceData['balance'];
    //         $provider->currency        = $balanceData['currency'];
    //         $provider->convention_rate = $conventionRate; // Use converted float
    //         $provider->status          = $status;         // Use converted integer
    //         $provider->description     = $data['description'] ?? null;

    //         $provider->save();

    //         return response()->json([
    //             'message' => 'API provider added successfully.',
    //             'data'    => $provider
    //         ], 201);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Connection to API failed.',
    //             'error'   => $e->getMessage()
    //         ], 500);
    //     }
    // }


    // public function getApiServices(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'api_provider_id' => 'required|exists:api_providers,id'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'Validation failed.',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     $provider = ApiProvider::find($request->api_provider_id);

    //     try {
    //         $response = Http::asForm()->post($provider->url, [
    //             'key' => $provider->api_key,
    //             'action' => 'services'
    //         ]);

    //         if ($response->failed()) {
    //             return response()->json([
    //                 'message' => 'Failed to fetch services.',
    //                 'error' => $response->body()
    //             ], $response->status());
    //         }

    //         return response()->json([
    //             'message' => 'Services fetched successfully.',
    //             'data' => $response->json()
    //         ], 200);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'Error connecting to API provider.',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }





    public function importMulti(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'provider' => 'required|integer|exists:api_providers,id',
            'price_percentage_increase' => 'required|numeric|min:0',
            'import_quantity' => 'required|in:all,selectItem,partial',
            'selectService' => 'required_if:import_quantity,selectItem|array',
            'selectService.*' => 'integer',
            'limit' => 'required_if:import_quantity,partial|integer|min:1',
            'default_service_type' => 'sometimes|string',
            'default_link' => 'sometimes|url',
            'default_username' => 'sometimes|string'
        ]);

        // Add custom validation for price limits
        $validator->after(function ($validator) use ($request) {
            if ($request->price_percentage_increase > 1000) {
                $validator->errors()->add('price_percentage_increase', 'Price increase percentage is too high');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $provider = ApiProvider::findOrFail($data['provider']);
        $maxPrice = 999999999.99; // Define max price here

        try {
            // Fetch services from provider API
            $response = Http::asForm()->post($provider->url, [
                'key' => $provider->api_key,
                'action' => 'services'
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to fetch services from provider API',
                    'error' => $response->body()
                ], 502);
            }

            $apiServices = $response->json();

            // Filter services if needed
            if ($data['import_quantity'] === 'selectItem' && !empty($data['selectService'])) {
                $apiServices = array_filter($apiServices, function ($service) use ($data) {
                    return in_array($service['service'], $data['selectService']);
                });
            }

            // Limit services if partial import
            if ($data['import_quantity'] === 'partial' && isset($data['limit'])) {
                $apiServices = array_slice($apiServices, 0, $data['limit']);
            }

            $importCount = 0;
            $skippedCount = 0;
            $priceAdjustedCount = 0;

            foreach ($apiServices as $apiService) {
                // Find or create category
                $category = Category::firstOrCreate(
                    ['category_title' => $apiService['category'] ?? 'Uncategorized'],
                    ['status' => 1]
                );

                // Check if service already exists
                $exists = Service::where([
                    'api_provider_id' => $provider->id,
                    'api_service_id' => $apiService['service']
                ])->exists();

                if ($exists) {
                    $skippedCount++;
                    continue;
                }

                // Calculate price with bounds checking
                $baseRate = floatval($apiService['rate'] ?? 0);
                $percentageIncrease = floatval($data['price_percentage_increase']);
                $conventionRate = floatval($provider->convention_rate);

                $increased_price = ($baseRate * $percentageIncrease) / 100;
                $final_price = ($baseRate + $increased_price) * $conventionRate;

                // Cap the price at maximum value
                $priceAdjusted = false;

                if ($final_price > $maxPrice) {
                    $final_price = $maxPrice;
                    $priceAdjusted = true;
                    $priceAdjustedCount++;
                }

                // Create service with all fields
                $serviceData = [
                    'service_title' => $apiService['name'] ?? 'Untitled Service',
                    'category_id' => $category->id,
                    'link' => $data['default_link'] ?? $apiService['link'] ?? null,
                    'username' => $data['default_username'] ?? $apiService['username'] ?? null,
                    'min_amount' => intval($apiService['min'] ?? 0),
                    'max_amount' => intval($apiService['max'] ?? 0),
                    'average_time' => $apiService['average_time'] ?? $apiService['time'] ?? null,
                    'description' => $apiService['desc'] ?? $apiService['description'] ?? null,
                    'rate_per_1000' => min(floatval($apiService['rate_per_1000'] ?? $baseRate), $maxPrice),
                    'price' => $final_price,
                    'price_percentage_increase' => $percentageIncrease,
                    'service_status' => 1,
                    'service_type' => $data['default_service_type'] ?? $apiService['type'] ?? 'default',
                    'api_provider_id' => $provider->id,
                    'api_service_id' => $apiService['service'],
                    'api_provider_price' => min($baseRate, $maxPrice),
                    'drip_feed' => $apiService['dripfeed'] ?? $apiService['drip_feed'] ?? 0,
                    'refill' => $apiService['refill'] ?? false,
                    'is_refill_automatic' => $apiService['is_refill_automatic'] ?? false
                ];

                Service::create($serviceData);
                $importCount++;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Services imported successfully',
                'imported_count' => $importCount,
                'skipped_count' => $skippedCount,
                'price_adjusted_count' => $priceAdjustedCount,
                'total_processed' => count($apiServices),
                'max_price_limit' => $maxPrice
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import services',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
