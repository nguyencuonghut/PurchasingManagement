<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'code' => 'required|string|max:50|unique:departments,code',
            'name' => 'required|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'code.required' => 'Vui lòng nhập mã phòng ban.',
            'code.string' => 'Mã phòng ban phải là một chuỗi ký tự.',
            'code.max' => 'Mã phòng ban không được vượt quá 50 ký tự.',
            'code.unique' => 'Mã phòng ban đã tồn tại.',
            'name.required' => 'Vui lòng nhập tên phòng ban.',
            'name.string' => 'Tên phòng ban phải là một chuỗi ký tự.',
            'name.max' => 'Tên phòng ban không được vượt quá 255 ký tự.',
        ];
    }
}
