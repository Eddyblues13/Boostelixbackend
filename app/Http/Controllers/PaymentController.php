<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Initiate a payment (API endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiatePayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:flutterwave,paystack',
        ]);

        try {
            $transactionRef = 'TX_' . uniqid();

            $payment = Transaction::create([
                'user_id' => Auth::id(),
                'transaction_id' => $transactionRef,
                'amount' => $validated['amount'],
                'currency' => Auth::user()->currency,
                'charge' => 0.00,
                'transaction_type' => 'deposit',
                'description' => 'Payment via ' . ucfirst($validated['payment_method']),
                'status' => 'pending',
                'payment_method' => $validated['payment_method'],
            ]);

            $paymentData = [
                'tx_ref' => $transactionRef,
                'amount' => $validated['amount'],
                'currency' => Auth::user()->currency,
                'redirect_url' => config('app.frontend_url') . '/payment/callback',
                'customer' => [
                    'email' => Auth::user()->email,
                    'name' => Auth::user()->first_name,
                ],
                'payment_options' => 'card',
                'meta' => [
                    'user_id' => Auth::id(),
                    'transaction_id' => $payment->id,
                ],
            ];

            $paymentURL = $this->createPaymentLink($validated['payment_method'], $paymentData);

            if (!$paymentURL) {
                throw new \Exception('Failed to generate payment link');
            }

            return response()->json([
                'success' => true,
                'payment_url' => $paymentURL,
                'transaction_id' => $transactionRef,
                'message' => 'Payment initiated successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle payment callback (API endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCallback(Request $request)
    {
        try {
            $transactionId = $request->input('transaction_id');
            $status = $request->input('status');

            if (!$transactionId) {
                throw new \Exception('Missing transaction reference');
            }

            if ($status === 'successful') {
                $verification = $this->verifyFlutterwavePayment($transactionId);

                if (!$verification || $verification['status'] !== 'success') {
                    throw new \Exception('Payment verification failed');
                }

                $txRef = $verification['data']['tx_ref'];
                $payment = Transaction::where('transaction_id', $txRef)->first();

                if (!$payment) {
                    throw new \Exception('Transaction not found');
                }

                $payment->update([
                    'transaction_id' => $verification['data']['id'],
                    'status' => 'completed',
                    'payment_method' => $verification['data']['payment_type'] ?? $payment->payment_method,
                    'meta' => json_encode($verification['data']),
                ]);

                return response()->json([
                    'success' => true,
                    'payment' => $payment,
                    'message' => 'Payment verified successfully',
                ]);
            }

            // Handle cancelled/failed payments
            $txRef = $request->input('tx_ref');
            if ($txRef) {
                $payment = Transaction::where('transaction_id', $txRef)->first();
                if ($payment) {
                    $payment->update(['status' => 'failed']);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment was not successful',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Payment callback failed: ' . $e->getMessage(), [
                'request' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create payment link based on payment method.
     *
     * @param string $method
     * @param array $data
     * @return string|null
     */
    private function createPaymentLink(string $method, array $data): ?string
    {
        switch ($method) {
            case 'flutterwave':
                return $this->createFlutterwavePaymentLink($data);
            case 'paystack':
                return $this->createPaystackPaymentLink($data);
            default:
                return null;
        }
    }

    /**
     * Create a Flutterwave payment link.
     *
     * @param array $data
     * @return string|null
     */
    private function createFlutterwavePaymentLink(array $data): ?string
    {
        try {
            $client = new Client();
            $response = $client->post('https://api.flutterwave.com/v3/payments', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $body = json_decode($response->getBody(), true);

            return $body['status'] === 'success' ? $body['data']['link'] : null;
        } catch (\Exception $e) {
            Log::error('Flutterwave payment link creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a Paystack payment link.
     *
     * @param array $data
     * @return string|null
     */
    private function createPaystackPaymentLink(array $data): ?string
    {
        try {
            $client = new Client();
            $response = $client->post('https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.paystack.secret_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email' => $data['customer']['email'],
                    'amount' => $data['amount'] * 100, // Paystack uses kobo
                    'reference' => $data['tx_ref'],
                    'callback_url' => $data['redirect_url'],
                    'metadata' => $data['meta'],
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            return $body['status'] ? $body['data']['authorization_url'] : null;
        } catch (\Exception $e) {
            Log::error('Paystack payment link creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify Flutterwave payment.
     *
     * @param string $transactionId
     * @return array|null
     */
    private function verifyFlutterwavePayment(string $transactionId): ?array
    {
        try {
            $client = new Client();
            $response = $client->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Flutterwave payment verification failed: ' . $e->getMessage());
            return null;
        }
    }
}
