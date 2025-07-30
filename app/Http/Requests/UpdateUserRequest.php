<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'email' => 'required|email|unique:users,email,'.$this->user->id,
            'role' => 'required',
            'status' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Bạn phải nhập tên.',
            'email.required' => 'Bạn phải nhập email.',
            'email.email' => 'Email sai định dạng.',
            'email.unique' => 'Email đã tồn tại.',
            'role.required' => 'Bạn phải nhập vai trò.',
            'status.required' => 'Bạn phải nhập trạng thái.',
        ];
    }
}
