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
        $users = User::orderBy('id', 'desc')->get()->map(function ($user) {
            return collect($user)->only(['id', 'name', 'email', 'status', 'role']);
        });

        $can = [
            'create_user' => 'Quản trị' ==  Auth::user()->role,
            'update_user' => 'Quản trị' ==  Auth::user()->role,
            'delete_user' => 'Quản trị' ==  Auth::user()->role,
            'import_user' => 'Quản trị' ==  Auth::user()->role,
            'export_user' => 'Quản trị' ==  Auth::user()->role,
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
        if ('Quản trị' != Auth::user()->role) {
            $request->session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user = new User();

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->password = bcrypt($request->password);
        $user->role = $request->role;
        $user->save();

        $request->session()->flash('message', 'Tạo xong người dùng!');
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
        if ('Quản trị' != Auth::user()->role) {
            $request->session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->status = $request->status;
        $user->role = $request->role;
        $user->save();

        $request->session()->flash('message', 'Sửa xong người dùng!');
        return redirect()->route('users.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //Check authorize
        if ('Quản trị' != Auth::user()->role) {
            $request->session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $user->delete();
        Session::flash('message', 'Xóa xong người dùng!');
        return redirect()->route('users.index');
    }

    public function bulkDelete(Request $request)
    {
        //Check authorize
        if ('Quản trị' != Auth::user()->role) {
            $request->session()->flash('message', 'Bạn không có quyền!');
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $users = $request->users;
        foreach ($users as $user) {
            $deleted_user = User::findOrFail($user['id']);
            if ($deleted_user) {
                $deleted_user->destroy($deleted_user->id);
            }
        }

        $request->session()->flash('message', 'Xóa xong người dùng!');
        return redirect()->route('users.index');
    }
}
