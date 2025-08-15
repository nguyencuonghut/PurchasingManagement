<?php

namespace App\Http\Controllers;

use App\Models\QuotationFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuotationFileController extends Controller
{
    /**
     * Remove the specified quotation file from storage.
     */
    public function destroy(QuotationFile $quotationFile)
    {
        $this->authorize('delete', $quotationFile);

        try {
            // Delete the file from storage
            if ($quotationFile->file_path && Storage::disk('public')->exists($quotationFile->file_path)) {
                Storage::disk('public')->delete($quotationFile->file_path);
            }

            // Delete the database record
            $quotationFile->delete();

            return redirect()->back()->with('flash', [
                'type' => 'success',
                'message' => 'File báo giá đã được xóa thành công!',
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra khi xóa file báo giá.',
            ]);
        }
    }
}
