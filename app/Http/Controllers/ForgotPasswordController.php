<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withErrors([
                'email' => 'Email không tồn tại trong hệ thống.'
            ]);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with([
                'message' => 'Đường link đặt lại mật khẩu đã được gửi tới email.',
                'type' => 'success'
            ]);
        } else {
            // Translate "Please wait before retrying." message directly
            $translatedMessage = __($status);
            if ($translatedMessage === 'Please wait before retrying.') {
                $translatedMessage = 'Yêu cầu đã được tạo trước đó. Vui lòng kiểm tra email.';
            }

            return back()->withErrors([
                'email' => $translatedMessage
            ]);
        }
    }
}
