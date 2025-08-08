<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManagerApproveSupplierSelectionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Có thể kiểm tra quyền ở đây nếu muốn
        return true;
    }

    public function rules(): array
    {
        return [
            'manager_approved_result' => 'required|in:approved,rejected',
            'manager_approved_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'manager_approved_result.required' => 'Vui lòng chọn trạng thái duyệt.',
            'manager_approved_result.in' => 'Trạng thái duyệt không hợp lệ.',
            'manager_approved_notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
