<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditorReviewSupplierSelectionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Có thể kiểm tra quyền ở đây nếu muốn
        return true;
    }

    public function rules(): array
    {
        return [
            'reviewer_status' => 'required|in:approved,rejected',
            'reviewer_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'reviewer_status.required' => 'Vui lòng chọn trạng thái duyệt.',
            'reviewer_status.in' => 'Trạng thái duyệt không hợp lệ.',
            'reviewer_notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
