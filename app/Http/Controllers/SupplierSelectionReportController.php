<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierSelectionReportRequest;
use App\Http\Requests\UpdateSupplierSelectionReportRequest;
use App\Models\QuotationFile;
use App\Models\SupplierSelectionReport;
use App\Models\User;
use App\Enums\ReportStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Http\Resources\SupplierSelectionReportResource;
use App\Services\ActivityLogger;
use Illuminate\Auth\Access\AuthorizationException;


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

        $query = SupplierSelectionReport::with(['creator', 'childReport', 'parentReport'])
            ->withCount('quotationFiles')
            ->orderBy('id', 'desc');

        $roleName = optional($user->role)->name;
        if ($roleName === 'Nhân viên mua hàng') {
            // Xem tất cả phiếu thuộc phòng ban mình, nhưng với phiếu trạng thái draft thì chỉ xem của mình
            $departmentId = $user->department_id;
            $query->whereHas('creator', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
            $query->where(function($q) use ($user) {
                $q->where('status', '!=', ReportStatus::DRAFT)
                  ->orWhere('creator_id', $user->id);
            });
        } elseif ($roleName === 'Trưởng phòng') {
            // Xem tất cả phiếu thuộc phòng ban mình, trừ những phiếu ở trạng thái draft
            $departmentId = $user->department_id;
            $query->whereHas('creator', function($q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
            $query->where('status', '!=', ReportStatus::DRAFT);
        } elseif ($roleName === 'Nhân viên Kiểm Soát') {
            $query->whereIn('status', [ReportStatus::MANAGER_APPROVED, ReportStatus::AUDITOR_APPROVED, ReportStatus::PENDING_DIRECTOR, ReportStatus::DIRECTOR_APPROVED, ReportStatus::REJECTED]);
        } elseif ($roleName === 'Giám đốc') {
            $query->whereIn('status', [ReportStatus::DIRECTOR_APPROVED, ReportStatus::PENDING_DIRECTOR]);
        } elseif ($roleName === 'Kế toán') {
            $query->where('status', ReportStatus::DIRECTOR_APPROVED);
        } elseif ($roleName === 'Admin Thu Mua') {
            $query->where('adm_id', $user->id);
        }

        $reports = SupplierSelectionReportResource::collection($query->get());

        $canCreate = $user->can('create', SupplierSelectionReport::class);
        $canUpdate = in_array($roleName, ['Quản trị', 'Nhân viên mua hàng', 'Trưởng phòng']);
        $canDelete = in_array($roleName, ['Quản trị', 'Nhân viên mua hàng', 'Trưởng phòng']);
        $canExport = true;//Always can export

        $can = [
            'create_report' => $canCreate,
            'update_report' => $canUpdate,
            'delete_report' => $canDelete,
            'export_report' => $canExport,
        ];

        $managers = User::whereHas('role', function($q) {
                $q->where('name', 'Trưởng phòng');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $directors = User::whereHas('role', function($q) {
                $q->where('name', 'Giám đốc');
            })
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
        $adminThuMuaUsers = $this->getAdminThuMuaUsers($request->user());

        // Nếu có parent_report_id (tạo từ phiếu bị rejected)
        $parentReportId = $request->input('parent_report_id');
        $parentReport = null;
        $initData = [];
        if ($parentReportId) {
            $parentReport = SupplierSelectionReport::find($parentReportId);
            if ($parentReport && $parentReport->status === ReportStatus::REJECTED) {
                if ($parentReport->childReport) {
                    abort(403, 'Phiếu này đã có phiếu con, không thể tạo thêm.');
                }
                $initData['description'] = $parentReport->description;
                $initData['adm_id'] = $parentReport->adm_id;
                $initData['parent_report_id'] = $parentReport->id;
            }
        }

        return Inertia::render('SupplierSelectionReportCreate', [
            'can' => [
                'create_report' => $request->user()->can('create', SupplierSelectionReport::class),
            ],
            'admin_thu_mua_users' => ($adminThuMuaUsers ?? collect())->toArray(),
            'init_data' => $initData,
            'parent_report' => $parentReport ? (new SupplierSelectionReportResource($parentReport))->toArray($request) : null,
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
            // Lưu adm_id nếu có (ép về null nếu rỗng, ép về int nếu có)
            $adminId = $request->input('admin_thu_mua_id');
            $data['adm_id'] = ($adminId === '' || $adminId === null) ? null : (int)$adminId;

            // Nếu tạo từ phiếu cha bị rejected thì lấy description, adm_id, parent_report_id từ phiếu cha
            $parentReportId = $request->input('parent_report_id');
            if ($parentReportId) {
                $parentReport = SupplierSelectionReport::find($parentReportId);
                if ($parentReport && $parentReport->status === ReportStatus::REJECTED) {
                    if ($parentReport->childReport) {
                        throw ValidationException::withMessages([
                            'parent_report_id' => 'Phiếu cha đã có phiếu con, không thể tạo thêm.'
                        ]);
                    }
                    $data['description'] = '[LẦN 2] - ' . $parentReport->description;
                    $data['adm_id'] = $parentReport->adm_id;
                    $data['parent_report_id'] = $parentReport->id;
                }
            }
            // --- Sinh mã code tự động, Index tăng từ 1 tới N cho cả năm, không phụ thuộc Department ---
            $year = now()->year;
            $user = $request->user();
            $departmentCode = optional($user->department)->code ?? 'N/A';
            // Tìm index lớn nhất đã dùng cho năm hiện tại
            $maxIndex = SupplierSelectionReport::whereYear('created_at', $year)
                ->where('code', 'like', "$year/%/%")
                ->get()
                ->map(function($r) use ($year) {
                    $parts = explode('/', $r->code);
                    return isset($parts[2]) ? intval($parts[2]) : 0;
                })->max();
            $index = $maxIndex ? $maxIndex + 1 : 1;
            $code = sprintf('%d/%s/%d', $year, $departmentCode, $index);
            if (SupplierSelectionReport::where('code', $code)->exists()) {
                throw new \Exception('Không thể sinh mã báo cáo duy nhất, vui lòng thử lại.');
            }
            $data['code'] = $code;

            // Chuẩn bị biến theo dõi file đã upload để có thể dọn nếu lỗi
            $uploadedMainPath = null;
            $storedQuotationFiles = [];
            $storedProposalFiles = [];

            // Xử lý file_path cho báo cáo (upload trước, lưu DB sau)
            if ($request->hasFile('file_path')) {
                $data['file_path'] = $this->uploadFile($request->file('file_path'));
                $uploadedMainPath = $data['file_path'];
            } elseif ($request->file_path instanceof \Illuminate\Http\UploadedFile) {
                $data['file_path'] = $this->uploadFile($request->file_path);
                $uploadedMainPath = $data['file_path'];
            } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
                $data['file_path'] = $this->saveBase64Image($request->file_path);
                $uploadedMainPath = $data['file_path'];
            } else {
                $data['file_path'] = null;
            }

            // Chuẩn bị file báo giá (upload trước, lưu DB sau)
            if ($request->hasFile('quotation_files')) {
                foreach ($request->file('quotation_files') as $file) {
                    $storedQuotationFiles[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $this->uploadFile($file),
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ];
                }
            }

            // Khởi tạo mảng rỗng cho $storedProposalFiles để tránh lỗi undefined
            $storedProposalFiles = [];

            // Upload file đề nghị/BOQ trước khi bắt đầu transaction (nếu có)
            if ($request->hasFile('proposal_files')) {
                foreach ($request->file('proposal_files') as $file) {
                    // Lưu vào public disk để có thể truy cập trực tiếp
                    $path = $file->store('proposal_files', 'public');
                    $storedProposalFiles[] = [
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                    ];
                }
            }

            // Tạo bản ghi trong transaction
            $report = null;
            DB::transaction(function () use (&$report, $data, $storedQuotationFiles, $storedProposalFiles, $request) {
                $report = SupplierSelectionReport::create($data);

                foreach ($storedQuotationFiles as $qf) {
                    $quotationFile = new QuotationFile();
                    $quotationFile->file_name = $qf['file_name'];
                    $quotationFile->file_path = $qf['file_path'];
                    $quotationFile->file_type = $qf['file_type'];
                    $quotationFile->file_size = $qf['file_size'];
                    $quotationFile->supplier_selection_report_id = $report->id;
                    $quotationFile->save();
                }

                // Lưu các file đề nghị/BOQ đã upload (nếu có)
                foreach ($storedProposalFiles as $pf) {
                    $proposalFile = new \App\Models\ProposalFile();
                    $proposalFile->file_name = $pf['file_name'];
                    $proposalFile->file_path = $pf['file_path'];
                    $proposalFile->file_type = $pf['file_type'];
                    $proposalFile->file_size = $pf['file_size'];
                    $proposalFile->supplier_selection_report_id = $report->id;
                    $proposalFile->save();
                }

                // Nếu người tạo là Trưởng phòng -> auto approve & notify
                if (optional($request->user()->role)->name === 'Trưởng phòng') {
                    $report->update([
                        'status' => ReportStatus::MANAGER_APPROVED,
                        'manager_id' => $request->user()->id,
                        'manager_approved_at' => now()
                    ]);

                    $auditors = User::whereHas('role', function($q) {
                        $q->where('name', 'Nhân viên Kiểm Soát');
                    })->get();
                    foreach ($auditors as $auditor) {
                        Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportNeedAuditorAudit($report));
                    }
                }
            });

            // Log activity: created (guard null)
            if ($report) {
                ActivityLogger::log($request, 'created', $report, [
                    'code' => $report->code,
                    'id' => $report->id,
                ]);
            }

            session()->flash('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được tạo thành công!',
            ]);

            return redirect()->route('supplier_selection_reports.index');
        } catch (ValidationException $e) {
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra trong quá trình xác thực dữ liệu.',
            ]);
            return redirect()->back();
        } catch (\Exception $e) {
            // Dọn file đã upload nếu transaction/DB fail
            try {
                if (!empty($uploadedMainPath) && Storage::disk('public')->exists($uploadedMainPath)) {
                    Storage::disk('public')->delete($uploadedMainPath);
                }
                if (!empty($storedQuotationFiles)) {
                    foreach ($storedQuotationFiles as $qf) {
                        if (!empty($qf['file_path']) && Storage::disk('public')->exists($qf['file_path'])) {
                            Storage::disk('public')->delete($qf['file_path']);
                        }
                    }
                }
                if (!empty($storedProposalFiles)) {
                    foreach ($storedProposalFiles as $pf) {
                        if (!empty($pf['file_path']) && Storage::disk('public')->exists($pf['file_path'])) {
                            Storage::disk('public')->delete($pf['file_path']);
                        }
                    }
                }
            } catch (\Throwable $cleanupEx) {
                Log::warning('Cleanup uploaded files failed: '.$cleanupEx->getMessage());
            }

            Log::error("Lỗi khi tạo báo cáo: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            session()->flash('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra trong quá trình tạo báo cáo.',
            ]);
            return redirect()->back();
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('update', $supplierSelectionReport);
        $report = $supplierSelectionReport->load(['quotationFiles','proposalFiles','creator','manager','auditor','director']);
        $adminThuMuaUsers = $this->getAdminThuMuaUsers(request()->user());
        return Inertia::render('SupplierSelectionReportEdit', [
            'report' => (new SupplierSelectionReportResource($report))->toArray(request()),
            'admin_thu_mua_users' => $adminThuMuaUsers,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(SupplierSelectionReport $supplierSelectionReport)
    {
        // Check condition to view report
        try {
            $this->authorize('view', $supplierSelectionReport);
        } catch (AuthorizationException $e) {
            return redirect()->route('supplier_selection_reports.index')->with('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền xem phiếu này.',
            ]);
        }

        $report = $supplierSelectionReport->load(['quotationFiles', 'proposalFiles', 'creator','manager','auditor','director', 'childReport']);

        // Gather activity logs for this report
        $logs = \App\Models\ActivityLog::with('user')
            ->where('subject_type', SupplierSelectionReport::class)
            ->where('subject_id', $supplierSelectionReport->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'user_role' => $log->user?->role?->name,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toDateTimeString(),
                ];
            });

        return Inertia::render('SupplierSelectionReportShow', [
            'report' => (new SupplierSelectionReportResource($report))->toArray(request()),
            'activity_logs' => $logs,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('update', $supplierSelectionReport);

        // Nếu người dùng upload ảnh mới, kiểm tra kích thước <= 10MB
        if ($request->hasFile('file_path')) {
            $request->validate([
                'file_path' => ['image', 'max:10240'], // 10MB
            ]);
        }

        // Lấy field text chuẩn
        $data = $request->safe()->only(['description']);
        // Lưu adm_id nếu có (ép về null nếu rỗng, ép về int nếu có)
        $adminId = $request->input('admin_thu_mua_id');
        $data['adm_id'] = ($adminId === '' || $adminId === null) ? null : (int)$adminId;

        // Giữ nguyên code cũ, không sinh lại khi update

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
        $deletedQuotationIds = collect($request->input('deleted_quotation_file_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map('intval')
            ->values();
        if ($deletedQuotationIds->isNotEmpty()) {
            $filesToDelete = $supplierSelectionReport->quotationFiles()
                ->whereIn('id', $deletedQuotationIds)
                ->get();
            foreach ($filesToDelete as $qf) {
                if ($qf->file_path && Storage::disk('public')->exists($qf->file_path)) {
                    Storage::disk('public')->delete($qf->file_path);
                }
                $qf->delete();
            }
        }

        // ---- 2b) Xử lý xoá file đề nghị/BOQ cũ (nếu người dùng đánh dấu)
        $deletedProposalIds = collect($request->input('deleted_proposal_file_ids', []))
            ->filter(fn($id) => is_numeric($id))
            ->map('intval')
            ->values();
        if ($deletedProposalIds->isNotEmpty()) {
            $proposalFilesToDelete = $supplierSelectionReport->proposalFiles()
                ->whereIn('id', $deletedProposalIds)
                ->get();
            foreach ($proposalFilesToDelete as $pf) {
                if ($pf->file_path && Storage::disk('public')->exists($pf->file_path)) {
                    Storage::disk('public')->delete($pf->file_path);
                }
                $pf->delete();
            }
        }
        // File đề nghị/BOQ không bắt buộc, có thể có hoặc không

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

        // ---- 3b) Thêm file đề nghị/BOQ mới (nếu có, không bắt buộc)
        if ($request->hasFile('proposal_files')) {
            foreach ($request->file('proposal_files') as $file) {
                $supplierSelectionReport->proposalFiles()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $this->uploadFile($file), // Sử dụng helper function để upload
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }
        // Lưu ý: File đề nghị/BOQ không bắt buộc, không cần kiểm tra số lượng

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
            ->whereNotIn('id', $deletedQuotationIds)
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
        // Chuẩn bị thông tin thay đổi để log
        $original = $supplierSelectionReport->getOriginal();
        $supplierSelectionReport->fill($data);
        $dirty = $supplierSelectionReport->getDirty();
        $supplierSelectionReport->save();

        // Log activity: updated (nếu có thay đổi)
        if (!empty($dirty)) {
            ActivityLogger::log($request, 'updated', $supplierSelectionReport, [
                'changed' => $dirty,
            ]);
        }

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

            // Xóa file đề nghị/BOQ
            foreach ($supplierSelectionReport->proposalFiles as $proposalFile) {
                if ($proposalFile->file_path && Storage::disk('public')->exists($proposalFile->file_path)) {
                    Storage::disk('public')->delete($proposalFile->file_path);
                }
                $proposalFile->delete();
            }

            // Xóa file báo cáo
            if ($supplierSelectionReport->file_path && Storage::disk('public')->exists($supplierSelectionReport->file_path)) {
                Storage::disk('public')->delete($supplierSelectionReport->file_path);
            }

            $id = $supplierSelectionReport->id;
            $code = $supplierSelectionReport->code;
            $supplierSelectionReport->delete();

            // Log activity: deleted
            ActivityLogger::log(request(), 'deleted', null, [
                'id' => $id,
                'code' => $code,
                'subject_type' => SupplierSelectionReport::class,
            ]);

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
        if ($supplierSelectionReport->creator_id !== Auth::id()) {
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $managerId = $request->input('manager_id');
        if (!$managerId) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Vui lòng chọn Trưởng phòng duyệt.'
            ]);
        }

        $manager = User::whereHas('role', function($q) {
                $q->where('name', 'Trưởng phòng');
            })->where('id', $managerId)->first();
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

        ActivityLogger::log($request, 'submitted_to_manager', $supplierSelectionReport, [
            'manager_id' => $manager->id,
            'manager_name' => $manager->name,
        ]);

        Notification::route('mail', $manager->email)->notify(new SupplierSelectionReportCreated($supplierSelectionReport));

        return redirect()->route('supplier_selection_reports.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được gửi duyệt thành công!'
            ]);
    }

    /**
     * Trưởng phòng duyệt phiếu
     */
    public function managerApprove(ManagerApproveSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $user = $request->user();
        if (optional($user->role)->name !== 'Trưởng phòng') {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Bạn không có quyền duyệt phiếu này.'
            ]);
        }

        $validated = $request->validated();
        $supplierSelectionReport->manager_approved_result = $validated['manager_approved_result'];
        $supplierSelectionReport->manager_approved_notes = $validated['manager_approved_notes'] ?? null;

        if ('approved' == $validated['manager_approved_result']) {
            $supplierSelectionReport->status = ReportStatus::MANAGER_APPROVED;
            ActivityLogger::log($request, 'manager_approved', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->manager_approved_notes,
            ]);
        } else {
            $supplierSelectionReport->status = ReportStatus::REJECTED;
            ActivityLogger::log($request, 'manager_rejected', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->manager_approved_notes,
            ]);
        }

        $supplierSelectionReport->manager_id = Auth::id();
        $supplierSelectionReport->manager_approved_at = now();
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status === ReportStatus::MANAGER_APPROVED) {
            $auditors = User::whereHas('role', function($q) {
                $q->where('name', 'Nhân viên Kiểm Soát');
            })->get();
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
        if (optional($user->role)->name !== 'Nhân viên Kiểm Soát') {
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
            $supplierSelectionReport->status = ReportStatus::AUDITOR_APPROVED;
            ActivityLogger::log($request, 'auditor_approved', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->auditor_audited_notes,
            ]);
        } else {
            $supplierSelectionReport->status = ReportStatus::REJECTED;
            ActivityLogger::log($request, 'auditor_rejected', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->auditor_audited_notes,
            ]);
        }

        $supplierSelectionReport->auditor_audited_at = now();
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status !== ReportStatus::AUDITOR_APPROVED) {
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
        if ($supplierSelectionReport->status !== ReportStatus::AUDITOR_APPROVED) {
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

        $director = User::whereHas('role', function($q) {
                $q->where('name', 'Giám đốc');
            })->where('id', $directorId)->first();
        if (!$director) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Người duyệt không hợp lệ.'
            ]);
        }

        $supplierSelectionReport->update([
            'status' => ReportStatus::PENDING_DIRECTOR,
            'director_id' => $director->id,
        ]);

        ActivityLogger::log($request, 'submitted_to_director', $supplierSelectionReport, [
            'director_id' => $director->id,
            'director_name' => $director->name,
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
        if (optional($user->role)->name !== 'Giám đốc') {
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
            $supplierSelectionReport->status = ReportStatus::DIRECTOR_APPROVED;
            ActivityLogger::log($request, 'director_approved', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->director_approved_notes,
            ]);
        } else {
            $supplierSelectionReport->status = ReportStatus::REJECTED;
            ActivityLogger::log($request, 'director_rejected', $supplierSelectionReport, [
                'notes' => $supplierSelectionReport->director_approved_notes,
            ]);
        }

        $supplierSelectionReport->save();

        $creator = $supplierSelectionReport->creator;
        $auditor = $supplierSelectionReport->auditor;
        $adminThuMua = $supplierSelectionReport->adminThuMua;

        if ($validated['director_approved_result'] === 'approved') {
            Notification::route('mail', $creator->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            if ($auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            }
            if ($adminThuMua) {
                Notification::route('mail', $adminThuMua->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
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
    /**
     * Lấy danh sách Admin Thu Mua (và thêm user login nếu là Nhân viên mua hàng)
     */
    private function getAdminThuMuaUsers($user)
    {
        $admins = User::whereHas('role', function($q) {
            $q->where('name', 'Admin Thu Mua');
        })
        ->select('id', 'name', 'email')
        ->get();

        // Nếu user login là Nhân viên mua hàng, thêm vào danh sách nếu chưa có
        if (optional($user->role)->name === 'Nhân viên mua hàng') {
            $exists = $admins->contains('id', $user->id);
            if (!$exists) {
                $admins->push($user->only(['id', 'name', 'email']));
            }
        }

        return $admins;
    }
}
