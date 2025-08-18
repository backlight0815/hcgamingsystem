<?php

namespace App\Http\Controllers\Sales_Performances;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\orders;
use Illuminate\Support\Facades\DB;
use App\Models\Referral;

class SalesController extends Controller
{
    public function SalesPerformances(Request $request){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Sales Performances', 'url' => route('Sales.Performances')],

        ];
        $salesPerformances = orders::with('user')
            ->where('status', '>=', 1)
            ->whereHas('user', function ($query) {
                $query->where('role_id', '!=', 700); // Exclude users with role ID 700
            })
            // Filter by status greater than or equal to 1
            ->selectRaw('user_id, SUM(total_amount) as total_sales')
            ->groupBy('user_id')
            ->orderByDesc('total_sales') // Order by total_amount in descending order
            ->get();

        // Get downline counts similar to the AgentAll method
        $downlineData = Referral::with('agent')
            ->select('upline_user_id', DB::raw('count(*) as downline_count'))
            ->groupBy('upline_user_id')

            ->get();

        $AgentCount = $downlineData->count();

        return view('all.sales_performances.sales_performance', compact('salesPerformances', 'downlineData', 'AgentCount','breadcrumbData'));
    }
}
