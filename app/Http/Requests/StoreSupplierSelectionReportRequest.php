<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierSelectionReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Hoặc logic phân quyền của bạn
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', Rule::unique('supplier_selection_reports')],
            'description' => ['required', 'string', 'max:1000'],
            'file_path' => ['nullable', 'string'], // Hoặc rules cho file upload nếu bạn gửi file trực tiếp
            // Thêm các rules khác nếu có
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Mã báo cáo không được để trống.',
            'code.unique' => 'Mã báo cáo đã tồn tại.',
            'code.max' => 'Mã báo cáo không được vượt quá 255 ký tự.',
            'description.required' => 'Mô tả không được để trống.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            // Thêm các thông báo lỗi khác
        ];
    }
}
