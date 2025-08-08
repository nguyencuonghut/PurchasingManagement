<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\SupplierSelectionReport;

class SupplierSelectionReportRejectedByDirector extends Notification
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
                    ->subject('Phiếu lựa chọn nhà cung cấp mã '. $this->report->code . ' bị Giám đốc từ chối')
                    ->greeting('Xin chào,')
                    ->line('Phiếu lựa chọn nhà cung cấp với mã: ' . $this->report->code . ' đã bị Giám đốc từ chối.')
                    ->line('Lý do: ' . ($this->report->director_approved_notes ?? 'Không có lý do cụ thể'))
                    ->action('Xem chi tiết', url(route('supplier_selection_reports.show', $this->report->id)))
                    ->line('Vui lòng kiểm tra và cập nhật lại nếu cần thiết.');
    }
}
