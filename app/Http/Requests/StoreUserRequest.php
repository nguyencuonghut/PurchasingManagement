<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:6',
            'role_id' => 'required|exists:roles,id',
            'status' => 'required',
            'department_id' => 'exists:departments,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bạn phải nhập tên.',
            'email.required' => 'Bạn phải nhập email.',
            'email.email' => 'Email sai định dạng.',
            'email.unique' => 'Email đã tồn tại.',
            'password.required' => 'Bạn phải nhập mật khẩu.',
            'password.confirmed' => 'Mật khẩu không khớp.',
            'password.min' => 'Mật khẩu phải dài ít nhất 6 ký tự.',
            'role_id.required' => 'Bạn phải nhập vai trò.',
            'role_id.exists' => 'Vai trò không hợp lệ.',
            'status.required' => 'Bạn phải nhập trạng thái.',
            'department_id.exists' => 'Phòng ban không hợp lệ.',
        ];
    }
}
