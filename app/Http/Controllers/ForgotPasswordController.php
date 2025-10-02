<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

        // Kiểm tra xem có token reset password cũ không và đã hết hạn chưa
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();
        
        // Nếu có token cũ và đã hết hạn (quá 60 phút), xóa token cũ
        if ($tokenRecord) {
            $tokenCreatedAt = new \DateTime($tokenRecord->created_at);
            $now = new \DateTime();
            $interval = $tokenCreatedAt->diff($now);
            $minutesElapsed = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
            
            // Token đã hết hạn (quá 60 phút)
            if ($minutesElapsed >= 60) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
                
                // Log để theo dõi
                Log::info("Đã xóa token hết hạn cho email: {$request->email}");
            }
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
                $translatedMessage = 'Yêu cầu đã được tạo trước đó. Vui lòng kiểm tra email hoặc đợi 60 phút để yêu cầu lại.';
            }

            return back()->withErrors([
                'email' => $translatedMessage
            ]);
        }
    }
}
