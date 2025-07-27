<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Fund;
use App\Models\Order;
use App\Models\ApiProvider;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function dashboard()
    {
        $last30 = date('Y-m-d', strtotime('-30 days'));

        $data['totalAmountReceived'] = Fund::where('status', 1)->sum('amount');
        $data['totalOrder'] = Order::count();
        $data['totalProviders'] = ApiProvider::count();

        $users = User::selectRaw('COUNT(id) AS totalUser')
            ->selectRaw('SUM(balance) AS totalUserBalance')
            ->selectRaw('COUNT((CASE WHEN created_at >= CURDATE() THEN id END)) AS todayJoin')
            ->get()->makeHidden(['fullname', 'mobile'])->toArray();

        $data['userRecord'] = collect($users)->collapse();

        $transactions = Transaction::selectRaw('SUM((CASE WHEN remarks LIKE "DEPOSIT Via%" AND created_at >= ? THEN charge WHEN remarks LIKE "Place order%" AND created_at >= ? THEN amount END)) AS profit_30_days', [$last30, $last30])
            ->selectRaw('SUM((CASE WHEN remarks LIKE "DEPOSIT Via%" AND created_at >= CURDATE() THEN charge WHEN remarks LIKE "Place order%" AND created_at >= CURDATE() THEN amount END)) AS profit_today')
            ->get()->toArray();
        $data['transactionProfit'] = collect($transactions)->collapse();

        $tickets = Ticket::where('created_at', '>', Carbon::now()->subDays(30))
            ->selectRaw('count(CASE WHEN status = 3 THEN status END) AS closed')
            ->selectRaw('count(CASE WHEN status = 2 THEN status END) AS replied')
            ->selectRaw('count(CASE WHEN status = 1 THEN status END) AS answered')
            ->selectRaw('count(CASE WHEN status = 0 THEN status END) AS pending')
            ->get()->toArray();
        $data['tickets'] = collect($tickets)->collapse();

        $orders = Order::where('created_at', '>', Carbon::now()->subDays(30))
            ->selectRaw('count(id) as totalOrder')
            ->selectRaw('count(CASE WHEN status = "completed" THEN status END) AS completed')
            ->selectRaw('count(CASE WHEN status = "processing" THEN status END) AS processing')
            ->selectRaw('count(CASE WHEN status = "pending" THEN status END) AS pending')
            ->selectRaw('count(CASE WHEN status = "progress" THEN status END) AS inProgress')
            ->selectRaw('count(CASE WHEN status = "partial" THEN status END) AS partial')
            ->selectRaw('count(CASE WHEN status = "canceled" THEN status END) AS canceled')
            ->selectRaw('count(CASE WHEN status = "refunded" THEN status END) AS refunded')
            ->selectRaw('COUNT((CASE WHEN created_at >= CURDATE() THEN id END)) AS todaysOrder')
            ->get()->map(function ($value) {
                return [
                    'records' => [
                        'totalOrder' => $value->totalOrder,
                        'todaysOrder' => $value->todaysOrder,
                        'complete' => $value->completed,
                        'processing' => $value->processing,
                        'pending' => $value->pending,
                        'inProgress' => $value->inProgress,
                        'partial' => $value->partial,
                        'canceled' => $value->canceled,
                        'refunded' => $value->refunded,
                    ],
                    'percent' => [
                        'complete' => ($value->completed) ? round(($value->completed / $value->totalOrder) * 100, 2) : 0,
                        'processing' => ($value->processing) ? round(($value->processing / $value->totalOrder) * 100, 2) : 0,
                        'pending' => ($value->pending) ? round(($value->pending / $value->totalOrder) * 100, 2) : 0,
                        'inProgress' => ($value->inProgress) ? round(($value->inProgress / $value->totalOrder) * 100, 2) : 0,
                        'partial' => ($value->partial) ? round(($value->partial / $value->totalOrder) * 100, 2) : 0,
                        'canceled' => ($value->canceled) ? round(($value->canceled / $value->totalOrder) * 100, 2) : 0,
                        'refunded' => ($value->refunded) ? round(($value->refunded / $value->totalOrder) * 100, 2) : 0,
                    ]
                ];
            });

        $data['orders'] = collect($orders)->collapse();

        $data['bestSale'] = Order::with('service')
            ->whereHas('service')
            ->selectRaw('service_id, COUNT(service_id) as count, sum(quantity) as quantity')
            ->groupBy('service_id')->orderBy('count', 'DESC')->take(10)->get();

        $orderStatistics = Order::where('created_at', '>', Carbon::now()->subDays(30))
            ->selectRaw('count(CASE WHEN status = "completed" THEN status END) AS completed')
            ->selectRaw('count(CASE WHEN status = "processing" THEN status END) AS processing')
            ->selectRaw('count(CASE WHEN status = "pending" THEN status END) AS pending')
            ->selectRaw('count(CASE WHEN status = "progress" THEN status END) AS progress')
            ->selectRaw('count(CASE WHEN status = "partial" THEN status END) AS partial')
            ->selectRaw('count(CASE WHEN status = "canceled" THEN status END) AS canceled')
            ->selectRaw('count(CASE WHEN status = "refunded" THEN status END) AS refunded')
            ->selectRaw('DATE_FORMAT(created_at, "%d %b") as date')
            ->orderBy('created_at')
            ->groupBy(DB::raw("DATE(created_at)"))->get();

        $statistics['date'] = [];
        $statistics['completed'] = [];
        $statistics['processing'] = [];
        $statistics['pending'] = [];
        $statistics['progress'] = [];
        $statistics['partial'] = [];
        $statistics['canceled'] = [];
        $statistics['refunded'] = [];
        
        foreach ($orderStatistics as $val) {
            array_push($statistics['date'], trim($val->date));
            array_push($statistics['completed'], ($val->completed != null) ? $val->completed : 0);
            array_push($statistics['processing'], ($val->processing != null) ? $val->processing : 0);
            array_push($statistics['pending'], ($val->pending != null) ? $val->pending : 0);
            array_push($statistics['progress'], ($val->progress != null) ? $val->progress : 0);
            array_push($statistics['partial'], ($val->partial != null) ? $val->partial : 0);
            array_push($statistics['canceled'], ($val->canceled != null) ? $val->canceled : 0);
            array_push($statistics['refunded'], ($val->refunded != null) ? $val->refunded : 0);
        }

        $data['latestUser'] = User::latest()->limit(5)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'statistics' => $statistics
        ]);
    }
}