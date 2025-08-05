<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{

    /**
     * Initiate a payment with Flutterwave.
     */
    public function initiatePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|',
            'name' => 'required|string|max:255',
            'payment_method' => 'required|string|in:flutterwave,paystack',
            // 'email' => 'required|email|max:255',
        ]);

        // Generate a unique transaction reference.
        $transactionRef = 'TX_' . uniqid();

        // Save transaction details to the database.
        $payment = Transaction::create([
            'user_id' => Auth::id(),
            'transaction_id' => $transactionRef,
            'amount' => $request->amount,
            'charge' => 0.00,
            'transaction_type' => 'deposit',
            'description' => 'Payment via Flutterwave',
            'status' => 'pending',
            // 'meta' => json_encode($paymentData),
        ]);

        // Prepare the data for the Flutterwave API.
        $paymentData = [
            'tx_ref' => $transactionRef,
            'amount' => $request->amount,
            'currency' => $request->currency, // Adjust the currency as needed
            'redirect_url' => url('/welcome'),
            'customer' => [
                'email' => Auth::user()->email,
                'name' =>  Auth::user()->first_name,
            ],
            'payment_options' => 'card', // Add other payment methods if needed
            'meta' => $payment->meta,
        ];

        // Redirect the user to the Flutterwave payment page.
        $paymentURL = $this->createFlutterwavePaymentLink($paymentData);
        if ($paymentURL) {
            return redirect($paymentURL);
        }

        return back()->with('error', 'Error initiating payment. Please try again.');
    }

    /**
     * Create a payment link using Guzzle.
     */
    private function createFlutterwavePaymentLink($data)
    {
        $client = new Client();
        $url = 'https://api.flutterwave.com/v3/payments';

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $data,
            ]);

            $body = json_decode($response->getBody(), true);

            if ($body['status'] === 'success') {
                return $body['data']['link'];
            }
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }

    /**
     * Handle Flutterwave callback.
     */
    public function handleCallback(Request $request)
    {
        $transactionID = $request->transaction_id;

        // Verify the payment.
        $verificationResponse = $this->verifyPayment($transactionID);

        if ($verificationResponse && $verificationResponse['status'] === 'success') {
            // Update the payment record in the database.
            $payment = Transaction::where('transaction_id', $verificationResponse['data']['tx_ref'])->first();

            if ($payment) {
                $payment->update([
                    'transaction_id' => $verificationResponse['data']['id'],
                    'status' => 'successful',
                    'payment_method' => $verificationResponse['data']['payment_type'],
                ]);
            }

            return view('advertiser.success', ['payment' => $payment]);
        }

        return view('advertiser.error');
    }

    /**
     * Verify payment using Guzzle.
     */
    private function verifyPayment($transactionID)
    {
        $client = new Client();
        $url = "https://api.flutterwave.com/v3/transactions/{$transactionID}/verify";

        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.flutterwave.secret_key'),
                    'Content-Type' => 'application/json',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            report($e);
        }

        return null;
    }
}
