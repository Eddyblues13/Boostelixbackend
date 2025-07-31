<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Order;
use App\Models\Service;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class ManageOrderController extends Controller
{
    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();

            return response()->json(['message' => 'Order deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Delete Order Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete order'], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->update($request->all());

            return response()->json(['message' => 'Order updated successfully', 'data' => $order]);
        } catch (\Exception $e) {
            Log::error('Update Order Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update order'], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|string',
                'statusDescription' => 'nullable|string',
                'reason' => 'nullable|string',
            ]);

            $order = Order::findOrFail($id);
            $order->status = $request->status;
            $order->status_description = $request->statusDescription;
            $order->status_reason = $request->reason;
            $order->save();

            return response()->json(['message' => 'Order status updated successfully', 'data' => $order]);
        } catch (\Exception $e) {
            Log::error('Update Order Status Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update order status'], 500);
        }
    }

    public function getUserCategories()
    {
        $categories = Category::all();

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }


    public function getUserServices()
    {
        $services = Service::all();

        return response()->json([
            'success' => true,
            'data' => $services
        ]);
    }
}
