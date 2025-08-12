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

        // Validate payment method configuration
        if ($validated['payment_method'] === 'flutterwave' && empty(config('services.flutterwave.secret_key'))) {
            return response()->json([
                'success' => false,
                'message' => 'Flutterwave payment is not properly configured',
            ], 500);
        }

        if ($validated['payment_method'] === 'paystack' && empty(config('services.paystack.secret_key'))) {
            return response()->json([
                'success' => false,
                'message' => 'Paystack payment is not properly configured',
            ], 500);
        }

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
                'redirect_url' => rtrim(config('app.frontend_url'), '/') . '/payment/callback',
                'customer' => [
                    'email' => Auth::user()->email,
                    'name' => Auth::user()->first_name . ' ' . Auth::user()->last_name,
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
            $txRef = $request->input('tx_ref');
            $status = $request->input('status');

            // Use either transaction_id or tx_ref
            $reference = $transactionId ?? $txRef;

            if (!$reference) {
                throw new \Exception('Missing transaction reference');
            }

            // Find the transaction by either field
            $payment = Transaction::where('transaction_id', $reference)
                ->orWhere('meta->tx_ref', $reference)
                ->first();

            if (!$payment) {
                throw new \Exception('Transaction not found');
            }

            $normalizedStatus = strtolower($status);

            // Handle all possible statuses
            if ($normalizedStatus === 'successful' || $normalizedStatus === 'completed') {
                if ($payment->payment_method === 'flutterwave') {
                    $verification = $this->verifyFlutterwavePayment($reference);

                    if (!$verification || $verification['status'] !== 'success') {
                        throw new \Exception('Payment verification failed');
                    }

                    $payment->update([
                        'transaction_id' => $verification['data']['id'] ?? $reference,
                        'status' => 'completed',
                        'payment_method' => $verification['data']['payment_type'] ?? $payment->payment_method,
                        'meta' => json_encode($verification['data'] ?? []),
                    ]);

                    // Credit user's balance
                    $user = $payment->user;
                    $user->balance += $payment->amount;
                    $user->save();
                } else {
                    // For other payment methods
                    $payment->update(['status' => 'completed']);

                    // Credit user's balance
                    $user = $payment->user;
                    $user->balance += $payment->amount;
                    $user->save();
                }
            } elseif ($status === 'cancelled' || $status === 'failed') {
                $payment->update(['status' => 'failed']);
            } else {
                $payment->update(['status' => 'pending']);
            }

            return response()->json([
                'success' => true,
                'payment' => $payment,
                'message' => 'Payment status updated',
            ]);
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
                throw new \InvalidArgumentException("Unsupported payment method: {$method}");
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
            $flutterwaveKey = config('services.flutterwave.secret_key');

            if (empty($flutterwaveKey)) {
                throw new \RuntimeException('Flutterwave secret key is not configured');
            }

            Log::debug('Attempting Flutterwave payment', ['data' => $data]);

            $client = new Client();
            $response = $client->post('https://api.flutterwave.com/v3/payments', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $flutterwaveKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $body = json_decode((string)$response->getBody(), true);

            if (!isset($body['status']) || $body['status'] !== 'success') {
                Log::error('Flutterwave payment failed', ['response' => $body]);
                throw new \RuntimeException($body['message'] ?? 'Flutterwave payment failed');
            }

            return $body['data']['link'] ?? null;
        } catch (\Exception $e) {
            Log::error('Flutterwave payment error: ' . $e->getMessage());
            throw $e;
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
            $paystackKey = config('services.paystack.secret_key');

            if (empty($paystackKey)) {
                throw new \RuntimeException('Paystack secret key is not configured');
            }

            $client = new Client();
            $response = $client->post('https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $paystackKey,
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

            if (!$body['status']) {
                Log::error('Paystack payment failed', ['response' => $body]);
                throw new \RuntimeException($body['message'] ?? 'Paystack payment failed');
            }

            return $body['data']['authorization_url'] ?? null;
        } catch (\Exception $e) {
            Log::error('Paystack payment error: ' . $e->getMessage());
            throw $e;
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
            $flutterwaveKey = config('services.flutterwave.secret_key');

            if (empty($flutterwaveKey)) {
                throw new \RuntimeException('Flutterwave secret key is not configured');
            }

            $client = new Client();
            $response = $client->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $flutterwaveKey,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            if (!isset($body['status']) || $body['status'] !== 'success') {
                Log::error('Flutterwave verification failed', ['response' => $body]);
                throw new \RuntimeException($body['message'] ?? 'Payment verification failed');
            }

            return $body;
        } catch (\Exception $e) {
            Log::error('Flutterwave verification error: ' . $e->getMessage());
            throw $e;
        }
    }




    /**
     * Verify a payment (API endpoint).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentCallback(Request $request)
    {
        try {
            $validated = $request->validate([
                'transaction_id' => 'required|string',
                'status' => 'required|string|in:successful,completed,failed,cancelled',
            ]);

            $transaction = Transaction::where('transaction_id', $validated['transaction_id'])
                ->where('user_id', Auth::id())
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            // If already verified, return current status
            if ($transaction->status === 'completed') {
                return response()->json([
                    'success' => true,
                    'data' => $transaction,
                    'message' => 'Payment already verified',
                ]);
            }

            // Update transaction status
            $newStatus = $validated['status'] === 'successful' || $validated['status'] === 'completed'
                ? 'completed'
                : 'failed';

            $transaction->update(['status' => $newStatus]);

            // If successful, credit user's balance
            if ($newStatus === 'completed') {
                $user = Auth::user();
                $user->balance += $transaction->amount;
                $user->save();
            }

            return response()->json([
                'success' => true,
                'data' => $transaction,
                'message' => 'Payment verified successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment history for authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function paymentHistory()
    {
        $transactions = Transaction::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($transactions);
    }
}
