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

class SupplierSelectionReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $reports = SupplierSelectionReport::with('quotationFiles')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($report) {
            return collect($report)->only(['id', 'code', 'description', 'file_path', 'image_url', 'status', 'creator_id'])
                ->merge(['quotation_files_count' => $report->quotation_files_count]);
        });

        // Lấy quyền của người dùng hiện tại
        $user = $request->user();

        $canCreate = $user->can('create', SupplierSelectionReport::class);
        $canUpdate = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua' || $user->role === 'Trưởng phòng Thu Mua';
        $canDelete = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua' || $user->role === 'Trưởng phòng Thu Mua';

        $can = [
            'create_report' => $canCreate,
            'update_report' => $canUpdate,
            'delete_report' => $canDelete,
        ];

        return Inertia::render('SupplierSelectionReportIndex', [
            'reports' => $reports,
            'can' => $can,
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

        try {
            $data = $request->validated();
            $oldFilePath = $supplierSelectionReport->file_path;

            // Xử lý file_path
            if ($request->hasFile('file_path')) {
                $data['file_path'] = $this->uploadFile($request->file('file_path'));
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath);
                }
            } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
                $data['file_path'] = $this->saveBase64Image($request->file_path);
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath);
                }
            } elseif (is_null($request->file_path)) {
                $data['file_path'] = null;
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath);
                }
            }

            $supplierSelectionReport->update($data);

            // Xử lý file báo giá trong update
            if ($request->hasFile('quotation_files')) {
                foreach ($request->file('quotation_files') as $file) {
                    $quotationFile = new QuotationFile();
                    $quotationFile->file_name = $file->getClientOriginalName();
                    $quotationFile->file_path = $this->uploadFile($file);
                    $quotationFile->file_type = $file->getClientMimeType();
                    $quotationFile->file_size = $file->getSize();
                    $quotationFile->supplier_selection_report_id = $supplierSelectionReport->id;
                    $quotationFile->save();
                }
            }

            return redirect()->back()->with('flash', [
                'type' => 'success',
                'message' => 'Báo cáo đã được cập nhật thành công!',
            ]);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra trong quá trình xác thực dữ liệu.',
            ]);
        } catch (\Exception $e) {
            Log::error("Lỗi khi cập nhật báo cáo: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Đã xảy ra lỗi không mong muốn khi cập nhật báo cáo.',
            ]);
        }
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

    public function requestManagerToApprove(SupplierSelectionReport $supplierSelectionReport)
    {
        if ($supplierSelectionReport->creator_id !== auth()->id()) {
            return redirect()->back()->withErrors('Bạn không có quyền!');
        }

        $supplierSelectionReport->update([
            'status' => 'pending_manager_approval',
        ]);

        $reviewers = User::where('role', 'Trưởng phòng Thu Mua')->get();
        foreach ($reviewers as $reviewer) {
            Notification::route('mail', $reviewer->email)->notify(new SupplierSelectionReportCreated($supplierSelectionReport));
        }

        return redirect()->route('supplier_selection_reports.index')
            ->with('success', 'Báo cáo đã được gửi duyệt thành công.');
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

        if ($supplierSelectionReport->status === 'auditor_approved') {
            $directors = User::where('role', 'Giám đốc')->get();
            foreach ($directors as $director) {
                Notification::route('mail', $director->email)->notify(new SupplierSelectionReportNeedDirectorApproval($supplierSelectionReport));
            }
        } else {
            Notification::route('mail', $supplierSelectionReport->creator->email)->notify(new SupplierSelectionReportRejectedByAuditor($supplierSelectionReport));
        }

        return redirect()->route('supplier_selection_reports.show', $supplierSelectionReport->id)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Đã gửi review thành công!'
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
