<?php
namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Http\Requests\DepartmentRequest;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()->get();
        return Inertia::render('DepartmentIndex', [
            'departments' => $departments,
            'can' => [
                'create_department' => true,
                'update_department' => true,
                'delete_department' => true,
            ],
        ]);
    }

    public function store(DepartmentRequest $request)
    {
        Department::create($request->validated());
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Tạo phòng ban thành công!',
        ]);
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Cập nhật phòng ban thành công!',
        ]);
    }

    public function destroy(Department $department)
    {
        if ($department->users()->exists()) {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Không thể xóa phòng ban đang được sử dụng',
            ]);
            return redirect()->back();
        }
        $department->delete();
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Xóa phòng ban thành công',
        ]);
    }

    public function bulkDelete(Request $request)
    {
        $departmentIds = collect($request->departments)->flatten()->toArray();
        $departmentsInUse = Department::whereIn('id', $departmentIds)->whereHas('users')->get(['id', 'name']);
        if ($departmentsInUse->isNotEmpty()) {
            $names = $departmentsInUse->pluck('name')->toArray();
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Không thể xóa các phòng ban đang được sử dụng: ' . implode(', ', $names)
            ]);
        }
        Department::whereIn('id', $departmentIds)->delete();
        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Xóa các phòng ban đã chọn thành công!'
        ]);
    }
}
