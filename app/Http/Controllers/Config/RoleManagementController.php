<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleManagementController extends Controller
{
    // Show all roles
 // Show all roles
    public function AllRoles()
    {

             $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Role Management', 'url' => route('all.roles')],

        ];
        $roles = Role::orderBy('id', 'desc')->get();
        return view('admin.role.roles_all', compact('roles','breadcrumbData'));
    }

    // Show add role form
    public function AddRole()
    {
        return view('admin.role.roles_add');
    }

    // Store role (with manual ID support)
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

    // Show edit form
    public function EditRole($id)
    {
        $role = Role::findOrFail($id);
        return view('admin.role.roles_edit', compact('role'));
    }

    // Update role
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

    // Delete role
    public function DeleteRole($id)
    {
        Role::findOrFail($id)->delete();
        return redirect()->route('all.roles')->with('success', 'Role deleted successfully.');
    }
}
