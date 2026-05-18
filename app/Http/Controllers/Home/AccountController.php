<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TradingPositionApplication;
use Illuminate\Http\Request;
use App\Models\User;

use Illuminate\Support\Facades\Auth;
use PDF;
use Image;
use Illuminate\Support\Carbon;
use Validator;



class AccountController extends Controller
{

//     public function __construct()
// {
//     $this->middleware('role:1');
// }
    public function AllAccount(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Account Management', 'url' => route('all.account')],

        ];
        $users =User::count();
        $activeCount = User::where('status','1')->count();
        $suspendedCount = User::where('status','0')->count();

        // $user = User::get();
        $user = User::with(['upline', 'commissions'])->latest()->get();


        return view('admin.account.account_all', compact('user', 'users', 'suspendedCount', 'activeCount','breadcrumbData'));
    }//End Method


    public function UpdateAccount(Request $request){

        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i', // Custom domain check
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);
        $user = auth()->user();
        $user->save();


            $account_id = $request->id;

                User::findOrFail($account_id)->update([
                    'username'=> $request ->account_username,
                    'name'=> $request ->account_name,
                    'email'=> $request ->account_email,

                    // 'referral_code'=>$request->account_referral_code,
                    'status'=> $request->account_status,
                    'role_id'=> $request->account_role,


                ]);

                $notification = array(
                    'message' =>'User Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.account')->with($notification);
            }

public function EditAccount($id){
    $account_details = User::findOrFail($id);
    return view('admin.account.account_edit',compact('account_details'));

}//End Method


