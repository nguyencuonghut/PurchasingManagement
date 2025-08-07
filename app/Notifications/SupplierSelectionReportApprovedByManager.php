<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierSelectionReportApprovedByManager extends Notification implements ShouldQueue
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
                    ->subject('Quản lý phòng Thu Mua đã duyệt báo cáo cáo lựa chọn nhà cung cấp số: ' . $this->report->code)
                    ->line('Báo cáo lựa chọn nhà cung cấp số:' . $this->report->code . ' đã được phê duyệt bởi quản lý phòng Thu Mua.')
                    ->action('Chi tiết', url($url))
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
