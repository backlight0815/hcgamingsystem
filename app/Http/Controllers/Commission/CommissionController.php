<?php

namespace App\Http\Controllers\Commission;
use Illuminate\Support\Facades\Validator;

USE App\Models\Commission;
use App\Models\CommissionSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class CommissionController extends Controller
{
    public function MyCommission(Request $requst){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My Commission', 'url' => route('My.Commission')],
        ];

        $userId = Auth::user()->id;
    // Retrieve commissions associated with the logged-in user along with related order and downline user details
    // Retrieve commissions associated with the logged-in user along with the downline user details
    $commissions = Commission::where('upline_user_id', $userId)
    ->with(['downlineUser', 'order']) // Assuming 'downlineUser' is the relationship between Commission and User
    ->get();
         // Retrieve the user's e-wallet balance with status 1 (total income)
$totalCommission = Commission::where('upline_user_id',$userId)
->sum('commission_amount');

        return view('agent.commission.mycommission_all', compact('breadcrumbData', 'commissions','totalCommission'));

    }

    public function CommissionTutorial(Request $request){




return view('agent.commission.commissiontutorial');
    }

    public function AllDealerCommission(){
        $breadcrumbData=[
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Dealer Commission', 'url' => route('all.dealer.commission')],

        ];

        $dealercommission = Commission::latest()->get();
        $totalCommission = Commission::sum('commission_amount');


        return view('admin.dealercommission.commission_all',compact('breadcrumbData','dealercommission','totalCommission'));
    }

    public function showCommissionSetupForm()
    {

  // Fetch the commission percentage from the database
    // Fetch the commission percentage from the database
    $commissionSetting = CommissionSetting::first();
    $commissionPercentage = $commissionSetting ? $commissionSetting->percentage : null;
$extra_percentage = $commissionSetting ? $commissionSetting->extra_percentage : null;

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Setup Commission', 'url' => route('admin.commission.setup')],
        ];

        return view('admin.dealercommission.commmission_setup', compact('breadcrumbData','commissionPercentage','extra_percentage'));
    }

    public function saveCommissionSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'commission_percentage' => 'required|numeric|min:0',
            'extra_percentage' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Save or update commission percentage in the database
        $percentage = CommissionSetting::firstOrNew(['id' => 1]); // Assuming there's only one row in the table
        $percentage->percentage = $request->input('commission_percentage');
        $percentage->extra_percentage=$request->input('extra_percentage');
        $percentage->save();

        return redirect()->route('admin.commission.setup')->with('success', 'Commission percentage saved successfully.');
    }


}
