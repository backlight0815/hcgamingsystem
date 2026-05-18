<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use App\Models\SignalProviderCertificate;
use App\Models\User;
use App\Models\Network;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use phpDocumentor\Reflection\Types\Integer;
use PhpParser\Node\Stmt\TryCatch;
use Image;


class AdminController extends Controller
{
    public function destroy(Request $request)

    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        $notification = array(
            'message' => 'User Logout Successfully',
            'alert-type' => 'success'
        );

        return redirect('/')->with($notification);
    }//End Method


public function Profile(){

$id = Auth::user()->id;
$adminData = User::with('upline')->find($id);
$roleLabels = [
    1 => 'Super Admin',
    2 => 'Admin',
    201 => 'Junior Signal Provider',
    202 => 'Senior Signal Provider',
    350 => 'Agent',
    501 => 'Market Analyst',
    502 => 'Signal Provider Management',
    700 => 'Customer',
    750 => 'Trader',
    760 => 'Leadership',
    770 => 'Recruiter',
];
$accountLevelLabel = Role::where('id', $adminData->role_id)->value('name')
    ?: ($roleLabels[(int) $adminData->role_id] ?? 'Role ' . $adminData->role_id);

$latestCertificate = SignalProviderCertificate::where('user_id', $id)
    ->latest('updated_at')
    ->first();
$publishedCertificate = SignalProviderCertificate::where('user_id', $id)
    ->where('status', SignalProviderCertificate::STATUS_PUBLISHED)
    ->latest('published_at')
    ->first();
$approvedCertificate = SignalProviderCertificate::where('user_id', $id)
    ->where('status', SignalProviderCertificate::STATUS_APPROVED)
    ->latest('approved_at')
    ->first();
$certificateScore = (int) ($adminData->total_score ?? 0);

$certificateReadiness = [
    'label' => 'Not Evaluated Yet',
    'tone' => 'pending',
    'note' => 'Complete trading or signal performance reviews to unlock certificate evaluation.',
];

if ($publishedCertificate) {
    $certificateReadiness = [
        'label' => 'Certified',
        'tone' => 'connected',
        'note' => $publishedCertificate->certificate_type_label . ' published on ' . optional($publishedCertificate->published_at)->format('d M Y') . '.',
    ];
} elseif ($approvedCertificate) {
    $certificateReadiness = [
        'label' => 'Qualified - Pending Publish',
        'tone' => 'qualified',
        'note' => $approvedCertificate->certificate_type_label . ' has been approved and is waiting for publishing.',
    ];
} elseif ($latestCertificate && $latestCertificate->status === SignalProviderCertificate::STATUS_DRAFT) {
    $certificateReadiness = [
        'label' => 'Under Certificate Review',
        'tone' => 'pending',
        'note' => 'A draft certificate exists and is pending administration review.',
    ];
} elseif ($certificateScore >= 85) {
    $certificateReadiness = [
        'label' => 'Expert Evaluation Ready',
        'tone' => 'connected',
        'note' => 'Current evaluation score: ' . $certificateScore . '/100.',
    ];
} elseif ($certificateScore >= 60) {
    $certificateReadiness = [
        'label' => 'Strategy Evaluation Qualified',
        'tone' => 'qualified',
        'note' => 'Current evaluation score: ' . $certificateScore . '/100. Minimum threshold met.',
    ];
} elseif ($certificateScore > 0) {
    $certificateReadiness = [
        'label' => 'Not Qualified Yet',
        'tone' => 'pending',
        'note' => 'Current evaluation score: ' . $certificateScore . '/100. Minimum target is 60/100.',
    ];
}

$agentData = Referral::where('upline_user_id', $id)->get();
$commissionData  = Commission::where('upline_user_id',$id)->get();
// $agentUsername = $agentData?$agentData->agent->username:null;

    // Fetch commission amount for the upline user
    $commissionAmount = $commissionData->sum('commission_amount');

$agentUsernames = $agentData->map(function ($referral) {
    return $referral->agent->username;
});

    // Map prop firm phase into text
    $phaseMapping = [
        1 => 'Phase 1 🟢',
        2 => 'Phase 2 🟡',
        3 => 'HC Funded Traders'
    ];
// Default phase text
    $propFirmPhaseText = $phaseMapping[$adminData->prop_firm_phase] ?? 'N/A';

    // Extra logic for funded_status
    if ($adminData->prop_firm_phase == 3) {
        if ($adminData->funded_status == 0) {
            $propFirmPhaseText .= '⏳';
        }
    }

// $username = $adminData->users->username;
// $uplineUsername = $adminData->parent_user_id->id;
return view('admin.admin_profile_view',compact(
    'adminData',
    'agentData',
    'agentUsernames',
    'commissionAmount',
    'propFirmPhaseText',
    'accountLevelLabel',
    'certificateReadiness',
    'latestCertificate'
));



}//End method





