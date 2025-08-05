<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierSelectionReportRequest;
use App\Http\Requests\UpdateSupplierSelectionReportRequest;
use App\Models\SupplierSelectionReport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // THÊM DÒNG NÀY
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            return collect($report)->only(['id', 'code', 'description', 'file_path', 'image_url']);
        });

        // Lấy quyền của người dùng hiện tại
        $user = $request->user(); // Lấy đối tượng người dùng một lần

        $canCreate = $user->can('create', SupplierSelectionReport::class);
        $canUpdate = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua'; // Ví dụ: Admin hoặc Editor có thể update
        $canDelete = $user->role === 'Quản trị' || $user->role === 'Nhân viên Thu Mua'; // Ví dụ: Chỉ Admin có thể delete

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

            SupplierSelectionReport::create($data);

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
        //
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
