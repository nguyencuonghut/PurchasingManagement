<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierSelectionReportRequest;
use App\Http\Requests\UpdateSupplierSelectionReportRequest;
use App\Models\SupplierSelectionReport;
use App\Models\User;
use App\Notifications\SupplierSelectionReportApprovedByDirector;
use App\Notifications\SupplierSelectionReportApprovedByManager;
use App\Notifications\SupplierSelectionReportCreated;
use App\Notifications\SupplierSelectionReportNeedAuditorReview;
use App\Notifications\SupplierSelectionReportNeedDirectorApproval;
use App\Notifications\SupplierSelectionReportRejectedByAuditor;
use App\Notifications\SupplierSelectionReportRejectedByDirector;
use App\Notifications\SupplierSelectionReportRejectedByManager;
use App\Http\Requests\DirectorApproveSupplierSelectionReportRequest;
use App\Http\Requests\ManagerReviewSupplierSelectionReportRequest;
use App\Http\Requests\AuditorAuditSupplierSelectionReportRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // THÊM DÒNG NÀY
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
        $reports = SupplierSelectionReport::all()->map(function ($report) {
            return collect($report)->only(['id', 'code', 'description', 'file_path', 'image_url', 'status', 'creator_id']);
        });

        // Lấy quyền của người dùng hiện tại
        $user = $request->user(); // Lấy đối tượng người dùng một lần

        $canCreate = $user->can('create', SupplierSelectionReport::class);
        $canUpdate = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua' || $user->role === 'Trưởng phòng Thu Mua'; // Ví dụ: Admin hoặc Editor có thể update
        $canDelete = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua'|| $user->role === 'Trưởng phòng Thu Mua'; // Ví dụ: Chỉ Admin có thể delete

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
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierSelectionReportRequest $request)
    {
        $this->authorize('create', SupplierSelectionReport::class);

        try {
            $data = $request->validated();
            $data['creator_id'] = $request->user()->id; // Gán user_id của người tạo

            // Xử lý file_path
            if ($request->hasFile('file_path')) {
                // Đây là trường hợp người dùng upload file mới (từ input type="file")
                $data['file_path'] = $this->uploadFile($request->file('file_path'));
            } elseif ($request->file_path instanceof \Illuminate\Http\UploadedFile) {
                 // Trường hợp này có thể xảy ra nếu file_path là một UploadedFile nhưng không được nhận diện bởi hasFile()
                 // (thường xảy ra với các cách upload phức tạp hơn như từ Base64)
                 $data['file_path'] = $this->uploadFile($request->file_path);
            } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
                // Đây là trường hợp người dùng paste ảnh (Data URL)
                $data['file_path'] = $this->saveBase64Image($request->file_path);
            } else {
                // Nếu không có file mới được gửi (hoặc file_path là null/empty string)
                $data['file_path'] = null;
            }

            // Nếu người tạo là Trưởng phòng Thu Mua, thì trạng thái sẽ là 'manager_approved'
            if ($request->user()->role === 'Trưởng phòng Thu Mua') {
                $data['status'] = 'manager_approved';
                $data['manager_approved_at'] = now(); // Ghi lại thời gian duyệt
            } else {
                // Mặc định trạng thái là 'draft' nếu không phải Trưởng phòng Thu Mua
                $data['status'] = 'draft';
            }
            // Tạo bản ghi mới trong cơ sở dữ liệu
            SupplierSelectionReport::create($data);

            // Gửi thông báo cho Nhân viên Kiểm Soát trong trường hợp Trưởng phòng Thu Mua đã duyệt
            if ($data['status'] === 'manager_approved') {
                $auditors = User::where('role', 'Nhân viên Kiểm Soát')->get();
                foreach ($auditors as $auditor) {
                    Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportNeedAuditorReview($data));
                }
            }
            return redirect()->back()->with('flash', [
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
        return Inertia::render('SupplierSelectionReportShow', [
            'report' => $supplierSelectionReport->only(['id', 'code', 'description', 'file_path', 'image_url', 'status', 'manager_approved_result', 'manager_approved_notes', 'auditor_audited_result', 'auditor_audited_notes', 'director_approved_result', 'director_approved_notes', 'creator_name', 'manager_name', 'auditor_name', 'director_name', 'creator_id', 'manager_id', 'auditor_id', 'director_id', 'manager_approved_at', 'auditor_audited_at', 'director_approved_at', 'created_at', 'updated_at']),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SupplierSelectionReport $supplierSelectionReport)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
    {
        $this->authorize('update', $supplierSelectionReport);

        try {
            // Lấy tất cả dữ liệu đã được validate
            $data = $request->validated();

            $oldFilePath = $supplierSelectionReport->file_path; // Lấy đường dẫn file cũ

            // Xử lý file_path mới
            if ($request->hasFile('file_path')) {
                // Người dùng đã upload file mới (từ input type="file" hoặc UploadedFile)
                $data['file_path'] = $this->uploadFile($request->file('file_path'));
                // Xóa file cũ nếu có
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath);
                }
            } elseif (is_string($request->file_path) && str_starts_with($request->file_path, 'data:image')) {
                // Người dùng đã paste ảnh (Data URL)
                $data['file_path'] = $this->saveBase64Image($request->file_path);
                // Xóa file cũ nếu có
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath);
                }
            } elseif (is_null($request->file_path)) {
                // Người dùng đã xóa ảnh (file_path được gửi là null từ frontend)
                $data['file_path'] = null; // Đặt thành null trong DB
                if ($oldFilePath && Storage::disk('public')->exists($oldFilePath)) {
                    $this->deleteFile($oldFilePath); // Xóa file cũ
                }
            } else {
                // Trường hợp người dùng không thay đổi file_path (giữ file_path cũ)
                // Đảm bảo không ghi đè file_path nếu nó không được thay đổi bởi request
                // Laravel sẽ tự động bỏ qua nếu thuộc tính không có trong $fillable
                // hoặc nếu giá trị không thay đổi. Tuy nhiên, để tường minh, ta có thể bỏ nó ra khỏi $data.
                // Hoặc đơn giản là không làm gì, vì $data đã chứa các trường khác.
                // Nếu file_path là string và không phải data:image (tức là đường dẫn cũ từ DB),
                // thì ta giữ nguyên nó trong $data để nó được update lại với chính nó.
                // Không cần unset($data['file_path']) ở đây nếu bạn muốn nó luôn có trong $data cho mọi trường hợp.
                // Với trường hợp này, $data['file_path'] sẽ giữ nguyên giá trị string ban đầu của nó từ DB
                // nếu người dùng không tương tác với input file.
            }

            // Cập nhật tất cả các trường từ $data đã validated, bao gồm 'code', 'description' và 'file_path'
            $supplierSelectionReport->update($data);

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
            // Xóa file đính kèm trước khi xóa bản ghi
            if ($supplierSelectionReport->file_path) {
                $this->deleteFile($supplierSelectionReport->file_path);
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
        // 1. Kiểm tra quyền
        // Đảm bảo chỉ người tạo báo cáo mới có thể gửi duyệt
        if ($supplierSelectionReport->creator_id !== auth()->id()) {
            return redirect()->back()->withErrors('Bạn không có quyền!');

       }

        // 2. Cập nhật trạng thái
        // Thay đổi 'status' từ 'draft' sang 'pending_manager_approval' cho Trưởng phòng Thu Mua duyệt
        $supplierSelectionReport->update([
            'status' => 'pending_manager_approval',
        ]);

        // 3. Gửi email thông báo
        $reviewers = User::where('role', 'Trưởng phòng Thu Mua')->get();
        foreach ($reviewers as $reviewer) {
            Notification::route('mail' , $reviewer->email)->notify(new SupplierSelectionReportCreated($supplierSelectionReport));
        }

        // 4. Trả về phản hồi thành công
        return redirect()->route('supplier_selection_reports.index')
            ->with('success', 'Báo cáo đã được gửi duyệt thành công.');
    }

    /**
     * Trưởng phòng Thu Mua duyệt phiếu
     */
    public function managerApprove(ManagerReviewSupplierSelectionReportRequest $request, SupplierSelectionReport $supplierSelectionReport)
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
        $supplierSelectionReport->manager_approved_at = now(); // Ghi lại thời gian duyệt
        // Lưu bản ghi đã cập nhật
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status === 'manager_approved') {
            // Gửi notification cho Nhân viên Kiểm Soát (yêu cầu review)
            $auditors = User::where('role', 'Nhân viên Kiểm Soát')->get();
            foreach ($auditors as $auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportNeedAuditorReview($supplierSelectionReport));
            }
            // Gửi notification cho người tạo phiếu (kết quả duyệt)
            Notification::route('mail', $supplierSelectionReport->creator->email)->notify(new SupplierSelectionReportApprovedByManager($supplierSelectionReport));
        } else {
            // Nếu rejected, thông báo cho người tạo phiếu
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
        // Cập nhật trạng thái và ghi chú của Nhân viên Kiểm Soát
        $supplierSelectionReport->auditor_audited_result = $validated['auditor_audited_result'];
        $supplierSelectionReport->auditor_audited_notes = $validated['auditor_audited_notes'] ?? null;
        $supplierSelectionReport->auditor_id = Auth::id(); // Gán ID của người review
        if ($validated['auditor_audited_result'] === 'approved') {
            $supplierSelectionReport->status = 'auditor_approved';
        } else {
            $supplierSelectionReport->status = 'rejected';
        }
        $supplierSelectionReport->auditor_audited_at = now(); // Ghi lại thời gian review
        // Lưu bản ghi đã cập nhật
        $supplierSelectionReport->save();

        if ($supplierSelectionReport->status === 'auditor_approved') {
            // Gửi notification cho Giám đốc
            $directors = User::where('role', 'Giám đốc')->get();
            foreach ($directors as $director) {
                Notification::route('mail', $director->email)->notify(new SupplierSelectionReportNeedDirectorApproval($supplierSelectionReport));
            }
        } else {
            // Nếu rejected, thông báo cho người tạo phiếu
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

        // Cập nhật thông tin duyệt của Giám đốc
        $supplierSelectionReport->director_approved_result = $validated['director_approved_result'];
        $supplierSelectionReport->director_approved_notes = $validated['director_approved_notes'] ?? null;
        $supplierSelectionReport->director_id = Auth::id();
        $supplierSelectionReport->director_approved_at = now();

        // Cập nhật trạng thái dựa trên kết quả duyệt
        if ($validated['director_approved_result'] === 'approved') {
            $supplierSelectionReport->status = 'director_approved';
        } else {
            $supplierSelectionReport->status = 'rejected';
        }

        $supplierSelectionReport->save();

        // Gửi email notification cho creator và auditor
        $creator = $supplierSelectionReport->creator;
        $auditor = $supplierSelectionReport->auditor;

        if ($validated['director_approved_result'] === 'approved') {
            // Gửi notification cho creator và auditor khi được duyệt
            Notification::route('mail', $creator->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            if ($auditor) {
                Notification::route('mail', $auditor->email)->notify(new SupplierSelectionReportApprovedByDirector($supplierSelectionReport));
            }
        } else {
            // Gửi notification cho creator và auditor khi bị từ chối
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
        // Tách header (data:image/png;base64,) và data
        list($type, $data) = explode(';', $base64Image);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);

        // Lấy đuôi file từ MIME type
        preg_match('/^data:image\/(\w+);base64/', $base64Image, $matches);
        $extension = $matches[1] ?? 'png'; // Mặc định là png nếu không xác định được

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
