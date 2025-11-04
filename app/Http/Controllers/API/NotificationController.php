<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\GeneralNotification;

class NotificationController extends Controller
{
    /**
     * Display a list of user notifications
     */
   public function index()
    {
        try {
            $notifications = GeneralNotification::where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($id)
    {
        $notification = GeneralNotification::where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read.'
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        GeneralNotification::where('user_id', Auth::id())->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read.'
        ]);
    }

    /**
     * Delete a specific notification
     */
    public function destroy($id)
    {
        $notification = GeneralNotification::where('user_id', Auth::id())->findOrFail($id);
        $notification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification deleted successfully.'
        ]);
    }

    /**
     * Delete all notifications
     */
    public function clearAll()
    {
        GeneralNotification::where('user_id', Auth::id())->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications cleared.'
        ]);
    }
}
