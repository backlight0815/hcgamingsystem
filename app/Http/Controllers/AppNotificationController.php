<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AppNotificationRead;
use App\Models\Role;
use Illuminate\Http\Request;

class AppNotificationController extends Controller
{
    private const ADMIN_ROLES = [1, 2];

    public function index()
    {
        $user = auth()->user();

        $notifications = AppNotification::visibleToUser($user)
            ->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest('published_at')
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(AppNotification $notification)
    {
        $this->ensureVisible($notification);

        AppNotificationRead::updateOrCreate(
            [
                'app_notification_id' => $notification->id,
                'user_id' => auth()->id(),
            ],
            [
                'read_at' => now(),
            ]
        );

        if ($notification->action_url) {
            return redirect()->away($notification->action_url);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        $user = auth()->user();

        AppNotification::visibleToUser($user)
            ->pluck('id')
            ->each(function ($notificationId) use ($user): void {
                AppNotificationRead::updateOrCreate(
                    [
                        'app_notification_id' => $notificationId,
                        'user_id' => $user->id,
                    ],
                    [
                        'read_at' => now(),
                    ]
                );
            });

        return back()->with('success', 'All notifications marked as read.');
    }

    public function adminIndex()
    {
        $this->ensureAdmin();

        $notifications = AppNotification::with('creator')
            ->latest('published_at')
            ->latest()
            ->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $roleOptions = Role::orderBy('id')->get();

        return view('admin.notifications.create', compact('roleOptions'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $this->validatedAdminNotification($request);

        AppNotification::create(array_merge($data, [
            'created_by' => auth()->id(),
            'published_at' => now(),
        ]));

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification published successfully.');
    }

    public function edit(AppNotification $notification)
    {
        $this->ensureAdmin();

        $roleOptions = Role::orderBy('id')->get();

        return view('admin.notifications.edit', compact('notification', 'roleOptions'));
    }

    public function update(Request $request, AppNotification $notification)
    {
        $this->ensureAdmin();

        $notification->update($this->validatedAdminNotification($request));

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification updated successfully.');
    }

    public function destroy(AppNotification $notification)
    {
        $this->ensureAdmin();
        $notification->delete();

        return redirect()
            ->route('admin.notifications.index')
            ->with('success', 'Notification deleted successfully.');
    }

    private function validatedAdminNotification(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:3000'],
            'type' => ['required', 'string', 'max:60'],
            'action_url' => ['nullable', 'url', 'max:2048'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $data['target_roles'] = collect($data['target_roles'] ?? [])
            ->map(fn ($roleId): int => (int) $roleId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($data['target_roles'])) {
            $data['target_roles'] = null;
        }

        return $data;
    }

    private function ensureVisible(AppNotification $notification): void
    {
        abort_unless(
            AppNotification::visibleToUser(auth()->user())->where('id', $notification->id)->exists(),
            403
        );
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true), 403);
    }
}
