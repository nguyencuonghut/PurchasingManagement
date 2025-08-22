<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierSelectionReportRequest extends FormRequest
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
    public function rules()
    {
        return [
            'code' => ['required','string','max:255'],
            'description' => ['required','string','max:1000'],

            'file_path' => ['nullable'], // có thể là file (multipart) hoặc base64 hoặc URL chuỗi
            'file_path_removed' => ['sometimes','boolean'],

            'quotation_files' => ['sometimes','array'],
            'quotation_files.*' => ['file','mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png','max:20480'],

            'deleted_quotation_file_ids' => ['sometimes','array'],
            'deleted_quotation_file_ids.*' => ['integer','exists:quotation_files,id'],
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
            'code.max' => 'Mã báo cáo không được vượt quá 255 ký tự.',
            'description.required' => 'Mô tả không được để trống.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',

            'file_path_removed.boolean' => 'Trường xóa file phải là giá trị boolean.',

            'quotation_files.array' => 'Danh sách file báo giá phải là một mảng.',

            'quotation_files.*.file' => 'Mỗi file báo giá phải là một file hợp lệ.',
            'quotation_files.*.mimes' => 'File báo giá phải có định dạng: pdf, doc, docx, xls, xlsx, jpg, jpeg, png.',
            'quotation_files.*.max' => 'File báo giá không được vượt quá 20MB.',

            'deleted_quotation_file_ids.array' => 'Danh sách ID file báo giá cần xóa phải là một mảng.',
            'deleted_quotation_file_ids.*.integer' => 'Mỗi ID file báo giá cần xóa phải là số nguyên.',
            'deleted_quotation_file_ids.*.exists' => 'File báo giá cần xóa không tồn tại trong hệ thống.',
        ];
    }
}
