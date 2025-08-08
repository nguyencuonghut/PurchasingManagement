<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierSelectionReportNeedAuditorAudit extends Notification implements ShouldQueue
{
    use Queueable;
    protected $report;

    /**
     * Create a new notification instance.
     */
    public function __construct($report)
    {
        $this->report = $report;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $url = '/supplier_selection_reports/' . $this->report->id;
        return (new MailMessage)
                    ->subject('Đề nghị kiểm tra báo cáo lựa chọn nhà cung cấp số ' . $this->report->code)
                    ->line('Xin mời kiểm tra báo cáo lựa chọn nhà cung cấp sau đây:')
                    ->line('- Số: ' . $this->report->code)
                    ->line('- Mô tả: ' . $this->report->description)
                    ->action('Kiểm tra', url($url))
                    ->line('Xin cảm ơn!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
