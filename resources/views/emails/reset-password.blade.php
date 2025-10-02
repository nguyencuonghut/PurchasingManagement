@component('mail::message')
# Đặt lại mật khẩu

Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.

@component('mail::button', ['url' => $url])
Đặt lại mật khẩu
@endcomponent

Liên kết đặt lại mật khẩu này sẽ hết hạn sau {{ config('auth.passwords.users.expire') }} phút.

Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.

Trân trọng,<br>
{{ config('app.name') }}

@component('mail::subcopy')
Nếu bạn gặp sự cố khi nhấp vào nút "Đặt lại mật khẩu", hãy sao chép và dán URL dưới đây vào trình duyệt web của bạn: <br>
<span class="break-all">[{{ $url }}]({{ $url }})</span>
@endcomponent
@endcomponent
