<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'currency' => 'required|string',
            'payment_method' => 'required|string',
            'email' => 'required|email',
            'name' => 'required|string',
        ]);

        if ($request->payment_method === 'flutterwave') {
            return $this->initiateFlutterwavePayment($request);
        }

        // Handle other payment methods here
        return response()->json([
            'status' => 'error',
            'message' => 'Payment method not supported'
        ], 400);
    }

    private function initiateFlutterwavePayment(Request $request)
    {
        $transactionID = 'TX-' . time() . '-' . uniqid();

        $paymentData = [
            'tx_ref' => $transactionID,
            'amount' => $request->amount,
            'currency' => Auth::user()->currency,
            'redirect_url' => config('app.url') . '/api/payment/callback',
            'payment_options' => 'card,account,ussd',
            'customer' => [
                'email' => $request->email,
                'name' => $request->name,
            ],
            'customizations' => [
                'title' => config('app.name'),
                'description' => 'Account Funding',
            ],
        ];

        try {
            $client = new Client();
            $response = $client->post('https://api.flutterwave.com/v3/payments', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $paymentData,
            ]);

            $responseData = json_decode($response->getBody(), true);

            if ($responseData['status'] === 'success') {
                // Save transaction to database
                $transaction = Transaction::create([
                    'user_id' => Auth::id(),
                    'transaction_id' => $transactionID,
                    'amount' => $request->amount,
                    'charge' => 0.00,
                    'transaction_type' => 'deposit',
                    'description' => 'Payment via Flutterwave',
                    'status' => 'pending',
                    'meta' => $paymentData,
                ]);
                return response()->json([
                    'status' => 'success',
                    'payment_url' => $responseData['data']['link'],
                    'transaction_id' => $transactionID,
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $responseData['message'] ?? 'Payment initiation failed'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function paymentCallback(Request $request)
    {
        $transactionId = $request->transaction_id;

        try {
            $client = new Client();
            $response = $client->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if ($responseData['status'] === 'success' && $responseData['data']['status'] === 'successful') {
                $transaction = \App\Models\Transaction::where('transaction_id', $responseData['data']['tx_ref'])
                    ->first();

                if ($transaction) {
                    $transaction->update([
                        'status' => 'completed',
                        'meta' => json_encode($responseData['data']),
                    ]);

                    // Credit user's account
                    $transaction->user->increment('balance', $transaction->amount);


                    return redirect(config('app.frontend_url') . '/dashboard?payment=success');
                }
            }

            return redirect(config('app.frontend_url') . '/dashboard?payment=error');
        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/dashboard?payment=error');
        }
    }

    public function paymentHistory(Request $request)
    {
        $transactions = Transaction::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ]);
    }
}
