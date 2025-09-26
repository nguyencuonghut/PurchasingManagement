<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DirectorApproveSupplierSelectionReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && optional($this->user()->role)->name === 'Giám đốc';
    }

    public function rules(): array
    {
        return [
            'director_approved_result' => 'required|in:approved,rejected',
            'director_approved_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'director_approved_result.required' => 'Vui lòng chọn trạng thái duyệt.',
            'director_approved_result.in' => 'Trạng thái duyệt không hợp lệ.',
            'director_approved_notes.max' => 'Ghi chú không được vượt quá 1000 ký tự.',
        ];
    }
}
