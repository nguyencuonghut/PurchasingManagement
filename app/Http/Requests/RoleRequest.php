<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Vui lòng nhập tên vai trò.',
            'name.string' => 'Tên vai trò phải là một chuỗi ký tự.',
            'name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
        ];
    }
}
