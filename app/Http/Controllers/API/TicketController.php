<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TicketController extends Controller
{

public function store(Request $request)
{
    $request->validate([
        'category_id'   => 'required|string|max:191',
        'subject'       => 'required|string|max:191',
        'order_ids'     => 'nullable|string',
        'request_type'  => 'required|string|max:191',
        'message'       => 'nullable|string',
    ]);

    $user = Auth::user();

    $ticket = Ticket::create([
        'user_id'       => $user?->id,
        'name'          => $user?->name ?? null,
        'email'         => $user?->email ?? null,
        'category_id'   => $request->category_id,
        'subject'       => $request->subject,
        'order_ids'     => $request->order_ids,
        'request_type'  => $request->request_type,
        'message'       => $request->message,
        'status'        => 0,
        'last_reply'    => now(),
    ]);

    return response()->json([
        'message' => 'Ticket submitted successfully',
        'ticket'  => $ticket,
    ], 201);
}

}

