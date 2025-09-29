<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::with(['role', 'department'])->orderBy('id', 'desc')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role' => optional($user->role)->name,
                'role_id' => $user->role_id,
                'department' => optional($user->department)->name,
                'department_id' => $user->department_id,
            ];
        });

        $isAdmin = optional(Auth::user()->role)->name === 'Quản trị';
        $can = [
            'create_user' => $isAdmin,
            'update_user' => $isAdmin,
            'delete_user' => $isAdmin,
            'import_user' => $isAdmin,
            'export_user' => $isAdmin,
        ];

        $departments = \App\Models\Department::all(['id', 'name']);
        $roles = \App\Models\Role::all(['id', 'name']);
        return Inertia::render('UserIndex', [
            'users' => $users,
            'can' => $can,
            'departments' => $departments,
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền!',
            ]);
            return redirect()->back();
        }

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->password = bcrypt($request->password);
        $user->role_id = $request->role_id;
        $user->department_id = $request->department_id;
        $user->save();

        session()->flash('flash', [
            'type' => 'success',
            'message' => 'Tạo xong người dùng!',
        ]);
        return redirect()->route('users.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền!',
            ]);
            return redirect()->back();
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->role_id = $request->role_id;
        $user->department_id = $request->department_id;
        $user->save();

        session()->flash('flash', [
            'type' => 'success',
            'message' => 'Cập nhật xong người dùng!',
        ]);
        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            Session::flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền!',
            ]);
            return redirect()->back();
        }

        // Kiểm tra nếu người dùng đang cố xóa chính họ
        if (Auth::id() === $user->id) {
            Session::flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không thể xóa chính mình!',
            ]);
            return redirect()->back();
        }

        // Kiểm tra nếu user liên quan đến bất kỳ báo cáo nào (creator, manager, auditor, director, admin thu mua)
        $hasRelatedReport = \App\Models\SupplierSelectionReport::where(function($q) use ($user) {
            $q->where('creator_id', $user->id)
              ->orWhere('manager_id', $user->id)
              ->orWhere('auditor_id', $user->id)
              ->orWhere('director_id', $user->id)
              ->orWhere('adm_id', $user->id);
        })->exists();
        if ($hasRelatedReport) {
            Session::flash('flash', [
                'type' => 'error',
                'message' => 'Không thể xóa người dùng đang liên quan đến báo cáo lựa chọn nhà cung cấp!',
            ]);
            return redirect()->back();
        }
        $user->delete();
        Session::flash('flash', [
            'type' => 'success',
            'message' => 'Xóa xong người dùng!',
        ]);
        return redirect()->route('users.index');
    }

    public function bulkDelete(Request $request)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền!',
            ]);
            return redirect()->back();
        }

        // Kiểm tra nếu có người dùng nào trong danh sách đang cố xóa chính họ
        $userIds = collect($request->users)->pluck('id')->toArray();
        if (in_array(Auth::id(), $userIds)) {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Bạn không thể xóa chính mình!',
            ]);
            return redirect()->back();
        }

        // Kiểm tra nếu có người dùng nào trong danh sách đang có Supplier Selection Reports
        $usersWithReports = User::whereIn('id', $userIds)->whereHas('supplierSelectionReports')->get(['id', 'name']);
        if ($usersWithReports->isNotEmpty()) {
            $names = $usersWithReports->pluck('name')->toArray();
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Không thể xóa các người dùng đang có báo cáo lựa chọn nhà cung cấp: ' . implode(', ', $names)
            ]);
        }

        $users = $request->users;
        foreach ($users as $user) {
            $deleted_user = User::findOrFail($user['id']);
            if ($deleted_user) {
                $deleted_user->destroy($deleted_user->id);
            }
        }

        session()->flash('flash', [
            'type' => 'success',
            'message' => 'Xóa xong người dùng đã chọn!',
        ]);
        return redirect()->route('users.index');
    }
}
