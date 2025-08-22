<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierSelectionReportRequest;
use App\Http\Requests\UpdateSupplierSelectionReportRequest;
use App\Models\QuotationFile;
use App\Models\SupplierSelectionReport;
use App\Models\User;
use App\Notifications\SupplierSelectionReportApprovedByDirector;
use App\Notifications\SupplierSelectionReportApprovedByManager;
use App\Notifications\SupplierSelectionReportCreated;
use App\Notifications\SupplierSelectionReportNeedAuditorAudit;
use App\Notifications\SupplierSelectionReportNeedDirectorApproval;
use App\Notifications\SupplierSelectionReportRejectedByAuditor;
use App\Notifications\SupplierSelectionReportRejectedByDirector;
use App\Notifications\SupplierSelectionReportRejectedByManager;
use App\Http\Requests\DirectorApproveSupplierSelectionReportRequest;
use App\Http\Requests\ManagerApproveSupplierSelectionReportRequest;
use App\Http\Requests\AuditorAuditSupplierSelectionReportRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Carbon\Carbon;

class SupplierSelectionReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Lấy quyền của người dùng hiện tại
        $user = $request->user();

        $query = SupplierSelectionReport::with(['quotationFiles', 'creator'])
            ->orderBy('id', 'desc');

        if ($user->role === 'Nhân viên Thu Mua') {
            $query->where('creator_id', $user->id);
        } elseif ($user->role === 'Trưởng phòng Thu Mua') {
            $query->where(function($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhere(function($q2) use ($user) {
                      $q2->whereNotNull('manager_id')
                         ->where('manager_id', $user->id);
                  });
            });
        }

        $reports = $query->get()
            ->map(function ($report) {
            return collect($report)->only(['id', 'code', 'description', 'file_path', 'image_url', 'status', 'creator_id', 'created_at'])
                ->merge([
                    'quotation_files_count' => $report->quotation_files_count,
                    'creator_name' => $report->creator_name,
                    'formatted_created_at' => $report->created_at ? Carbon::parse($report->created_at)->format('d/m/Y H:i') : 'N/A'
                ]);
        });

        $canCreate = $user->can('create', SupplierSelectionReport::class);
        $canUpdate = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua' || $user->role === 'Trưởng phòng Thu Mua';
        $canDelete = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua' || $user->role === 'Trưởng phòng Thu Mua';
        $canExport = true;//Always can export

        $can = [
            'create_report' => $canCreate,
            'update_report' => $canUpdate,
            'delete_report' => $canDelete,
            'export_report' => $canExport,
        ];

        $managers = User::where('role', 'Trưởng phòng Thu Mua')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $directors = User::where('role', 'Giám đốc')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return Inertia::render('SupplierSelectionReportIndex', [
            'reports' => $reports,
            'can' => $can,
            'managers' => $managers,
            'directors' => $directors,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->authorize('create', SupplierSelectionReport::class);

        // You can pass any initial props needed for the create page here
        return Inertia::render('SupplierSelectionReportCreate', [
            // Example: permissions or defaults
            'can' => [
                'create_report' => $request->user()->can('create', SupplierSelectionReport::class),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierSelectionReportRequest $request)
    {
        $this->authorize('create', SupplierSelectionReport::class);

        try {
            $data = $request->validated();
            $data['creator_id'] = $request->user()->id;

            // Xử lý file_path cho báo cáo
            if ($request->hasFile('file_path')) {
                $data['file_path'] = $this->uploadFile($request->file('file_path'));
            } elseif ($request->file_path instanceof \Illuminate\Http\UploadedFile) {
                $data['file_path'] = $this->uploadFile($request->file_path);
            } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
                $data['file_path'] = $this->saveBase64Image($request->file_path);
            } else {
                $data['file_path'] = null;
            }

            // Tạo bản ghi mới
            $report = SupplierSelectionReport::create($data);

            // Xử lý file báo giá
            if ($request->hasFile('quotation_files')) {
                foreach ($request->file('quotation_files') as $file) {
                    $quotationFile = new QuotationFile();
                    $quotationFile->file_name = $file->getClientOriginalName();
                    $quotationFile->file_path = $this->uploadFile($file);
                    $quotationFile->file_type = $file->getClientMimeType();
                    $quotationFile->file_size = $file->getSize();
                    $quotationFile->supplier_selection_report_id = $report->id;
                    $quotationFile->save();
                }
            }

            // Nếu người tạo là Trưởng phòng Thu Mua
            if ($request->user()->role === 'Trưởng phòng Thu Mua') {
                $report->update([
                    'status' => 'manager_approved',
                    'manager_approved_at' => now()
                ]);

                $auditors = User::where('role', 'Nhân viên Kiểm Soát')->get();
                foreach ($auditors as $auditor) {
                    Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportNeedAuditorAudit($report));
                }
            }

            return redirect()->route('supplier_selection_reports.index')->with('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được tạo thành công!',
            ]);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra trong quá trình xác thực dữ liệu.',
            ]);
        } catch (\Exception $e) {
            Log::error("Lỗi khi tạo báo cáo: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Đã xảy ra lỗi không mong muốn khi tạo báo cáo.',
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('update', $supplierSelectionReport);

        $report = $supplierSelectionReport->load('quotationFiles');

        return Inertia::render('SupplierSelectionReportEdit', [
            'report' => [
                'id' => $report->id,
                'code' => $report->code,
                'description' => $report->description,
                'file_path' => $report->file_path,
                'image_url' => $report->image_url,
                'status' => $report->status,
                'manager_approved_result' => $report->manager_approved_result,
                'manager_approved_notes' => $report->manager_approved_notes,
                'auditor_audited_result' => $report->auditor_audited_result,
                'auditor_audited_notes' => $report->auditor_audited_notes,
                'director_approved_result' => $report->director_approved_result,
                'director_approved_notes' => $report->director_approved_notes,
                'creator_name' => $report->creator_name,
                'manager_name' => $report->manager_name,
                'auditor_name' => $report->auditor_name,
                'director_name' => $report->director_name,
                'creator_id' => $report->creator_id,
                'manager_id' => $report->manager_id,
                'auditor_id' => $report->auditor_id,
                'director_id' => $report->director_id,
                'manager_approved_at' => $report->manager_approved_at,
                'auditor_audited_at' => $report->auditor_audited_at,
                'director_approved_at' => $report->director_approved_at,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'quotation_files' => $report->quotationFiles->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_url' => $file->file_url,
                        'file_size_formatted' => $file->file_size_formatted,
                        'file_type' => $file->file_type,
                        'created_at' => $file->created_at,
                    ];
                })
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierSelectionReport $supplierSelectionReport)
    {
        $report = $supplierSelectionReport->load('quotationFiles');

        return Inertia::render('SupplierSelectionReportShow', [
            'report' => [
                'id' => $report->id,
                'code' => $report->code,
                'description' => $report->description,
                'file_path' => $report->file_path,
                'image_url' => $report->image_url,
                'status' => $report->status,
                'manager_approved_result' => $report->manager_approved_result,
                'manager_approved_notes' => $report->manager_approved_notes,
                'auditor_audited_result' => $report->auditor_audited_result,
                'auditor_audited_notes' => $report->auditor_audited_notes,
                'director_approved_result' => $report->director_approved_result,
                'director_approved_notes' => $report->director_approved_notes,
                'creator_name' => $report->creator_name,
                'manager_name' => $report->manager_name,
                'auditor_name' => $report->auditor_name,
                'director_name' => $report->director_name,
                'creator_id' => $report->creator_id,
                'manager_id' => $report->manager_id,
                'auditor_id' => $report->auditor_id,
                'director_id' => $report->director_id,
                'manager_approved_at' => $report->manager_approved_at,
                'auditor_audited_at' => $report->auditor_audited_at,
                'director_approved_at' => $report->director_approved_at,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'quotation_files' => $report->quotationFiles->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'file_name' => $file->file_name,
                        'file_url' => $file->file_url,
                        'file_size_formatted' => $file->file_size_formatted,
                        'file_type' => $file->file_type,
                        'created_at' => $file->created_at,
                    ];
                })
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('update', $supplierSelectionReport);

        // Lấy field text chuẩn
        $data = $request->safe()->only(['code', 'description']);

        $oldFilePath = $supplierSelectionReport->file_path;

        if (
            $request->boolean('file_path_removed') === true
            && ! $request->hasFile('file_path')
            && ! (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image'))
        ) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file_path' => 'Vui lòng đính kèm ảnh báo cáo.',
            ]);
        }

        // ---- 1) Xử lý file_path (ảnh)
        // Ưu tiên xóa nếu người dùng ấn nút xóa
        if ($request->boolean('file_path_removed') === true) {
            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
            }
            $data['file_path'] = null;

        } elseif ($request->hasFile('file_path')) {
            // Ảnh mới là file upload
            $data['file_path'] = $this->uploadFile($request->file('file_path'));
            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
            }

        } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
            // Ảnh mới dạng base64
            $data['file_path'] = $this->saveBase64Image($request->file_path);
            if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                Storage::disk('public')->delete($oldFilePath);
            }

        } elseif ($request->filled('file_path') && is_string($request->file_path)) {
            // Chuỗi URL ảnh cũ -> giữ nguyên (KHÔNG set $data['file_path'])
            // no-op
        } else {
            // Không có key file_path trong request hoặc rỗng -> giữ nguyên
            // no-op
        }

        // ---- 2) Xử lý xoá file báo giá cũ (nếu người dùng đánh dấu)
        $deletedIds = collect($request->input('deleted_quotation_file_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map('intval')
            ->values();

        if ($deletedIds->isNotEmpty()) {
            $filesToDelete = $supplierSelectionReport->quotationFiles()
                ->whereIn('id', $deletedIds)
                ->get();

            foreach ($filesToDelete as $qf) {
                if ($qf->file_path && Storage::disk('public')->exists($qf->file_path)) {
                    Storage::disk('public')->delete($qf->file_path);
                }
                $qf->delete();
            }
        }

        // ---- 3) Thêm file báo giá mới (nếu có)
        if ($request->hasFile('quotation_files')) {
            foreach ($request->file('quotation_files') as $file) {
                $supplierSelectionReport->quotationFiles()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $this->uploadFile($file),
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // ---- 4) Các ràng buộc bắt buộc (nếu nghiệp vụ yêu cầu)
        // - file_path required?
        // - Ít nhất 1 file báo giá?
        $errors = [];

        // Ví dụ: bắt buộc phải có ảnh file_path
        if (
            ($request->boolean('file_path_removed') === true && !$request->hasFile('file_path') && !(is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')))
            && empty($data['file_path']) // không set mới
            && empty($oldFilePath)       // trước đó cũng không có
        ) {
            $errors['file_path'] = 'Vui lòng đính kèm ảnh báo cáo.';
        }

        // Ví dụ: yêu cầu phải còn ít nhất một file báo giá sau khi xử lý
        $remainQuotation = $supplierSelectionReport->quotationFiles()
            ->whereNotIn('id', $deletedIds)
            ->count();
        $addingQuotation = $request->hasFile('quotation_files') ? count($request->file('quotation_files')) : 0;

        if (($remainQuotation + $addingQuotation) === 0) {
            // Nếu nghiệp vụ yêu cầu bắt buộc có báo giá
            // $errors['quotation_files'] = 'Vui lòng đính kèm ít nhất một file báo giá.';
        }

        if (!empty($errors)) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        // ---- 5) Lưu các field text + file_path (nếu có trong $data)
        $supplierSelectionReport->fill($data)->save();

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => 'Báo cáo đã được cập nhật thành công!',
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('delete', $supplierSelectionReport);

        try {
            // Xóa file báo giá
            foreach ($supplierSelectionReport->quotationFiles as $quotationFile) {
                if ($quotationFile->file_path && Storage::disk('public')->exists($quotationFile->file_path)) {
                    Storage::disk('public')->delete($quotationFile->file_path);
                }
                $quotationFile->delete();
            }

            // Xóa file báo cáo
            if ($supplierSelectionReport->file_path && Storage::disk('public')->exists($supplierSelectionReport->file_path)) {
                Storage::disk('public')->delete($supplierSelectionReport->file_path);
            }

            $supplierSelectionReport->delete();

            return redirect()->back()->with('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được xóa thành công!',
            ]);
        } catch (\Exception $e) {
            Log::error("Lỗi khi xóa báo cáo: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Đã xảy ra lỗi không mong muốn khi xóa báo cáo.',
            ]);
        }
    }

    public function requestManagerToApprove(Request $request, SupplierSelectionReport $supplierSelectionReport)
    {
        if ($supplierSelectionReport->creator_id !== auth()->id()) {
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $managerId = $request->input('manager_id');
        if (!$managerId) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Vui lòng chọn Trưởng phòng duyệt.'
            ]);
        }

        $manager = User::where('role', 'Trưởng phòng Thu Mua')->where('id', $managerId)->first();
        if (!$manager) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Người duyệt không hợp lệ.'
            ]);
        }

        $supplierSelectionReport->update([
            'status' => 'pending_manager_approval',
            'manager_id' => $manager->id,
        ]);

        Notification::route('mail', $manager->email)->notify(new SupplierSelectionReportCreated($supplierSelectionReport));

        return redirect()->route('supplier_selection_reports.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được gửi duyệt thành công!'
            ]);
    }

    /**
     * Trưởng phòng Thu Mua duyệt phiếu
     */
    public function managerApprove(ManagerApproveSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $user = $request->user();
        if ($user->role !== 'Trưởng phòng Thu Mua') {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền duyệt phiếu này.'
            ]);
        }

        $validated = $request->validated();
        $supplierSelectionReport->manager_approved_result = $validated['manager_approved_result'];
        $supplierSelectionReport->manager_approved_notes = $validated['manager_approved_notes'] ?? null;

        if ('approved' == $validated['manager_approved_result']) {
            $supplierSelectionReport->status = 'manager_approved';
        } else {
            $supplierSelectionReport->status = 'rejected';
        }

        $supplierSelectionReport->manager_id = Auth::id();
        $supplierSelectionReport->manager_approved_at = now();
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status === 'manager_approved') {
            $auditors = User::where('role', 'Nhân viên Kiểm Soát')->get();
            foreach ($auditors as $auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportNeedAuditorAudit($supplierSelectionReport));
            }
            Notification::route('mail', $supplierSelectionReport->creator->email)->notify(new SupplierSelectionReportApprovedByManager($supplierSelectionReport));
        } else {
            Notification::route('mail', $supplierSelectionReport->creator->email)->notify(new SupplierSelectionReportRejectedByManager($supplierSelectionReport));
        }

        return redirect()->route('supplier_selection_reports.show', $supplierSelectionReport->id)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Đã duyệt phiếu thành công!'
            ]);
    }

    /**
     * Nhân viên Kiểm Soát kiểm tra phiếu
     */
    public function auditorAudit(AuditorAuditSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $user = $request->user();
        if ($user->role !== 'Nhân viên Kiểm Soát') {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền review báo cáo này.'
            ]);
        }

        $validated = $request->validated();
        $supplierSelectionReport->auditor_audited_result = $validated['auditor_audited_result'];
        $supplierSelectionReport->auditor_audited_notes = $validated['auditor_audited_notes'] ?? null;
        $supplierSelectionReport->auditor_id = Auth::id();

        if ($validated['auditor_audited_result'] === 'approved') {
            $supplierSelectionReport->status = 'auditor_approved';
        } else {
            $supplierSelectionReport->status = 'rejected';
        }

        $supplierSelectionReport->auditor_audited_at = now();
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status !== 'auditor_approved') {
            Notification::route('mail', $supplierSelectionReport->creator->email)->notify(new SupplierSelectionReportRejectedByAuditor($supplierSelectionReport));
        }

        return redirect()->route('supplier_selection_reports.show', $supplierSelectionReport->id)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Đã gửi review thành công!'
            ]);
    }

    public function requestDirectorToApprove(Request $request, SupplierSelectionReport $supplierSelectionReport)
    {
        // Optional: Restrict to auditor or authorized roles. For now, just ensure status is auditor_approved
        if ($supplierSelectionReport->status !== 'auditor_approved') {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Chỉ có thể gửi duyệt Giám đốc sau khi đã được Kiểm Soát duyệt.'
            ]);
        }

        $directorId = $request->input('director_id');
        if (!$directorId) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Vui lòng chọn Giám đốc duyệt.'
            ]);
        }

        $director = User::where('role', 'Giám đốc')->where('id', $directorId)->first();
        if (!$director) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Người duyệt không hợp lệ.'
            ]);
        }

        $supplierSelectionReport->update([
            'status' => 'pending_director_approval',
            'director_id' => $director->id,
        ]);

        Notification::route('mail', $director->email)->notify(new SupplierSelectionReportNeedDirectorApproval($supplierSelectionReport));

        return redirect()->route('supplier_selection_reports.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Đã gửi yêu cầu duyệt tới Giám đốc.'
            ]);
    }

    /**
     * Giám đốc duyệt phiếu
     */
    public function directorApprove(DirectorApproveSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $user = $request->user();
        if ($user->role !== 'Giám đốc') {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền duyệt phiếu này.'
            ]);
        }

        $validated = $request->validated();
        $supplierSelectionReport->director_approved_result = $validated['director_approved_result'];
        $supplierSelectionReport->director_approved_notes = $validated['director_approved_notes'] ?? null;
        $supplierSelectionReport->director_id = Auth::id();
        $supplierSelectionReport->director_approved_at = now();

        if ($validated['director_approved_result'] === 'approved') {
            $supplierSelectionReport->status = 'director_approved';
        } else {
            $supplierSelectionReport->status = 'rejected';
        }

        $supplierSelectionReport->save();

        $creator = $supplierSelectionReport->creator;
        $auditor = $supplierSelectionReport->auditor;

        if ($validated['director_approved_result'] === 'approved') {
            Notification::route('mail', $creator->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            if ($auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            }
        } else {
            Notification::route('mail', $creator->email)->notify(new SupplierSelectionReportRejectedByDirector($supplierSelectionReport));
            if ($auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportRejectedByDirector($supplierSelectionReport));
            }
        }

        return redirect()->route('supplier_selection_reports.show', $supplierSelectionReport->id)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Đã duyệt phiếu thành công!'
            ]);
    }

    /**
     * Helper function to upload an image file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string
     */
    protected function uploadFile($file): string
    {
        $path = $file->store('supplier_reports', 'public');
        return $path;
    }

    /**
     * Helper function to save a Base64 encoded image.
     *
     * @param string $base64Image
     * @return string
     */
    protected function saveBase64Image(string $base64Image): string
    {
        list($type, $data) = explode(';', $base64Image);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);

        preg_match('/^data:image\/(\w+);base64/', $base64Image, $matches);
        $extension = $matches[1] ?? 'png';

        $fileName = 'supplier_reports/' . uniqid() . '.' . $extension;
        Storage::disk('public')->put($fileName, $data);

        return $fileName;
    }

    /**
     * Helper function to delete a file from storage.
     *
     * @param string $filePath
     * @return bool
     */
    protected function deleteFile(string $filePath): bool
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }
        return false;
    }
}
