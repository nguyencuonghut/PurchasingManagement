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
        $users = User::with('role')->orderBy('id', 'desc')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'role' => optional($user->role)->name,
                'role_id' => $user->role_id,
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

        return Inertia::render('UserIndex', [
            'users' => $users,
            'can' => $can,
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
            session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->password = bcrypt($request->password);
        $user->role_id = $request->role_id;
        $user->save();

    session()->flash('message', 'Tạo xong người dùng!');
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
            session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->role_id = $request->role_id;
        $user->save();

    session()->flash('message', 'Sửa xong người dùng!');
        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            Session::flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user->delete();
        Session::flash('message', 'Xóa xong người dùng!');
        return redirect()->route('users.index');
    }

    public function bulkDelete(Request $request)
    {
        //Check authorize
        if (optional(Auth::user()->role)->name !== 'Quản trị') {
            session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $users = $request->users;
        foreach ($users as $user) {
            $deleted_user = User::findOrFail($user['id']);
            if ($deleted_user) {
                $deleted_user->destroy($deleted_user->id);
            }
        }

    session()->flash('message', 'Xóa xong người dùng!');
        return redirect()->route('users.index');
    }
}
