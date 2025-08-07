<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\AffiliatePayout;
use App\Models\AffiliateProgram;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AffiliateController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $affiliate = $user->affiliateProgram;

        $response = [
            'has_program' => (bool)$affiliate,
        ];

        if ($affiliate) {
            $response = array_merge($response, [
                'referral_link' => url('/ref/' . $affiliate->referral_code),
                'commission_rate' => $affiliate->commission_rate,
                'minimum_payout' => $affiliate->minimum_payout,
                'stats' => $affiliate->stats ?? $this->emptyStats(),
                'payouts' => $affiliate->payouts()->orderBy('created_at', 'desc')->get()
            ]);
        }

        return response()->json($response);
    }

    public function generateLink(Request $request)
    {
        $user = Auth::user();

        if ($user->affiliateProgram) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an affiliate program'
            ], 400);
        }

        try {
            $affiliate = AffiliateProgram::create([
                'user_id' => $user->id,
                'referral_code' => Str::random(8),
                'commission_rate' => 4.0, // Default commission rate
                'minimum_payout' => 2000.00 // Default minimum payout
            ]);

            // Create stats record with default values
            $affiliate->stats()->create($this->emptyStats());

            return response()->json([
                'success' => true,
                'message' => 'Affiliate program created successfully',
                'referral_link' => url('/ref/' . $affiliate->referral_code),
                'commission_rate' => $affiliate->commission_rate,
                'minimum_payout' => $affiliate->minimum_payout,
                'stats' => $affiliate->stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create affiliate program',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStats()
    {
        $affiliate = Auth::user()->affiliateProgram;

        return response()->json(
            $affiliate ? ($affiliate->stats ?? $this->emptyStats()) : $this->emptyStats()
        );
    }

    public function getPayouts()
    {
        $affiliate = Auth::user()->affiliateProgram;

        return response()->json(
            $affiliate ? $affiliate->payouts()->orderBy('created_at', 'desc')->get() : []
        );
    }

    public function requestPayout(Request $request)
    {
        $user = Auth::user();
        $affiliate = $user->affiliateProgram;

        if (!$affiliate) {
            return response()->json([
                'success' => false,
                'message' => 'No affiliate program found'
            ], 404);
        }

        $stats = $affiliate->stats;

        if ($stats->available_earnings < $affiliate->minimum_payout) {
            return response()->json([
                'success' => false,
                'message' => 'You need at least ₦' . $affiliate->minimum_payout . ' to request a payout',
                'minimum_required' => $affiliate->minimum_payout,
                'current_balance' => $stats->available_earnings
            ], 400);
        }

        try {
            $payout = AffiliatePayout::create([
                'affiliate_program_id' => $affiliate->id,
                'amount' => $stats->available_earnings,
                'status' => 'pending',
                'payment_method' => $request->payment_method ?? 'bank_transfer'
            ]);

            // Reset available earnings
            $stats->update(['available_earnings' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Payout requested successfully',
                'payout' => $payout,
                'new_balance' => 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request payout',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function trackVisit($code)
    {
        $affiliate = AffiliateProgram::where('referral_code', $code)->first();

        if (!$affiliate) {
            return redirect('/'); // Or your homepage
        }

        // Increment visit count
        $affiliate->stats()->increment('visits');

        // Store in session to track if this user signs up
        session(['affiliate_code' => $code]);

        return redirect('/register'); // Your registration page
    }

    protected function emptyStats()
    {
        return [
            'visits' => 0,
            'signups' => 0,
            'available_earnings' => 0,
            'total_earnings' => 0,
            'paid_earnings' => 0
        ];
    }
}
