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
    public function destroy(Request $request, QuotationFile $quotationFile)
    {
        $this->authorize('delete', $quotationFile);

        try {
            // Delete the file from storage
            if ($quotationFile->file_path && Storage::disk('public')->exists($quotationFile->file_path)) {
                Storage::disk('public')->delete($quotationFile->file_path);
            }

            // Delete the database record
            $quotationFile->delete();

            // Return JSON for XHR/JSON/Inertia requests
            if ($request->expectsJson() || $request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'File báo giá đã được xóa thành công!'
                ], 200);
            }

            // Fallback to redirect with unified flash message
            return redirect()->back()->with('flash', [
                'type' => 'success',
                'message' => 'File báo giá đã được xóa thành công!',
            ]);
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->wantsJson() || $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Có lỗi xảy ra khi xóa file báo giá.'
                ], 500);
            }

            return redirect()->back()->with('flash', [
                'type' => 'error',
                'message' => 'Có lỗi xảy ra khi xóa file báo giá.',
            ]);
        }
    }
}