public function updateAccountStatus(Request $request){
    $account_id = $request->id;
    $newStatus = $request->input('status');
    $newRole = $request->input('role_id');
    $account = User::findOrFail($account_id);
    $account->status=$newStatus;
    $account->role_id=$newRole;

    $account->save();

    $notification = array(
        'message' =>'User Updated  Successfully',
        'alert-type' => 'success'
    );
    return redirect()->route('all.account')->with($notification);
}//End Methods


    public function AllAdmin() {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Admin Management', 'url' => route('all.admin.account')],
        ];
        $adminCount = User::where('role_id', '2')->count();

        $admins = User::with(['upline', 'commissions'])->where('role_id', '2')->latest()->get();
        $activeCount = $admins->where('status', '1')->count();
        $suspendedCount = $admins->where('status', '0')->count();

        return view('admin.account.admin_all', compact('adminCount','admins','activeCount','suspendedCount','breadcrumbData'));
    }

    public function EditAdminAccount($id){
        $admin_details = User::findOrFail($id);
        return view('admin.account.admin_edit',compact('admin_details'));
    }
    public function UpdateAdmin(Request $request){

        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i', // Custom domain check
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);

        $user = auth()->user();
        $user->save();


            $admin_id = $request->id;

                User::findOrFail($admin_id)->update([
                    'username'=> $request ->account_username,
                    'name'=> $request ->account_name,
                    'email'=> $request ->account_email,

                    // 'referral_code'=>$request->account_referral_code,
                    'status'=> $request->account_status,
                    'role_id' => $request->account_role,



                ]);

                $notification = array(
                    'message' =>'Admin Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.admin.account')->with($notification);
            } //End Methods
            public function updateAdminStatus(Request $request){
                $admin_id = $request->id;
                $newStatus = $request->input('status');
                $newRole = $request->input('role_id');

                $account = User::findOrFail($admin_id);
                $account->status=$newStatus;
                $account->role_id=$newRole;
                $account->save();

                $notification = array(
                    'message' =>'Admin Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.admin.account')->with($notification);
            }//End Methods


  public function AllSignalProvider() {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Signal Provider Management', 'url' => route('all.signal_provider')],
        ];
        $signalProviderRoles = [201, 202, 502];

        $provider = User::with(['upline', 'commissions'])
            ->whereIn('role_id', $signalProviderRoles)
            ->latest()
            ->get();
        $signalProviderCount = $provider->count();
        $activeCount = $provider->where('status', '1')->count();
        $suspendedCount = $provider->where('status', '0')->count();

        return view('admin.account.dealer_account_all', compact('signalProviderCount','provider','activeCount','suspendedCount','breadcrumbData'));
    }

    public function EditSignalProviderAccount($id){
        $agent_details = User::findOrFail($id);
        return view('admin.account.dealer_account_edit', compact('agent_details'));
    }

    public function UpdateSignalProvider(Request $request){
        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i',
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);

        User::findOrFail($request->id)->update([
            'username'=> $request->account_username,
            'name'=> $request->account_name,
            'email'=> $request->account_email,
            'status'=> $request->account_status,
            'role_id'=> $request->account_role,
        ]);

        $notification = array(
            'message' =>'Signal Provider Updated Successfully',
            'alert-type' => 'success'
        );

        return redirect()->route('all.signal_provider')->with($notification);
    }

    public function AllAgent() {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Agent Management', 'url' => route('all.agent.account')],
        ];
        $agentCount = User::where('role_id', '350')->count();

        $agents = User::with(['upline', 'commissions'])->where('role_id', '350')->latest()->get();
        $activeCount = $agents->where('status', '1')->count();
        $suspendedCount = $agents->where('status', '0')->count();

        return view('admin.account.dealer_account_all', compact('agentCount','agents','activeCount','suspendedCount','breadcrumbData'));
    }

    public function EditAgentAccount($id){
        $agent_details = User::findOrFail($id);
        return view('admin.account.dealer_account_edit',compact('agent_details'));
    }

    public function UpdateAgent(Request $request){
        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i', // Custom domain check
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);

        $user = auth()->user();
        $user->save();


            $agent_id = $request->id;

                User::findOrFail($agent_id)->update([
                    'username'=> $request ->account_username,
                    'name'=> $request ->account_name,
                    'email'=> $request ->account_email,

                    // 'referral_code'=>$request->account_referral_code,
                    'status'=> $request->account_status,
                    'role_id'=> $request->account_role,


                ]);

                $notification = array(
                    'message' =>'User Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.agent.account')->with($notification);
            } //End Methods


            public function updateAgentStatus(Request $request){
                $agent_id = $request->id;
                $newStatus = $request->input('status');
                $newRole = $request->input('role_id');

                $account = User::findOrFail($agent_id);
                $account->status=$newStatus;
                $account->role_id=$newRole;

                $account->save();

                $notification = array(
                    'message' =>'Agent Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.agent.account')->with($notification);
            }//End Methods



    public function AllCustomer() {

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Customer Management', 'url' => route('all.customer.account')],
        ];
        $customerCount = User::where('role_id', '700')->count();

        $customers = User::with(['upline', 'commissions'])->where('role_id', '700')->latest()->get();
        $activeCount = $customers->where('status', '1')->count();
        $suspendedCount = $customers->where('status', '0')->count();

        return view('admin.account.customer_account_all', compact('customerCount','customers','activeCount','suspendedCount','breadcrumbData'));
    }
  public function EditCustomerAccount($id){
        $customer_details = User::findOrFail($id);
        return view('admin.account.customer_account_edit',compact('customer_details'));
    }

    public function UpdateCustomer(Request $request){
        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i', // Custom domain check
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);


        $user = auth()->user();
        $user->save();


            $customer_id = $request->id;

                User::findOrFail($customer_id)->update([
                    'username'=> $request ->account_username,
                    'name'=> $request ->account_name,
                    'email'=> $request ->account_email,

                    // 'referral_code'=>$request->account_referral_code,
                    'status'=> $request->account_status,
                    'role_id'=>$request->account_role,

                ]);

                $notification = array(
                    'message' =>'Customer Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.customer.account')->with($notification);
            }

            public function updateCustomerStatus(Request $request){
                $customer_id = $request->id;
                $newStatus = $request->input('status');
                $newRole = $request->input('role_id');

                $account = User::findOrFail($customer_id);
                $account->status=$newStatus;
                $account->role_id=$newRole;

                $account->save();

                $notification = array(
                    'message' =>'Customer Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.customer.account')->with($notification);
            }//End Methods


            
    public function AllTraders() {

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Traders Management', 'url' => route('all.traders.account')],
        ];
        $tradingRoles = TradingPositionApplication::tradingMemberRoles();

        $traders = User::with(['upline', 'commissions', 'latestTraderOnboardingApplication'])
            ->whereIn('role_id', $tradingRoles)
            ->latest()
            ->get();
        $tradersCount = $traders->count();
        $leaderCount = $traders->where('role_id', TradingPositionApplication::ROLE_LEADERSHIP)->count();
        $recruiterCount = $traders->where('role_id', TradingPositionApplication::ROLE_RECRUITER)->count();
        $regularTraderCount = $traders->where('role_id', 750)->count();
        $activeCount = $traders->where('status', '1')->count();
        $suspendedCount = $traders->where('status', '0')->count();

        return view('admin.account.traders_account_all', compact('tradersCount','leaderCount','recruiterCount','regularTraderCount','traders','activeCount','suspendedCount','breadcrumbData'));
    }
  public function EditTradersAccount($id){
        $traders_details = User::findOrFail($id);
        return view('admin.account.traders_account_edit',compact('traders_details'));
    }

    public function UpdateTraders(Request $request){
        $request->validate([
            'account_username' => 'required|unique:users,username,' . $request->id,
            'account_name' => 'required',
            'account_email' => [
                'required',
                'email',
                'unique:users,email,' . $request->id,
                'regex:/@(gmail|ymail|yahoo|hotmail|outlook)\.com$/i', // Custom domain check
            ],
            'account_status' => 'required',
            'account_role' => 'required',
        ], [
            'account_username.required' => 'The username field is required.',
            'account_username.unique' => 'Username is already taken.',
            'account_name.required' => 'The name field is required.',
            'account_email.required' => 'The email field is required.',
            'account_email.email' => 'Please provide a valid email address.',
            'account_email.unique' => 'Email is already taken.',
            'account_email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
            'account_status.required' => 'The status field is required.',
            'account_role.required' => 'The role field is required.',
        ]);


        $user = auth()->user();
        $user->save();


            $traders_id = $request->id;

                User::findOrFail($traders_id)->update([
                    'username'=> $request ->account_username,
                    'name'=> $request ->account_name,
                    'email'=> $request ->account_email,

                    // 'referral_code'=>$request->account_referral_code,
                    'status'=> $request->account_status,
                    'role_id'=>$request->account_role,

                ]);

                $notification = array(
                    'message' =>'Customer Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.traders.account')->with($notification);
            }

            public function updateTradersStatus(Request $request){
                $traders_id = $request->id;
                $newStatus = $request->input('status');
                $newRole = $request->input('role_id');

                $account = User::findOrFail($traders_id);
                $account->status=$newStatus;
                $account->role_id=$newRole;

                $account->save();

                $notification = array(
                    'message' =>'Traders Updated  Successfully',
                    'alert-type' => 'success'
                );
                return redirect()->route('all.traders.account')->with($notification);
            }//End Methods



        


public function DeleteAccount($id){
    $user = User::findOrFail($id);

        // Check if the user being deleted is the currently authenticated user
        if ($user->id === Auth::id()) {
            $notification = array(
                'message' => 'You cannot delete your own account.',
                'alert-type' => 'error'
            );
            return redirect()->back()->with($notification);
        }



    User::findOrFail($id)->delete();


    $notification = array(
        'message' =>'User Deleted Successfully',
        'alert-type' => 'success'
    );
    return redirect()->back()->with($notification);
}//End Method



    }//end Methods
