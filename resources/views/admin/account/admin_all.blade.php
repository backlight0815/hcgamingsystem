@extends('admin.admin_master')
@section('admin')
@section('Title', 'Admin  Management  | HC Gaming Studio')

@php
    $referralCode = 'REFERRAL_CODE_HERE'; // Replace with your fixed referral code
    $registrationURL = route('register', ['referral_code' => $referralCode]);
@endphp
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">

    <!-- DataTables CSS -->
 <link rel="stylesheet" href="https://cdn.datatables.net/1.11.6/css/jquery.dataTables.min.css">

 <!-- jQuery -->
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

 <!-- DataTables JS -->
 <script src="https://cdn.datatables.net/1.11.6/js/jquery.dataTables.min.js"></script>

 <!-- Add the Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

<!-- Add jQuery -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!-- Add the Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.min.js"></script>
 </head>


    <style>
.empty-cart-btn {
        background-color: black;
        color: white;
        float: right;
        margin-right:15px;
    }

    @media screen and (max-width: 768px) {
     .table-responsive {
         overflow-x: auto;
     }
 }
 .table-container {
        overflow-x: auto; /* Enable horizontal scrolling */
        max-width: 100%; /* Ensure the container takes full width of the parent */
    }

    /* Ensure table cells don't wrap and add padding for better readability */
    #datatable th,
    #datatable td {
        white-space: nowrap;
        padding: 8px;
    }
        </style>


 <title>@yield('Title')</title>
</head>


<script src="assets/libs/pdfmake/build/pdfmake.min.js"></script>

<div class="page-content">
    <div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">Admin Management</h4>

<a href="#" class="btn btn-danger btn-rounded waves-effect waves-light empty-cart-btn">Invite New Admin</a>


            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="breadcrumb">
        @foreach ($breadcrumbData as $breadcrumb)
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
            @if (!$loop->last)
                <span> / </span>
            @endif
        @endforeach
    </div>
            <div class="row">
                <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="card-title mb-2" > All Admin Data</h4>

                    <div class="row text-center " >

                        <div class="row">
                            <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                                <h5 class="mb-0">{{ $adminCount }}</h5>
                                <p class="text-muted text-truncate">Total Account Registered</p>
                            </div>

                            <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                                <h5 class="mb-0">{{ $activeCount }}</h5>
                                <p class="text-muted text-truncate">Account Activated</p>
                            </div>

                            <div class="col-md-4 col-sm-12 border border-dark pt-3 mb-3">
                                <h5 class="mb-0">{{ $suspendedCount }}</h5>
                                <p class="text-muted text-truncate">Account Suspended</p>
                            </div>
                        </div>
                    </div>
<div class="table-responsive">
                    <table id="datatable" class="table table-bordered" style="border-collapse: collapse; border-spacing: 0; width: 100%;">
                        <input type="hidden" id="dynamicReferralCode" value="{{ Auth::user()->referral_code ?? '' }}">

                        <thead>
                        <tr>

                            <th>SI</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            {{-- <th>Role</th> --}}
                            <th>Upline</th>
                            <th>Registered At</th>
                            <th>Status</th>
                            <th>Action</th>

                        </tr>
                        </thead>



                        <tbody>
                            @php($i=1)
                            @foreach($admins as $item)

                            <tr>
                                <td>{{ $i++ }}</td>
                            <td>{{ $item->username }}</td>
                            <td>{{ $item->name }}</td>

                            <td>{{ $item->email }} </td>

                            {{-- <td>




                                @if ($item->role_id==1)
                                               Super Admin
                               @elseif($item->role_id==2)
                                         Admin


                                @elseif($item->role_id==350)
                                             Agent
                                 @elseif($item->role_id==700)
                                             Customer
                                @endif
                            </td> --}}




                            <td>
                                @if ($item->upline)
                                    {{ $item->upline->username }}
                                @else
                                    HC Gaming Sdn Bhd
                                @endif
                            </td>
                            <td>{{ $item->created_at }}</td>
                            @if($item->status==1)
                            <td> Active </td>
                            @elseif($item->status==0)
                            <td style="color:red"> Suspended </td>
                            @endif
                            {{-- <td>{{ $item->status }}</td> --}}
                            <td>
                                @auth
                                @if(Auth::user()->id !== $item->id)
                                    <a href="{{ route('edit.admin.account', $item->id) }}" class="" title="Edit Data">
                                        <i class="fas fa-edit lg"></i>
                                    </a>

                                @endif
                            @endauth
                            {{-- <a href="{{ route('delete.account', $item->id) }}" class="" title="Delete Data" id="delete">
                                <i class="fas fa-ban text-danger"></i>
                            </a> --}}
                            </td>

                        </tr>



    @endforeach
                        </tbody>
                    </table>
</div>
                </div>
            </div>
        </div> <!-- end col -->
    </div> <!-- end row -->

    </div> <!-- container-fluid -->
    </div>
{{-- @endif --}}
<!-- Modal -->
<!-- The modal for inviting a new member -->
<div class="modal fade" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="inviteModalLabel">Invite New Admin</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Share this link with the new member:</p>
          <div class="input-group mb-3">
            <input type="text" class="form-control" id="registrationLink" readonly>
            <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn">Copy Link</button>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function() {
        // Function to get URL parameters by name
        function getURLParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // Check if referral_code parameter exists in the URL
        const referralCodeParam = getURLParameter('referral_code');

        if (referralCodeParam) {
            // If referral_code parameter is present, pre-fill the referral code field with its value
            const referralCodeInput = document.getElementById('referral_code');
            referralCodeInput.value = referralCodeParam;

            // Disable the referral code field since it's pre-filled
            referralCodeInput.disabled = true;
        }

        // Show the modal when the "Invite New Member" button is clicked
        $('.empty-cart-btn').on('click', function() {
            // Open the modal
            $('#inviteModal').modal('show');

            // Get the dynamic referral code from the hidden input field
            const dynamicReferralCode = $('#dynamicReferralCode').val();

            // Display the dynamic referral code in the modal content
            $('#referralCodeModal').text(dynamicReferralCode);

            const registrationLink = 'http://127.0.0.1:8000/register?referral_code=' + encodeURIComponent(dynamicReferralCode);

            // Set the value of the input field to the registration link
            $('#registrationLink').val(registrationLink);

            // Hide the referral code field and label after copying the link
            $('#referralCodeField').hide();
            $('#referralCodeLabel').hide();
        });

        // Copy the link to the clipboard when the "Copy Link" button is clicked
        $('#copyLinkBtn').on('click', function() {
            // Select the text inside the input field
            $('#registrationLink').select();

            try {
                // Copy the text to the clipboard
                document.execCommand('copy');

                // Optionally, show a success message to the user
                alert('Link copied to clipboard!');

                // Hide the referral code field and label after copying the link
                $('#referralCodeField').hide();
                $('#referralCodeLabel').hide();
            } catch (err) {
                // If copying to clipboard fails, you can handle the error here
                alert('Failed to copy link to clipboard. Please copy it manually.');

                // Show the referral code field and label again in case of an error
                $('#referralCodeField').show();
                $('#referralCodeLabel').show();
            }
        });

        // Show the referral code field and label again if the user cancels the copy action
        $('#registrationLink').on('input', function() {
            $('#referralCodeField').show();
            $('#referralCodeLabel').show();
        });
    });
</script>
@endsection
