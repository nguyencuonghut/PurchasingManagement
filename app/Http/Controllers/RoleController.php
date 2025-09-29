<?php
namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\RoleRequest;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()->get();
        return Inertia::render('RoleIndex', [
            'roles' => $roles,
            'can' => [
                'create_role' => true,
                'update_role' => true,
                'delete_role' => true,
            ],
        ]);
    }

    public function store(RoleRequest $request)
    {
        $role = Role::create($request->validated());
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Tạo vai trò thành công!',
        ]);
    }

    public function update(RoleRequest $request, Role $role)
    {
        $role->update($request->validated());
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Cập nhật vai trò thành công!',
        ]);
    }

    public function destroy(Role $role)
    {
        // Kiểm tra nếu vai trò đang được sử dụng bởi người dùng nào không
        if ($role->users()->exists()) {
            session()->flash('flash', [
                'type' => 'error', // hoặc 'error'
                'message' => 'Không thể xóa vai trò đang được sử dụng',
            ]);
            return redirect()->back();
        }
        $role->delete();
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Xóa vai trò thành công',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        // Kiểm tra nếu có vai trò nào trong danh sách đang được sử dụng bởi người dùng
        $roleIds = collect($request->roles)->flatten()->toArray(); // Đảm bảo là mảng phẳng
        $rolesInUse = Role::whereIn('id', $roleIds)->whereHas('users')->get(['id', 'name']);
        if ($rolesInUse->isNotEmpty()) {
            $names = $rolesInUse->pluck('name')->toArray();
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Không thể xóa các vai trò đang được sử dụng: ' . implode(', ', $names)
            ]);
        }

        // Xóa các vai trò không được sử dụng
        Role::whereIn('id', $roleIds)->delete();
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Xóa các vai trò đã chọn thành công!'
        ]);
    }
}
