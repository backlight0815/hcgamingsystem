<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Referral;

class CustomerController extends Controller
{
    public function Customer(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Customer Management', 'url' => route('all.customer')],

        ];
        $id = Auth::user()->id;
  // Get all the customer users with role ID 700 under the logged-in user's upline
  $customerUsers = Referral::where('upline_user_id', $id)
  ->whereHas('agent', function ($query) {
      $query->where('role_id', 700); // Assuming the role_id column represents the role IDs
  })
  ->with('agent')
  ->get();        // $AgentCount = $userData->count();
  $CustomerCount = $customerUsers->count();

        $activeCustomer = $customerUsers->where('agent.status','1')->count();
        $inactiveCustomer = $customerUsers->where('agent.status','0')->count();
        return view('agent.customer_management.customer_all',compact('customerUsers','CustomerCount','activeCustomer','inactiveCustomer','breadcrumbData'));
}//End Method
}
