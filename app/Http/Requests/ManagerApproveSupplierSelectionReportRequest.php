<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManagerApproveSupplierSelectionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && optional($this->user()->role)->name === 'Trưởng phòng';
    }

    public function rules(): array
    {
        return [
            'manager_approved_result' => 'required|in:approved,rejected',
            'manager_approved_notes' => 'nullable|string|max:1000',
            'is_urgent' => 'nullable|boolean',
            'director_id' => 'required_if:is_urgent,true|nullable|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'manager_approved_result.required' => 'Vui lòng chọn trạng thái duyệt.',
            'manager_approved_result.in' => 'Trạng thái duyệt không hợp lệ.',
            'manager_approved_notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
            'director_id.required_if' => 'Vui lòng chọn Giám đốc cho phiếu khẩn cấp.',
            'director_id.exists' => 'Giám đốc được chọn không tồn tại.',
        ];
    }
}
