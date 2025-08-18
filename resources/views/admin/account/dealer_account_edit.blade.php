@extends('admin.admin_master')
@section('admin')
<title>Dealer Management - Edit | HC Gaming Studio</title>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>


<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Dealer Management Details</h4>






<form method="POST" action="{{ route('update.agent') }}">
    @csrf
<input type="hidden" name="id" value="{{ $agent_details->id }}">
                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label"> Username</label>
                            <div class="col-sm-10">
                                <input name="account_username" class="form-control" type="text"  id="example-text-input" value="{{ $agent_details->username }}">
                        @error('account_username')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                            </div>

                        </div>
                        <!-- end row -->

                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Name</label>
                            <div class="col-sm-10">
                                <input name="account_name" class="form-control" type="text"  id="example-text-input" value="{{ $agent_details->name }}">

                                @error('account_name')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->



                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Email</label>
                            <div class="col-sm-10">
                                <input name="account_email" class="form-control" type="text"  id="example-text-input" value="{{ $agent_details->email }}">

                                @error('account_email')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->


{{--
                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Referral Code</label>
                            <div class="col-sm-10">
                                <label name="account_referral_code" class="form-control" type="text"  id="example-text-input" value="{{ $account_details->referral_code }}">{{ $account_details->referral_code }}</label>

                                @error('referral_code')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div> --}}
                        <!-- end row -->









                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Status</label>
                            <div class="col-sm-10">

<select class="form-select" aria-label="Default select example"  name="account_status">
    <option value="1"{{ $agent_details->status==1?'selected':'' }}>Active</option>
    <option value="0"{{ $agent_details->status==0?'selected':'' }}>Suspend</option>
</select>

                                {{-- <input name="account_status" class="form-control" type="text"  id="example-text-input" value="{{ $account_details->status }}"> --}}

                                @error('account_status')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->




                        <div class="row mb-3">
                            <label for="example-text-input" class="col-sm-2 col-form-label">Role</label>
                            <div class="col-sm-10">

<select class="form-select" aria-label="Default select example"  name="account_role">
    <option value="2"{{ $agent_details->role_id==2?'selected':'' }}>Admin</option>
    <option value="350"{{ $agent_details->role_id==350?'selected':'' }}>Agent</option>
    <option value="700"{{ $agent_details->role_id==700?'selected':'' }}>Customer</option>
    <option value="750"{{ $agent_details->role_id==750?'selected':'' }}>Traders</option>


</select>

                                {{-- <input name="account_status" class="form-control" type="text"  id="example-text-input" value="{{ $account_details->status }}"> --}}

                                @error('account_role')
                                <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                        <!-- end row -->
<input type="submit" class="btn btn-info waves-effect waves-light" value="Update Account Information">
</form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>


@endsection
