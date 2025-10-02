<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ResetPasswordController extends Controller
{
    public function reset(Request $request)
    {
        // Log các thông tin request để debug
        Log::info('Password Reset Request', [
            'token' => $request->token,
            'email' => $request->email,
            'has_password' => !empty($request->password),
            'has_confirmation' => !empty($request->password_confirmation),
        ]);

        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ], [
            'token.required' => 'Token không hợp lệ.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'password.required' => 'Mật khẩu không được để trống.',
            'password.confirmed' => 'Xác nhận mật khẩu không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect('/login')->with([
                'message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập với mật khẩu mới.',
                'type' => 'success'
            ]);
        } else {
            // Log status error để debug
            Log::error('Password reset failed', ['status' => $status, 'translated' => __($status)]);

            $translatedMessage = __($status);
            if ($translatedMessage === 'This password reset token is invalid.') {
                $translatedMessage = 'Liên kết đặt lại mật khẩu không hợp lệ, đã hết hạn hoặc email không khớp với yêu cầu ban đầu.';
            }

            return back()->withErrors([
                'email' => $translatedMessage
            ]);
        }
    }
}
