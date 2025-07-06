<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\UserNotification;

class ManageUserController extends Controller
{
    /**
     * Display a listing of all users.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $users = User::with(['orders', 'transactions'])->latest()->get();
            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $user = User::with(['orders', 'transactions'])->findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $validatedData = $request->validate([
                'firstname' => 'sometimes|string|max:255',
                'lastname' => 'sometimes|string|max:255',
                'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:500',
                'image' => 'sometimes|string',
                'two_fa' => 'sometimes|boolean',
                'language_id' => 'sometimes|integer',
            ]);

            $user->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login as a user (admin impersonation).
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function loginAsUser($id)
    {
        try {
            $user = User::findOrFail($id);
            $token = $user->createToken('AdminImpersonationToken')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Logged in as user successfully',
                'token' => $token,
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to login as user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add balance to user account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addBalance(Request $request, $id)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'notify' => 'sometimes|boolean',
                'remarks' => 'sometimes|string|max:255'
            ]);

            $user = User::findOrFail($id);
            $amount = $request->amount;
            $remarks = $request->remarks ?? 'Balance added by admin';

            // Increment balance
            $user->increment('balance', $amount);

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'trx_type' => '+',
                'amount' => $amount,
                'charge' => 0,
                'remarks' => $remarks,
                'trx_id' => Str::uuid()
            ]);

            // Send notification if requested
            if ($request->notify) {
                $subject = 'Balance Added to Your Account';
                $message = "Your account balance has been increased by $amount. New balance: {$user->balance}";

                Mail::to($user->email)->send(new UserNotification($subject, $message));
            }

            return response()->json([
                'success' => true,
                'message' => 'Balance added successfully',
                'data' => [
                    'user' => $user,
                    'transaction' => $transaction
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reduce balance from user account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function reduceBalance(Request $request, $id)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'notify' => 'sometimes|boolean',
                'remarks' => 'sometimes|string|max:255'
            ]);

            $user = User::findOrFail($id);
            $amount = $request->amount;
            $remarks = $request->remarks ?? 'Balance reduced by admin';

            // Check if user has sufficient balance
            if ($user->balance < $amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has insufficient balance'
                ], 400);
            }

            // Decrement balance
            $user->decrement('balance', $amount);

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'trx_type' => '-',
                'amount' => $amount,
                'charge' => 0,
                'remarks' => $remarks,
                'trx_id' => Str::uuid()
            ]);

            // Send notification if requested
            if ($request->notify) {
                $subject = 'Balance Deducted from Your Account';
                $message = "Your account balance has been reduced by $amount. New balance: {$user->balance}";

                Mail::to($user->email)->send(new UserNotification($subject, $message));
            }

            return response()->json([
                'success' => true,
                'message' => 'Balance reduced successfully',
                'data' => [
                    'user' => $user,
                    'transaction' => $transaction
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reduce balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate user account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function activate($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['status' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate user account.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deactivate($id)
    {
        try {
            $user = User::findOrFail($id);
            $user->update(['status' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function changeStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|integer|in:0,1,2'
            ]);

            $user = User::findOrFail($id);
            $user->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'User status updated successfully',
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new API key for user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function generateApiKey($id)
    {
        try {
            $user = User::findOrFail($id);
            $apiKey = Str::random(60);
            $user->update(['api_token' => $apiKey]);

            return response()->json([
                'success' => true,
                'message' => 'API key generated successfully',
                'data' => [
                    'user_id' => $user->id,
                    'api_key' => $apiKey
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email to a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function sendUserEmail(Request $request, $id)
    {
        try {
            $request->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string'
            ]);

            $user = User::findOrFail($id);

            Mail::to($user->email)->send(new UserNotification(
                $request->subject,
                $request->message
            ));

            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email to all users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendBulkEmail(Request $request)
    {
        try {
            $request->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string'
            ]);

            $users = User::where('status', 1)->get();

            foreach ($users as $user) {
                Mail::to($user->email)->send(new UserNotification(
                    $request->subject,
                    $request->message
                ));
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk email sent successfully to ' . count($users) . ' users'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all orders for a specific user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getUserOrders($id)
    {
        try {
            $user = User::findOrFail($id);
            $orders = $user->orders()->with(['category', 'service'])->latest()->get();

            return response()->json([
                'success' => true,
                'message' => 'User orders retrieved successfully',
                'data' => $orders
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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

    /**
     * Create a new order for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function createUserOrder(Request $request, $id)
    {
        try {
            $request->validate([
                'category_id' => 'required|integer|exists:categories,id',
                'service_id' => 'required|integer|exists:services,id',
                'link' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0.01',
                'status' => 'sometimes|string|max:50',
                'drip_feed' => 'sometimes|boolean',
                'runs' => 'sometimes|integer|min:1',
                'interval' => 'sometimes|integer|min:1'
            ]);

            $user = User::findOrFail($id);

            // Check if user has sufficient balance
            if ($user->balance < $request->price) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has insufficient balance for this order'
                ], 400);
            }

            $orderData = $request->all();
            $orderData['user_id'] = $user->id;
            $orderData['api_order_id'] = Str::random(16);

            $order = Order::create($orderData);

            // Deduct balance if order is created successfully
            if ($order) {
                $user->decrement('balance', $order->price);

                // Create transaction record
                Transaction::create([
                    'user_id' => $user->id,
                    'trx_type' => '-',
                    'amount' => $order->price,
                    'charge' => 0,
                    'remarks' => 'Payment for order #' . $order->id,
                    'trx_id' => Str::uuid()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an order for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @param  int  $orderId
     * @return \Illuminate\Http\Response
     */
    public function updateUserOrder(Request $request, $userId, $orderId)
    {
        try {
            $request->validate([
                'status' => 'sometimes|string|max:50',
                'refill_status' => 'sometimes|string|max:50',
                'status_description' => 'sometimes|string|max:255',
                'reason' => 'sometimes|string|max:255',
                'start_counter' => 'sometimes|integer',
                'remains' => 'sometimes|integer',
                'refilled_at' => 'sometimes|date'
            ]);

            $user = User::findOrFail($userId);
            $order = Order::where('user_id', $user->id)->findOrFail($orderId);

            $order->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an order for a user.
     *
     * @param  int  $userId
     * @param  int  $orderId
     * @return \Illuminate\Http\Response
     */
    public function deleteUserOrder($userId, $orderId)
    {
        try {
            $user = User::findOrFail($userId);
            $order = Order::where('user_id', $user->id)->findOrFail($orderId);

            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a transaction for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function createUserTransaction(Request $request, $id)
    {
        try {
            $request->validate([
                'trx_type' => 'required|string|in:+,-',
                'amount' => 'required|numeric|min:0.01',
                'charge' => 'sometimes|numeric|min:0',
                'remarks' => 'required|string|max:255'
            ]);

            $user = User::findOrFail($id);

            // Update user balance if transaction is created
            if ($request->trx_type === '+') {
                $user->increment('balance', $request->amount);
            } else {
                // Check if user has sufficient balance
                if ($user->balance < $request->amount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User has insufficient balance'
                    ], 400);
                }
                $user->decrement('balance', $request->amount);
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'trx_type' => $request->trx_type,
                'amount' => $request->amount,
                'charge' => $request->charge ?? 0,
                'remarks' => $request->remarks,
                'trx_id' => Str::uuid()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => $transaction
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a transaction for a user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @param  int  $transactionId
     * @return \Illuminate\Http\Response
     */
    public function updateUserTransaction(Request $request, $userId, $transactionId)
    {
        try {
            $request->validate([
                'remarks' => 'sometimes|string|max:255',
                'charge' => 'sometimes|numeric|min:0'
            ]);

            $user = User::findOrFail($userId);
            $transaction = Transaction::where('user_id', $user->id)->findOrFail($transactionId);

            $transaction->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => $transaction
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a transaction for a user.
     *
     * @param  int  $userId
     * @param  int  $transactionId
     * @return \Illuminate\Http\Response
     */
    public function deleteUserTransaction($userId, $transactionId)
    {
        try {
            $user = User::findOrFail($userId);
            $transaction = Transaction::where('user_id', $user->id)->findOrFail($transactionId);

            $transaction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