public function ViewUpline($user_id){
    // $user = User::with('upline')->find($user_id);
    // $username = $user->username;
    // $uplineUsername = $user->upline?$user->upline->username:null;


}



public function EditProfile(){
    $id = Auth::user()->id;
    $editData = User::find($id);
    return view('admin.admin_profile_edit',compact('editData'));

}//End Method

public function StoreProfile(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => [
            'required',
            'email',
            'unique:users,email,' . Auth::user()->id,
            // Add the regex validation for allowed email domains
            'regex:/^(?=.*@)(?:(?=.*@(gmail|yahoo|ymail|hotmail|outlook)\.(com|ca)).*)$/i',
        ],
        'username' => 'required|string|max:100|unique:users,username,' . Auth::user()->id,
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Assuming you want to allow image uploads (optional)
    ], [
        'name.required' => 'Name is required.',
        'email.required' => 'Email is required.',
        'email.unique' => 'Email address is already taken.',
        'email.regex' => 'Only Gmail, Yahoo, Ymail, Hotmail, and Outlook email addresses are allowed.',
        'username.required' => 'Username is required.',
        'username.unique' => 'Username is taken.',
    ]);

    $id = Auth::user()->id;
    $data = User::find($id);
    $data->name = $request->name;
    $data->email = $request->email;
    $data->username = $request->username;

    if ($request->hasFile('profile_image')) {
        $file = $request->file('profile_image');
        $name_gen = hexdec(uniqid()).'.'.$file->getClientOriginalExtension();//34343443.jpg
        Image::make($file)->resize(500,500)->save('upload/admin_images/'.$name_gen);

        $data->profile_image = $name_gen;
    }

    $data->save();

    $notification = array(
        'message' => 'Admin Profile Updated Successfully',
        'alert-type' => 'info'
    );

    return redirect()->route('admin.profile')->with($notification);
}

public function ChangePassword(){

    return view('admin.admin_change_password');
}//End Method

public function UpdatePassword(Request $request)
{
    $validateData = $request->validate([
        'oldpassword' => 'required',
        'newpassword' => ['required', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        'confirm_password' => 'required|same:newpassword',
    ], [
        'newpassword.min' => 'The password must be at least 8 characters.',
        'newpassword.regex' => 'Password should contain at least one lowercase letter, one uppercase letter, and one digit.',
        'confirm_password.same' => 'The new password and confirmation password must match.',
    ]);

    $hashedPassword = Auth::user()->password;

    if (Hash::check($request->oldpassword, $hashedPassword)) {
        $user = User::find(Auth::id());
        $user->password = bcrypt($request->newpassword);
        $user->save();

        session()->flash('message', 'Password Updated Successfully');

        return redirect()->back();
    } else {
        session()->flash('message', 'Old Password is not matched');

        return redirect()->back();
    }
}




public function updateStatus($user_id,$status_code){

}//End Methods
}
