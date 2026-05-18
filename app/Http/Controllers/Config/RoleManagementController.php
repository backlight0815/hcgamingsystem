<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleManagementController extends Controller
{
    public function AllRoles()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Role Management', 'url' => route('all.roles')],
        ];

        $roles = Role::orderBy('id')->get();
        $userCountsByRole = User::selectRaw('role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');
        $activeUserCountsByRole = User::where('status', 1)
            ->selectRaw('role_id, COUNT(*) as total')
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        return view('admin.role.roles_all', compact(
            'roles',
            'breadcrumbData',
            'userCountsByRole',
            'activeUserCountsByRole'
        ));
    }

    public function AddRole()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Role Management', 'url' => route('all.roles')],
            ['label' => 'Add Role', 'url' => route('add.role')],
        ];

        return view('admin.role.roles_add', compact('breadcrumbData'));
    }

    public function StoreRole(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|unique:roles,id',
            'name' => 'required|unique:roles|max:255',
            'description' => 'nullable|max:500',
        ]);

        Role::create([
            'id' => $request->id,
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('all.roles')->with('success', 'Role created successfully.');
    }

    public function EditRole($id)
    {
        $role = Role::findOrFail($id);
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Role Management', 'url' => route('all.roles')],
            ['label' => 'Edit Role', 'url' => route('edit.role', $role->id)],
        ];

        $assignedUsers = User::where('role_id', $role->id)->count();

        return view('admin.role.roles_edit', compact('role', 'breadcrumbData', 'assignedUsers'));
    }

    public function UpdateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'name' => 'required|max:255|unique:roles,name,' . $id,
            'description' => 'nullable|max:500',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route('all.roles')->with('success', 'Role updated successfully.');
    }

    public function DeleteRole($id)
    {
        if (User::where('role_id', $id)->exists()) {
            return redirect()
                ->route('all.roles')
                ->with('error', 'This role has assigned users and cannot be deleted.');
        }

        Role::findOrFail($id)->delete();
        return redirect()->route('all.roles')->with('success', 'Role deleted successfully.');
    }
}
