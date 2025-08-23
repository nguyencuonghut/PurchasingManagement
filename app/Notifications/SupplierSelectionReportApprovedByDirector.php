<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\SupplierSelectionReport;

class SupplierSelectionReportApprovedByDirector extends Notification implements ShouldQueue
{
    use Queueable;

    protected $report;

    public function __construct(SupplierSelectionReport $report)
    {
        $this->report = $report;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Phiếu lựa chọn nhà cung cấp mã '. $this->report->code . ' đã được Giám đốc duyệt')
                    ->greeting('Xin chào,')
                    ->line('Phiếu lựa chọn nhà cung cấp với mã: ' . $this->report->code . ' đã được Giám đốc duyệt.')
                    ->action('Xem chi tiết', url(route('supplier_selection_reports.show', $this->report->id)))
                    ->line('Cảm ơn bạn đã sử dụng hệ thống.');
    }
}
