<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!-- User details -->
        @php
        $user = auth()->user();
    $id =Auth::user()->id;
    $adminData = App\Models\User::find($id);
        @endphp


       <!-- User details -->
       <div class="user-profile text-center mt-3">
        <div class="">
            <img src="{{ url('upload/admin_images/' . $adminData->profile_image) }}" onerror="this.src='{{ url('upload/default.jpg') }}'" alt="" class="avatar-md rounded-circle">
        </div>
        <div class="mt-3">
            <h4 class="font-size-16 mb-1">{{ $adminData->username }}</h4>
            <span class="text-muted"><i class="ri-record-circle-line align-middle font-size-14 text-success"></i> Online</span>
        </div>
    </div>
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->

            <ul class="metismenu list-unstyled" id="side-menu">
                <li class="menu-title">Menu</li>
                                           @if($user->role_id !=750)

                <li>
                    <a href="{{ route('home') }}" class="waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house" viewBox="0 0 16 16">
  <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5ZM13 7.207V13.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V7.207l5-5 5 5Z"/>
</svg>
                        {{-- <span class="badge rounded-pill bg-success float-end">3</span> --}}
                        <span>E-Store Page</span>
                    </a>
                </li>
@endif
                <li>
                    <a href="{{ route('all.statistics') }}" class="waves-effect">
                        <i class="ri-dashboard-line"></i>
                        {{-- <span class="badge rounded-pill bg-success float-end">3</span> --}}
                        <span>Dashboard</span>
                    </a>
                </li>
@if($user->role_id == 1 || $user->role_id == 2)

<li>
    <a href="javascript:void(0);" class="has-arrow waves-effect">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-toggle2-on" viewBox="0 0 16 16">
            <path d="M9 4a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
            <path d="M1 8a4 4 0 0 1 4-4h6a4 4 0 1 1 0 8H5a4 4 0 0 1-4-4z"/>
        </svg>
        <span>Company Setting</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('admin.features.index') }}">Manage Feature Toggles</a></li>
        <li><a href="{{ route('all.features') }}">Manage Feature</a></li>

        <li><a href="{{ route('all.roles') }}">Manage Role</a></li>

    </ul>
</li>

@endif


                @if($user->role_id == 1 ||$user->role_id==2)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-checklist" viewBox="0 0 16 16">
                            <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/>
                            <path d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0zM7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0z"/>
                        </svg>
                                                  <span>Order Centre</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('all.shipping.order') }}"> Shipping Order</a></li>
                    </ul>
                </li>
                @endif




                @if($user->role_id == 350 ||$user->role_id == 700)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <i class="ri-mail-send-line"></i>
                        <span>Place an Order</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('my.stock') }}">Product Catalogue</a></li>
                    </ul>
                </li>
                @endif



                @if($user->role_id == 350 ||$user->role_id == 700)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <i class="ri-mail-send-line"></i>
                        <span>Financial Centre</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('My.Wallet') }}">My E-Wallet</a></li>
                    </ul>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('My.Wallet.History') }}">My E-Wallet History</a></ul>

                </li>
                @endif


<!--Commission-->
@if($user->role_id == 350)

<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <i class="ri-mail-send-line"></i>
        <span>Commission Centre</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('My.Commission') }}">My Commission</a></li>
    </ul>

@endif










<!-- Admin Financial Report-->

@if($user->role_id == 1 ||$user->role_id == 2)

<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <i class="ri-mail-send-line"></i>
        <span>Financial Centre</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('all.dealer.wallets') }}"> Topup E-Wallet Request</a></li>
    </ul>
</li>
@endif
<!-- Trader Centre -->
@if(in_array($user->role_id, [1, 2, 750]))
<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <i class="ri-mail-send-line"></i>
        <span>Trader Centre</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('all.trading.journals') }}">Trading Journal</a></li>
        <li><a href="{{ route('all.trading.pairs') }}">Trading Pair</a></li>

        @if(in_array($user->role_id, [1, 2]))
            <li><a href="{{ route('admin.trader.journals.index') }}">Traders Performance</a></li>
        @endif
    </ul>
</li>
@endif





<!-- Admin Commission Report-->

@if($user->role_id == 1 ||$user->role_id == 2)

<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <i class="ri-mail-send-line"></i>
        <span>Commission Centre</span>
    </a>

    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('admin.commission.setup') }}">Commission Setup</a></li>
    </ul>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('all.dealer.commission') }}">Dealer Commission</a></li>
    </ul>
</li>
@endif


                @if($user->role_id == 350 ||$user->role_id == 700)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cart" viewBox="0 0 16 16">
                            <path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM3.102 4l1.313 7h8.17l1.313-7H3.102zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                          </svg>                        <span>My Orders</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('my.shipping.order') }}">My Shipping Order</a></li>
                    </ul>
                </li>
                @endif

{{-- <p>User Role ID: {{ $user->role_id }}</p> --}}

