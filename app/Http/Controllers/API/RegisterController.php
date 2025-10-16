<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Exception;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        try {
            // ✅ Validate incoming request
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'username' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            // ✅ Get user's IP address (fallback for localhost testing)
            $ip = $request->ip();
            if ($ip === '127.0.0.1' || $ip === '::1') {
                $ip = '8.8.8.8'; // Public test IP (Google)
            }

            // ✅ Default values
            $country = 'Unknown';
            $currency = 'NGN'; // fallback

            // ✅ Get location info using ip-api.com
            $response = Http::get("https://ip-api.com/json/{$ip}");

            if ($response->ok()) {
                $country = $response->json()['country'] ?? 'Unknown';

                // ✅ Country to currency mapping
                $currencyMap = [
                    'Nigeria' => 'NGN',
                    'United States' => 'USD',
                    'United Kingdom' => 'GBP',
                    'Canada' => 'CAD',
                    'India' => 'INR',
                    'Germany' => 'EUR',
                    'France' => 'EUR',
                    // Add more countries as needed
                ];

                $currency = $currencyMap[$country] ?? 'NGN';
            }

            // ✅ Create the user
            $user = User::create([
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'currency' => 'NGN',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful!',
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database error',
                'error' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
