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

            // Admin Thu Mua (nullable, phải là id user hợp lệ nếu có)
            'admin_thu_mua_id' => ['nullable', 'exists:users,id'],

            // Ảnh: theo cấu hình uploads.image (mimes + max KB)
            'file_path' => [
                'nullable',
                'file',
                'mimes:' . implode(',', config('uploads.image.mimes', ['jpeg','jpg','png'])),
                'max:' . (int) config('uploads.image.max_kb', 10240),
            ],

            // File báo giá có thể nhiều file (theo cấu hình uploads.quotation)
            'quotation_files' => ['sometimes','array'],
            'quotation_files.*' => [
                'file',
                'mimes:' . implode(',', config('uploads.quotation.mimes', ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'])),
                'max:' . (int) config('uploads.quotation.max_kb', 20480),
            ],
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

            'admin_thu_mua_id.exists' => 'Admin Thu Mua không hợp lệ.',

            'file_path.image' => 'Ảnh báo cáo phải là một file ảnh hợp lệ.',
            'file_path.max' => 'Ảnh báo cáo không được vượt quá 10MB.',

            'quotation_files.array' => 'Danh sách file báo giá phải là một mảng.',
            'quotation_files.*.file' => 'Mỗi file báo giá phải là một file hợp lệ.',
            'quotation_files.*.mimes' => 'File báo giá phải có định dạng: pdf, doc, docx, xls, xlsx, jpg, jpeg, png.',
            'quotation_files.*.max' => 'File báo giá không được vượt quá 20MB.',
        ];
    }
}
