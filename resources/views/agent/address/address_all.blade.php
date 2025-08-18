@extends('admin.admin_master')
@section('admin')

<title>My Address | HC Gaming</title>

    <div class="page-content">
        <div class="container-fluid">

            <!-- start page title -->
            <div class="row">
                <div class="col-lg-6">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">My Billing Address</h4>

                        {{-- <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="javascript: void(0);">Forms</a></li>
                                <li class="breadcrumb-item active">Form Mask</li>
                            </ol>
                        </div> --}}

                    </div>
                </div>
            </div>
            <!-- end page title -->

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <h4 class="card-title mb-4">Billing Address</h4>
                            <form method="POST" action="{{ route('update.address') }}" enctype="multipart/form-data">
                                @csrf

                                <input type="hidden" name="id" value="{{ $myaddress->id }}">                                <div class="row">
                                    <div class="col-lg-6">
                                        <div>
                                            <div class="mb-4">
                                                <label class="form-label" for="input-date1">Full Name</label>
                                                <input id="input-date1" class="form-control" value="{{ $myaddress->name }}" name="name" placeholder="Full Name" required>
                                                <span class="text-muted">e.g "John Senna"</span>
                                                <br>
                                                @error('name')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label" for="input-date2">Address Line 1</label>
                                                <input id="input-date2" class="form-control input-mask" value="{{ $myaddress->street }}" name="address_line_1" placeholder="Address Line 1" required>
                                                {{-- <span class="text-muted">e.g "mm/dd/yyyy"</span> --}}
                                                @error('address_line_1')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label" for="input-datetime">Address Line 2</label>
                                                <input id="input-datetime" class="form-control input-mask" value="{{ $myaddress->street_2 }}" name="address_line_2" placeholder="Address Line 2">
                                                {{-- <span class="text-muted">e.g "yyyy-mm-dd'T'HH:MM:ss"</span> --}}
                                                @error('address_line_2')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            {{-- <div class="mb-0">
                                                <label class="form-label" for="input-currency">Currency:</label>
                                                <input id="input-currency" class="form-control input-mask text-left" data-inputmask="'alias': 'numeric', 'groupSeparator': ',', 'digits': 2, 'digitsOptional': false, 'prefix': '$ ', 'placeholder': '0'">
                                                <span class="text-muted">e.g "$ 0.00"</span>
                                            </div> --}}
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mt-4 mt-lg-0">
                                            <div class="mb-4">
                                                <label class="form-label" for="input-repeat">Zipcode:</label>
                                                <input id="input-repeat" class="form-control input-mask" value="{{ $myaddress->zipcode }}" type="number"  name="zipcode" placeholder="Zipcode" required >
                                                <span class="text-muted">e.g "81750"</span>
                                               <br>

                                                @error('zipcode')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label" for="input-mask">Country</label>
                                                <input id="input-mask" class="form-control input-mask" value="{{ $myaddress->city }}" name="country" placeholder="Country" required>
                                                <span class="text-muted">e.g "Malaysia"</span>
                                                <br>
                                                @error('country')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label" for="input-ip">State</label>
                                                <input id="input-ip" class="form-control input-mask" value="{{ $myaddress->state }}" name="state" placeholder="State" required>
                                                <span class="text-muted">e.g "Kuala Lumpur"</span>
                                                <br>
                                                @error('state')
                                                <span class="text-danger">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            {{-- <div class="mb-0">
                                                <label class="form-label" for="input-email">Email address::</label>
                                                <input id="input-email" class="form-control input-mask" data-inputmask="'alias': 'email'">
                                                <span class="text-muted">_@_._</span>
                                            </div> --}}
                                        </div>
                                    </div>
                                </div>
                                <input type="submit" class="btn btn-info waves-effect waves-light" value="Save">

                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end row -->

        </div> <!-- container-fluid -->
    </div>
    <!-- End Page-content -->



<!-- end main content-->


@endsection
