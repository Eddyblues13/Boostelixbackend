<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;

class ManageTicketController extends Controller
{
    // ✅ List all tickets
    public function index()
    {
        $tickets = Ticket::with('user')->latest()->get();

        return response()->json([
            'status' => 'success',
            'tickets' => $tickets,
        ]);
    }

    // ✅ Show a specific ticket
    public function show($id)
    {
        $ticket = Ticket::with('user')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'ticket' => $ticket,
        ]);
    }

    // ✅ Update ticket status
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:0,1,2', // 0=Pending, 1=Answered, 2=Closed
        ]);

        $ticket = Ticket::findOrFail($id);
        $ticket->status = $request->status;
        $ticket->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Ticket status updated successfully',
        ]);
    }

    // ✅ Delete a ticket
    public function destroy($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Ticket deleted successfully',
        ]);
    }
}