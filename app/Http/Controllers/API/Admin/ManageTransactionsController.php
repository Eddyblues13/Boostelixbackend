<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ManageTransactionsController extends Controller
{
       /**
     * Get all transactions for a specific user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getUserTransactions($id)
    {
        try {
            $user = User::findOrFail($id);
            $transactions = $user->transactions()->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'User transactions retrieved successfully',
                'data' => $transactions
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
