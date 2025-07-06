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
        $providers = ApiProvider::all();

        return response()->json([
            'message' => 'API Providers fetched successfully.',
            'data' => $providers
        ], 200);
    }



    public function store(Request $request)
    {
        $data = $request->all();

        // Clean ONLY description field (if present)
        if (isset($data['description'])) {
            $data['description'] = Purifier::clean($data['description']);
        }

        $rules = [
            'api_name'         => 'required|string',
            'api_key'          => 'required|string',
            'url'              => 'required|url',
            'convention_rate'  => 'required|numeric',
            'status'           => 'required|in:0,1',
            'description'      => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            // Convert numeric fields to proper types
            $conventionRate = (float) $data['convention_rate'];
            $status = (int) $data['status'];

            $response = Http::asForm()->post($data['url'], [
                'key'    => $data['api_key'],
                'action' => 'balance',
            ]);

            $balanceData = $response->json();

            if (isset($balanceData['error'])) {
                return response()->json([
                    'message' => 'Failed to retrieve balance.',
                    'error'   => $balanceData['error'],
                ], 400);
            }

            if (!isset($balanceData['balance']) || !isset($balanceData['currency'])) {
                return response()->json([
                    'message' => 'Invalid API response. Missing balance or currency.',
                ], 400);
            }

            $provider = new ApiProvider();
            $provider->api_name        = $data['api_name'];
            $provider->api_key         = $data['api_key'];
            $provider->url             = $data['url'];
            $provider->balance         = $balanceData['balance'];
            $provider->currency        = $balanceData['currency'];
            $provider->convention_rate = $conventionRate; // Use converted float
            $provider->status          = $status;         // Use converted integer
            $provider->description     = $data['description'] ?? null;

            $provider->save();

            return response()->json([
                'message' => 'API provider added successfully.',
                'data'    => $provider
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Connection to API failed.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function getApiServices(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_provider_id' => 'required|exists:api_providers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        $provider = ApiProvider::find($request->api_provider_id);

        try {
            $response = Http::asForm()->post($provider->url, [
                'key' => $provider->api_key,
                'action' => 'services'
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Failed to fetch services.',
                    'error' => $response->body()
                ], $response->status());
            }

            return response()->json([
                'message' => 'Services fetched successfully.',
                'data' => $response->json()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error connecting to API provider.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function import(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'provider' => 'required|integer|exists:api_providers,id',
            'category' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'min' => 'required|integer',
            'max' => 'required|integer',
            'rate' => 'required|numeric',
            'price_percentage_increase' => 'required|numeric',
            'id' => 'required', // API service ID from provider
            'dripfeed' => 'sometimes|boolean',
            'refill' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $provider = ApiProvider::find($data['provider']);

        // Find or create category
        $category = Category::firstOrCreate(
            ['category_title' => $data['category']],
            ['status' => 1]
        );

        // Check if service already exists
        $exists = Service::where([
            'api_provider_id' => $data['provider'],
            'api_service_id' => $data['id']
        ])->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service already exists'
            ], 409);
        }

        // Create new service
        $increased_price = ($data['rate'] * $data['price_percentage_increase']) / 100;
        $final_price = ($data['rate'] + $increased_price) * $provider->convention_rate;

        $service = Service::create([
            'service_title' => $data['name'],
            'category_id' => $category->id,
            'min_amount' => $data['min'],
            'max_amount' => $data['max'],
            'price' => $final_price,
            'price_percentage_increase' => $data['price_percentage_increase'],
            'service_status' => 1,
            'api_provider_id' => $data['provider'],
            'api_service_id' => $data['id'],
            'drip_feed' => $data['dripfeed'] ?? 0,
            'refill' => $data['refill'] ?? false,
            'api_provider_price' => $data['rate']
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Service imported successfully',
            'data' => $service
        ], 201);
    }

    public function importMulti(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'provider' => 'required|integer|exists:api_providers,id',
            'price_percentage_increase' => 'required|numeric|min:0',
            'import_quantity' => 'required|in:all,selectItem,partial',
            'selectService' => 'required_if:import_quantity,selectItem|array',
            'selectService.*' => 'integer',
            'limit' => 'required_if:import_quantity,partial|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();
        $provider = ApiProvider::findOrFail($data['provider']);

        try {
            // Fetch services from provider API using Laravel HTTP client
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

            foreach ($apiServices as $apiService) {
                // Find or create category
                $category = Category::firstOrCreate(
                    ['category_title' => $apiService['category']],
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

                // Calculate price
                $increased_price = ($apiService['rate'] * $data['price_percentage_increase']) / 100;
                $final_price = ($apiService['rate'] + $increased_price) * $provider->convention_rate;

                // Create service
                Service::create([
                    'service_title' => $apiService['name'],
                    'category_id' => $category->id,
                    'min_amount' => $apiService['min'],
                    'max_amount' => $apiService['max'],
                    'price' => $final_price,
                    'price_percentage_increase' => $data['price_percentage_increase'],
                    'service_status' => 1,
                    'api_provider_id' => $provider->id,
                    'api_service_id' => $apiService['service'],
                    'drip_feed' => $apiService['dripfeed'] ?? 0,
                    'refill' => $apiService['refill'] ?? false,
                    'api_provider_price' => $apiService['rate'],
                    'description' => $apiService['desc'] ?? $apiService['description'] ?? null
                ]);

                $importCount++;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Services imported successfully',
                'imported_count' => $importCount,
                'skipped_count' => $skippedCount,
                'total_processed' => count($apiServices)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import services',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