@if($user->role_id == 1 ||$user->role_id== 2)


                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                            <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z"/>
                          </svg>                      <span>Account Management</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('all.account') }}">All Account Details</a></li>
                                       @if($user->role_id == 1)
            <li><a href="{{ route('all.admin.account') }}">Admin Management</a></li>
        @endif
      <li><a href="{{ route('all.agent.account') }}">Agent Management</a></li>
                        <li><a href="{{ route('all.customer.account') }}">Customer Management</a></li>

                        <li><a href="{{ route('all.traders.account') }}">Traders Management</a></li>

                    </ul>
                </li>

                @endif



<!--Event Management-->
@if($user->role_id == 1 ||$user->role_id== 2)


<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
            <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z"/>
          </svg>                      <span>Event Management</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">
        <li><a href="{{ route('all.events') }}">All Event Details</a></li>



    </ul>
</li>

@endif




                @if($user->role_id == 350)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
                            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                          </svg>                            <span>Recruitment Centre</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">

                        {{-- <li><a href="">Customer Management</a></li> --}}
                        <li><a href="{{ route('all.agent') }}">Agent Management</a></li>
                        <li><a href="{{ route('all.customer') }}">Customer Management</a></li>


                    </ul>
                </li>
                @endif

                @if($user->role_id == 1 ||$user->role_id==2)

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-diagram-2" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H11a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 5 7h2.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM3 11.5A1.5 1.5 0 0 1 4.5 10h1A1.5 1.5 0 0 1 7 11.5v1A1.5 1.5 0 0 1 5.5 14h-1A1.5 1.5 0 0 1 3 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 9 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/>
                          </svg>                        <span>Product Centre</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">

                        <li><a href="{{ route('all.product') }}">Product Management</a></li>
                        <li><a href="{{ route('all.product.category') }}">Category Management</a></li>


                    </ul>
                </li>
@endif




@if($user->role_id==350)

<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-diagram-2" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H11a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 5 7h2.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM3 11.5A1.5 1.5 0 0 1 4.5 10h1A1.5 1.5 0 0 1 7 11.5v1A1.5 1.5 0 0 1 5.5 14h-1A1.5 1.5 0 0 1 3 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 9 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/>
          </svg>                        <span>My Stock Centre</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">

        <li><a href="{{ route('all.dealer.products') }}">Stock Management</a></li>
        <li><a href="{{ route('all.dealer.product.category') }}">Product Category Management</a></li>



    </ul>
</li>
@endif



@if($user->role_id==350)

<li>
    <a href="javascript: void(0);" class="has-arrow waves-effect">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-diagram-2" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M6 3.5A1.5 1.5 0 0 1 7.5 2h1A1.5 1.5 0 0 1 10 3.5v1A1.5 1.5 0 0 1 8.5 6v1H11a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-1 0V8h-5v.5a.5.5 0 0 1-1 0v-1A.5.5 0 0 1 5 7h2.5V6A1.5 1.5 0 0 1 6 4.5v-1zM8.5 5a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1zM3 11.5A1.5 1.5 0 0 1 4.5 10h1A1.5 1.5 0 0 1 7 11.5v1A1.5 1.5 0 0 1 5.5 14h-1A1.5 1.5 0 0 1 3 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1zm4.5.5a1.5 1.5 0 0 1 1.5-1.5h1a1.5 1.5 0 0 1 1.5 1.5v1a1.5 1.5 0 0 1-1.5 1.5h-1A1.5 1.5 0 0 1 9 12.5v-1zm1.5-.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5h-1z"/>
          </svg>                        <span>Order Centre</span>
    </a>
    <ul class="sub-menu" aria-expanded="false">

        <li><a href="{{ route('all.dealers.shipping.orders') }}"> Shipping Order</a></li>

    </ul>
</li>
@endif



                @if($user->role_id != 750)

<li>
    <a href="{{ route('Sales.Performances') }}" class="waves-effect">
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trophy-fill" viewBox="0 0 16 16">
  <path d="M2.5.5A.5.5 0 0 1 3 0h10a.5.5 0 0 1 .5.5c0 .538-.012 1.05-.034 1.536a3 3 0 1 1-1.133 5.89c-.79 1.865-1.878 2.777-2.833 3.011v2.173l1.425.356c.194.048.377.135.537.255L13.3 15.1a.5.5 0 0 1-.3.9H3a.5.5 0 0 1-.3-.9l1.838-1.379c.16-.12.343-.207.537-.255L6.5 13.11v-2.173c-.955-.234-2.043-1.146-2.833-3.012a3 3 0 1 1-1.132-5.89A33.076 33.076 0 0 1 2.5.5zm.099 2.54a2 2 0 0 0 .72 3.935c-.333-1.05-.588-2.346-.72-3.935zm10.083 3.935a2 2 0 0 0 .72-3.935c-.133 1.59-.388 2.885-.72 3.935z"/>
