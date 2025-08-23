<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceUpdate;
use Illuminate\Http\Request;

class ManageServiceUpdateController extends Controller
{
    //

    public function UpdateService(Request $request)
{
    // Validate incoming request
    $validated = $request->validate([
        'service'  => 'required|string|max:255',
        'details'  => 'required|string',
        'date'     => 'required|date',
        'update'   => 'required|string|max:255',
        'category' => 'required|string|max:100',
    ]);

    try {
        // Create or update service update
        $serviceUpdate = ServiceUpdate::updateOrCreate(
            ['service' => $validated['service']], // Match by service name
            [
                'details'  => $validated['details'],
                'date'     => $validated['date'],
                'update'   => $validated['update'],
                'category' => $validated['category'],
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'Service update saved successfully.',
            'data'    => $serviceUpdate
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Failed to save service update.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

 /**
     * Get all service update history
     */
    public function ServiceUpdateHistory()
    {
       $updates = ServiceUpdate::orderBy('date', 'desc')->get(); // or paginate if needed
    return response()->json([
        'status' => true,
        'data' => $updates
    ]);
    }
}