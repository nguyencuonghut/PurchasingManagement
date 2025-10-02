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
        // file_path có thể là: UploadedFile (multipart) hoặc string (URL/base64) hoặc null
        // Nếu là UploadedFile: kiểm tra theo cấu hình uploads.image (mimes + max KB)
        $filePathRules = ['nullable'];
        if ($this->hasFile('file_path')) {
            $filePathRules = [
                'nullable',
                'file',
                'mimes:' . implode(',', config('uploads.image.mimes', ['jpeg','jpg','png'])),
                'max:' . (int) config('uploads.image.max_kb', 10240),
            ];
        } else {
            // Cho phép URL/base64 string
            $filePathRules = ['nullable', 'string'];
        }

        return [
            'code' => ['required','string','max:255'],
            'description' => ['required','string','max:1000'],

            'file_path' => $filePathRules,
            'file_path_removed' => ['sometimes','boolean'],

            'quotation_files' => ['sometimes','array'],
            'quotation_files.*' => [
                'file',
                'mimes:' . implode(',', config('uploads.quotation.mimes', ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'])),
                'max:' . (int) config('uploads.quotation.max_kb', 20480),
            ],

            'deleted_quotation_file_ids' => ['sometimes','array'],
            'deleted_quotation_file_ids.*' => ['integer','exists:quotation_files,id'],

            'proposal_files' => ['sometimes','array'],
            'proposal_files.*' => [
                'file',
                'mimes:' . implode(',', config('uploads.quotation.mimes', ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'])),
                'max:' . (int) config('uploads.quotation.max_kb', 20480),
            ],

            'deleted_proposal_file_ids' => ['sometimes','array'],
            'deleted_proposal_file_ids.*' => ['integer','exists:proposal_files,id'],
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
            'file_path.image' => 'Ảnh báo cáo phải là một file ảnh hợp lệ.',
            'file_path.max' => 'Ảnh báo cáo không được vượt quá 10MB.',

            'quotation_files.array' => 'Danh sách file báo giá phải là một mảng.',
            'quotation_files.*.file' => 'Mỗi file báo giá phải là một file hợp lệ.',
            'quotation_files.*.mimes' => 'File báo giá phải có định dạng: pdf, doc, docx, xls, xlsx, jpg, jpeg, png.',
            'quotation_files.*.max' => 'File báo giá không được vượt quá 20MB.',

            'deleted_quotation_file_ids.array' => 'Danh sách ID file báo giá cần xóa phải là một mảng.',
            'deleted_quotation_file_ids.*.integer' => 'Mỗi ID file báo giá cần xóa phải là số nguyên.',
            'deleted_quotation_file_ids.*.exists' => 'File báo giá cần xóa không tồn tại trong hệ thống.',

            'proposal_files.array' => 'Danh sách file đề nghị/BOQ phải là một mảng.',
            'proposal_files.*.file' => 'Mỗi file đề nghị/BOQ phải là một file hợp lệ.',
            'proposal_files.*.mimes' => 'File đề nghị/BOQ phải có định dạng: pdf, doc, docx, xls, xlsx, jpg, jpeg, png.',
            'proposal_files.*.max' => 'File đề nghị/BOQ không được vượt quá 20MB.',

            'deleted_proposal_file_ids.array' => 'Danh sách ID file đề nghị/BOQ cần xóa phải là một mảng.',
            'deleted_proposal_file_ids.*.integer' => 'Mỗi ID file đề nghị/BOQ cần xóa phải là số nguyên.',
            'deleted_proposal_file_ids.*.exists' => 'File đề nghị/BOQ cần xóa không tồn tại trong hệ thống.',
        ];
    }
}
