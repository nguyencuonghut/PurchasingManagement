<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UserRoles;

class AuditorAuditSupplierSelectionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && optional($this->user()->role)->name === 'Nhân viên Kiểm Soát';
    }

    public function rules(): array
    {
        return [
            'auditor_audited_result' => 'required|in:approved,rejected',
            'auditor_audited_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'auditor_audited_result.required' => 'Vui lòng chọn trạng thái duyệt.',
            'auditor_audited_result.in' => 'Trạng thái duyệt không hợp lệ.',
            'auditor_audited_notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
