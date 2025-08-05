<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\Order;
use App\Models\Service;
use App\Models\ApiProvider;
use App\Models\Transaction;
use App\Mail\CustomMail;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $rules = [
            'category' => 'required|integer|min:1|not_in:0',
            'service' => 'required|integer|min:1|not_in:0',
            'link' => 'required|url',
            'quantity' => 'required|integer',
            'check' => 'required|accepted',
        ];

        if ($request->has('runs') || $request->has('interval')) {
            $rules['runs'] = 'required|integer|min:1';
            $rules['interval'] = 'required|integer|min:1';
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $service = Service::userRate()->find($request->service);
        if (!$service) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found'
            ], 404);
        }

        // $basic = (object) config('basic');
        $quantity = $request->quantity;

        if ($service->drip_feed == 1 && $request->has('runs')) {
            $quantity = $request->quantity * $request->runs;
        }

        if ($quantity < $service->min_amount || $quantity > $service->max_amount) {
            return response()->json([
                'status' => 'error',
                'message' => "Quantity must be between {$service->min_amount} and {$service->max_amount}"
            ], 422);
        }

        $userRate = $service->user_rate ?? $service->price;
        $price = round(($quantity * $userRate) / 1000);

        $user = Auth::user();
        if ($user->balance < $price) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient balance'
            ], 400);
        }

        $order = new Order();
        $order->user_id = $user->id;
        $order->category_id = $request->category;
        $order->service_id = $request->service;
        $order->link = $request->link;
        $order->quantity = $request->quantity;
        $order->status = Order::STATUS_PROCESSING;
        $order->price = $price;
        $order->runs = $request->runs ?? null;
        $order->interval = $request->interval ?? null;
        // $order->start_time = $service->start_time ?? '5-30 minutes';
        // $order->speed = $service->speed ?? '100-1000/hour';
        // $order->avg_time = $service->avg_time ?? '7 hours 43 minutes';
        // $order->guarantee = $service->guarantee ?? '30 days';

        if ($service->api_provider_id) {
            $apiProvider = ApiProvider::find($service->api_provider_id);
            if ($apiProvider) {
                $postData = [
                    'key' => $apiProvider->api_key,
                    'action' => 'add',
                    'service' => $service->api_service_id,
                    'link' => $request->link,
                    'quantity' => $request->quantity
                ];

                if ($request->has('runs')) {
                    $postData['runs'] = $request->runs;
                }

                if ($request->has('interval')) {
                    $postData['interval'] = $request->interval;
                }

                try {
                    $response = Http::asForm()->post($apiProvider->url, $postData);
                    $apiData = $response->json();

                    if (isset($apiData['order'])) {
                        $order->status_description = "order: {$apiData['order']}";
                        $order->api_order_id = $apiData['order'];
                    } else {
                        $order->status_description = "error: {$apiData['error']}";
                        $order->status = Order::STATUS_CANCELLED;
                    }
                } catch (\Exception $e) {
                    $order->status_description = "error: API connection failed";
                    $order->status = Order::STATUS_CANCELLED;
                }
            }
        }

        $order->save();

        $user->balance -= $price;
        $user->save();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => str()->random(20),
            'transaction_type' => 'Debit',
            'amount' => $price,
            'charge' => 0,
            'description' => 'Place order',
            'status' => 'pending',
            'meta' => null,
        ]);

        // Send email using CustomMail
        // $emailBody = view('email.order_confirm', [
        //     'user' => $user,
        //     'order_id' => $order->id,
        //     'order_at' => $order->created_at,
        //     'service' => optional($order->service)->service_title,
        //     'status' => $order->status,
        //     'paid_amount' => $price,
        //     'remaining_balance' => $user->balance,
        //     'currency' => "$",
        //     'transaction' => $transaction->trx_id,
        // ])->render();

        // Mail::to($user->email)->send(new CustomMail(
        //     subject: "Order Confirmation #{$order->id}",
        //     messageBody: $emailBody
        // ));

        return response()->json([
            'status' => 'success',
            'message' => 'Order submitted successfully',
            'order_id' => $order->id,
            'balance' => $user->balance
        ]);
    }



    public function history(Request $request)
    {
        $user = Auth::user();

        $status = $request->input('status', 'all');
        $search = $request->input('search', '');
        $perPage = $request->input('per_page', 15);

        $query = Order::with(['service:id,name', 'category:id,name'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Search functionality
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('link', 'like', "%{$search}%")
                    ->orWhereHas('service', function ($serviceQuery) use ($search) {
                        $serviceQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_id' => 'ORD' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                    'date' => $order->created_at->format('Y-m-d'),
                    'link' => $order->link,
                    'charge' => '₦' . number_format($order->price, 2),
                    'start_count' => number_format($order->start_counter ?? 0),
                    'quantity' => number_format($order->quantity),
                    'service' => $order->service->name ?? 'N/A',
                    'status' => $order->status,
                    'remains' => number_format($order->remains ?? 0),
                    'status_description' => $order->status_description,
                ];
            }),
            'meta' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
            'status_counts' => [
                'all' => Order::where('user_id', $user->id)->count(),
                'pending' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
                'in-progress' => Order::where('user_id', $user->id)->where('status', 'in-progress')->count(),
                'completed' => Order::where('user_id', $user->id)->where('status', 'completed')->count(),
                'partial' => Order::where('user_id', $user->id)->where('status', 'partial')->count(),
                'processing' => Order::where('user_id', $user->id)->where('status', 'processing')->count(),
                'canceled' => Order::where('user_id', $user->id)->where('status', 'canceled')->count(),
            ]
        ]);
    }
}