</svg>        {{-- <span class="badge rounded-pill bg-success float-end">3</span> --}}
        <span>Sales Performances</span>
    </a>
</li>
@endif


@if($user->role_id == 1)

                <li class="menu-title">E-Store Pages</li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-house-add" viewBox="0 0 16 16">
                            <path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h4a.5.5 0 1 0 0-1h-4a.5.5 0 0 1-.5-.5V7.207l5-5 6.646 6.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293L8.707 1.5Z"/>
                            <path d="M16 12.5a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Zm-3.5-2a.5.5 0 0 0-.5.5v1h-1a.5.5 0 0 0 0 1h1v1a.5.5 0 1 0 1 0v-1h1a.5.5 0 1 0 0-1h-1v-1a.5.5 0 0 0-.5-.5Z"/>
                          </svg>                        <span>Home Slide Setup</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('home.slide') }}">Home Slide</a></li>
                    </ul>
                </li>


                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
                            <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                            <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                          </svg>                        <span>About Page Setup</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('about.page') }}">About Page</a></li>
                        <li><a href="{{ route('all.skill') }}">Skill Setup</a></li>
                        <li><a href="{{ route('all.acknowledgement') }}">Acknowledgement Setup</a></li>
                        <li><a href="{{ route('all.education') }}">Education Setup</a></li>

                        <li><a href="{{ route('about.multi.image') }}">About Multi Image</a></li>
                        <li><a href="{{ route('all.multi.image') }}">All Multi Image</a></li>

                    </ul>
                </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
                            <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                            <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                          </svg>                        <span>Service Page Setup</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">

                        <li><a href="{{ route('all.service') }}">All Service</a></li>
                        <li><a href="{{ route('add.service') }}">Add Service</a></li>


                    </ul>
                </li>
                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16">
                            <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h13zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2h-13z"/>
                            <path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8zm0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5zm-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zM4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0zm0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0z"/>
                          </svg>                        <span>Portfolio Page Setup</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">

                        <li><a href="{{ route('all.portfolio') }}">All Portfolio</a></li>
                        <li><a href="{{ route('add.portfolio') }}">Add Portfolio</a></li>


                    </ul>
                </li>

        <li>
            <a href="javascript: void(0);" class="has-arrow waves-effect">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bookmark-fill" viewBox="0 0 16 16">
                    <path d="M2 2v13.5a.5.5 0 0 0 .74.439L8 13.069l5.26 2.87A.5.5 0 0 0 14 15.5V2a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"/>
                  </svg>                <span>Blog Category</span>
            </a>
            <ul class="sub-menu" aria-expanded="false">
                <li><a href="{{ route('all.blog.category') }}">All Blog Category</a></li>
                <li><a href="{{ route('add.blog.category') }}">Add Blog Category</a></li>

            </ul>
        </li>

                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-journal-check" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M10.854 6.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 8.793l2.646-2.647a.5.5 0 0 1 .708 0z"/>
                            <path d="M3 0h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2v-1h1v1a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v1H1V2a2 2 0 0 1 2-2z"/>
                            <path d="M1 5v-.5a.5.5 0 0 1 1 0V5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0V8h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1zm0 3v-.5a.5.5 0 0 1 1 0v.5h.5a.5.5 0 0 1 0 1h-2a.5.5 0 0 1 0-1H1z"/>
                          </svg>                        <span>Blog Page</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('all.blog') }}">All Blog</a></li>
                        <li><a href="{{ route('add.blog') }}">Add Blog</a></li>

                    </ul>
                </li>

                {{-- <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <i class="ri-profile-line"></i>
                        <span>Footer Page Setup</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('footer.setup') }}">Footer Setep</a></li>

                    </ul>
                </li> --}}


                <li>
                    <a href="javascript: void(0);" class="has-arrow waves-effect">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope-paper-fill" viewBox="0 0 16 16">
                            <path fill-rule="evenodd" d="M6.5 9.5 3 7.5v-6A1.5 1.5 0 0 1 4.5 0h7A1.5 1.5 0 0 1 13 1.5v6l-3.5 2L8 8.75l-1.5.75ZM1.059 3.635 2 3.133v3.753L0 5.713V5.4a2 2 0 0 1 1.059-1.765ZM16 5.713l-2 1.173V3.133l.941.502A2 2 0 0 1 16 5.4v.313Zm0 1.16-5.693 3.337L16 13.372v-6.5Zm-8 3.199 7.941 4.412A2 2 0 0 1 14 16H2a2 2 0 0 1-1.941-1.516L8 10.072Zm-8 3.3 5.693-3.162L0 6.873v6.5Z"/>
                          </svg>                        <span>Contact Message</span>
                    </a>
                    <ul class="sub-menu" aria-expanded="false">
                        <li><a href="{{ route('contact.message') }}">Contact Message</a></li>

                    </ul>
                </li>



@endif
        </div>
        <!-- Sidebar -->
    </div>
</div>
