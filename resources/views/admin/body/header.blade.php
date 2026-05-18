<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <!-- LOGO -->
            <div class="navbar-brand-box">
                <a href="{{ route('all.statistics') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ asset('backend/assets/images/hclogo_black.png') }}" alt="logo-sm" height="62">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('backend/assets/images/hclogo_black.png') }}" alt="logo-dark" height="60">
                    </span>
                </a>

                <a href="{{ route('all.statistics') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ asset('backend/assets/images/hclogo_white.png') }}" alt="logo-sm-light" height="62">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ asset('backend/assets/images/hclogo_white.png') }}" alt="logo-light" height="60">
                    </span>
                </a>
            </div>

            {{-- <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
                <i class="ri-menu-2-line align-middle"></i>
            </button> --}}

            {{-- <!-- App Search-->
            <form class="app-search d-none d-lg-block">
                <div class="position-relative">
                    <input type="text" class="form-control" placeholder="Search...">
                    <span class="ri-search-line"></span>
                </div>
            </form> --}}

        </div>

        <div class="d-flex">
   <button type="button" class="btn btn-sm px-3 font-size-24 header-item waves-effect" id="vertical-menu-btn">
                            <i class="ri-menu-2-line align-middle"></i>
                        </button>



            {{-- <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item noti-icon waves-effect" data-toggle="fullscreen">
                    <i class="ri-fullscreen-line"></i>
                </button>
            </div> --}}

@php
    $id =Auth::user()->id;
    $adminData = App\Models\User::find($id);
    $headerNotifications = collect();
    $unreadNotificationCount = 0;

    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('app_notifications') && \Illuminate\Support\Facades\Route::has('notifications.index')) {
            $notificationBaseQuery = \App\Models\AppNotification::visibleToUser($adminData);
            $unreadNotificationCount = (clone $notificationBaseQuery)
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $adminData->id))
                ->count();
            $headerNotifications = (clone $notificationBaseQuery)
                ->with(['reads' => fn ($query) => $query->where('user_id', $adminData->id)])
                ->latest('published_at')
                ->latest()
                ->take(5)
                ->get();
        }
    } catch (\Throwable $exception) {
        $headerNotifications = collect();
        $unreadNotificationCount = 0;
    }
@endphp

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon waves-effect" id="page-header-notifications-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="ri-notification-3-line"></i>
                    @if($unreadNotificationCount > 0)
                        <span class="badge bg-danger rounded-pill position-absolute" style="top: 12px; right: 8px;">
                            {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                        </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0" aria-labelledby="page-header-notifications-dropdown">
                    <div class="p-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="m-0">Notifications</h6>
                            </div>
                            <div class="col-auto">
                                <a href="{{ route('notifications.index') }}" class="small">View All</a>
                            </div>
                        </div>
                    </div>
                    <div style="max-height: 280px; overflow-y: auto;">
                        @forelse($headerNotifications as $notification)
                            @php($notificationRead = $notification->isReadBy($adminData))
                            <form action="{{ route('notifications.read', $notification->id) }}" method="POST" class="m-0">
                                @csrf
                                <button type="submit" class="dropdown-item text-start py-3 {{ $notificationRead ? '' : 'bg-light' }}">
                                    <div class="d-flex align-items-start">
                                        <div class="avatar-xs me-3">
                                            <span class="avatar-title bg-primary rounded-circle font-size-16">
                                                <i class="ri-message-3-line"></i>
                                            </span>
                                        </div>
                                        <div class="flex-1">
                                            <h6 class="mt-0 mb-1">{{ \Illuminate\Support\Str::limit($notification->title, 42) }}</h6>
                                            <div class="font-size-12 text-muted">
                                                <p class="mb-1">{{ \Illuminate\Support\Str::limit($notification->message, 72) }}</p>
                                                <p class="mb-0">
                                                    <i class="mdi mdi-clock-outline"></i>
                                                    {{ $notification->published_at?->diffForHumans() ?? $notification->created_at?->diffForHumans() }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </form>
                        @empty
                            <div class="p-4 text-center text-muted">No notifications yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="dropdown d-inline-block user-dropdown">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <img class="rounded-circle header-profile-user" src="{{ url('upload/admin_images/' . $adminData->profile_image) }}" onerror="this.src='{{ url('upload/default.jpg') }}'" alt="Header Avatar">


                    <span class="d-none d-xl-inline-block ms-1">{{ $adminData->name }}</span>
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <!-- item-->
                    <a class="dropdown-item" href="{{ route('admin.profile') }}"><i class="ri-user-line align-middle me-1"></i> Profile</a>
                 <a class="dropdown-item" href="{{ route('dealer.address') }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-vcard-fill" viewBox="0 0 16 16">
  <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm9 1.5a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 0-1h-4a.5.5 0 0 0-.5.5ZM9 8a.5.5 0 0 0 .5.5h4a.5.5 0 0 0 0-1h-4A.5.5 0 0 0 9 8Zm1 2.5a.5.5 0 0 0 .5.5h3a.5.5 0 0 0 0-1h-3a.5.5 0 0 0-.5.5Zm-1 2C9 10.567 7.21 9 5 9c-2.086 0-3.8 1.398-3.984 3.181A1 1 0 0 0 2 13h6.96c.026-.163.04-.33.04-.5ZM7 6a2 2 0 1 0-4 0 2 2 0 0 0 4 0Z"/>
</svg></i>  My Address</a>

                    <a class="dropdown-item" href="{{ route('change.password') }}"><i class="ri-wallet-2-line align-middle me-1"></i> Change Password</a>
                    {{-- <a class="dropdown-item d-block" href="#"><span class="badge bg-success float-end mt-1">11</span><i class="ri-settings-2-line align-middle me-1"></i> Settings</a> --}}
                    {{-- <a class="dropdown-item" href="#"><i class="ri-lock-unlock-line align-middle me-1"></i> Lock screen</a> --}}
                    <div class="dropdown-divider"></div>


                    <a class="dropdown-item text-danger" href="{{ route('admin.logout') }}"><i class="ri-shut-down-line align-middle me-1 text-danger"></i> Logout</a>
                </div>
            </div>

            {{-- <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item noti-icon right-bar-toggle waves-effect">
                    <i class="ri-settings-2-line"></i>
                </button>
            </div> --}}

        </div>
    </div>
</header>
