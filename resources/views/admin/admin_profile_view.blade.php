@extends('admin.admin_master')
@section('admin')
<title>My Profile | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-6">
                <div class="card"><br><br>


<center>
    <img class="rounded-circle avatar-xl" src="{{ $adminData->profile_image ? url('upload/admin_images/' . $adminData->profile_image) : url('upload/default.jpg') }}" onerror="this.src='{{ url('upload/default.jpg') }}'"  alt="Personal Profile">
    {{-- <img class="rounded-circle avatar-xl" src="{{ (File::exists(public_path('upload/admin_images/' . $adminData->profile_image))) ? url('upload/admin_images/' . $adminData->profile_image) : url('upload/default.jpg') }}" alt="Personal Profile"> --}}
</center>


                    <div class="card-body">
                        <h4 class="card-title">Name : {{ $adminData->name }}</h4>
                        <hr>
                        <h4 class="card-title">Username : {{ $adminData->username }}</h4>
                         <hr>
                         <h4 class="card-title">Email : {{ $adminData->email }}</h4>

                         <hr>
                        @if($adminData->role_id == 1 || $adminData->role_id == 2)

    <h4 class="card-title">Admin Referral Code : {{ $adminData->referral_code }}</h4>
    <hr>
    <h4 class="card-title">Customer Referral Code : {{ $adminData->customer_referral_code }}</h4>

@elseif($adminData->role_id == 350 && feature_enabled('referral_dealer'))

    <h4 class="card-title">Agent Referral Code : {{ $adminData->referral_code }}</h4>
    <hr>
    <h4 class="card-title">Customer Referral Code : {{ $adminData->customer_referral_code }}</h4>
    <hr>

@endif


                         @if($adminData->role_id==1)
                         <h4 class="card-title">Account Level :Super Admin</h4>
                         @elseif($adminData->role_id==2)
                         <h4 class="card-title">Account Level : Admin</h4>

                         @elseif($adminData->role_id==350)
                         <h4 class="card-title">Account Level : Agent</h4>
                         @elseif($adminData->role_id==700)
                         <h4 class="card-title">Account Level : Customer</h4>
                            @elseif($adminData->role_id==750)
                         <h4 class="card-title">Account Level : Trader</h4>
                         @endif
                         @if($adminData->role_id==350)
                         <hr>

                         <h4 class="card-title">Commission Earn: {{ $commissionAmount }}</h4>
@endif




                         {{-- <hr>
                         <h4 class="card-title">Verified At : {{ $adminData->email_verified_at }}</h4> --}}

                         <hr>
                         <h4 class="card-title">Invited By:
@if($adminData->upline)
                            {{ $adminData->upline->username }}  </h4>
               @else
               HC Gaming Sdn Bhd
               @endif
                            <hr>


                               <hr>

<a href="{{ route('edit.profile') }}" class="btn btn-info btn-rounded waves-effect waves-light" > Edit Profile</a>
                    </div>
                </div>
            </div>




        </div>
        <!-- end row -->


    </div>
</div>





@endsection
