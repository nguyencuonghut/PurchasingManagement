<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManagerApproveSupplierSelectionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && optional($this->user()->role)->name === 'Trưởng phòng Thu Mua';
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
