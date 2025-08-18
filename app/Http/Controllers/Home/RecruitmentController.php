<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Image;
use Illuminate\Support\Carbon;
use Validator;
use App\Models\Referral;

class RecruitmentController extends Controller
{
    public function Agent(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Agent Management', 'url' => route('all.agent')],

        ];
        $id = Auth::user()->id;
// Get all the customer users with role ID 350 under the logged-in user's upline
$userData = Referral::where('upline_user_id', $id)
->whereHas('agent', function ($query) {
    $query->where('role_id', 350); // Assuming the role_id column represents the role IDs
})->with('agent')->get();
   // Calculate total commission earned by each agent
   $userData->each(function ($user) {
    $user->commission_earned = $user->agent->commissions->sum('commission_amount');
});
        $AgentCount = $userData->count();

        $activeAgent = $userData->where('agent.status','1')->count();
        $inactiveCount = $userData->where('agent.status','0')->count();
        return view('admin.recruitment.agent_all',compact('userData','AgentCount','activeAgent','inactiveCount','breadcrumbData'));
}//End Method

}
