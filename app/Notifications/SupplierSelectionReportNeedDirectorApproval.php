<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\SupplierSelectionReport;

class SupplierSelectionReportNeedDirectorApproval extends Notification implements ShouldQueue
{
    use Queueable;
    public $report;

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
            ->subject('Yêu cầu Giám đốc duyệt phiếu BCLCNCC')
            ->line('Có một phiếu BCLCNCC đã được kiểm tra và cần Giám đốc duyệt: ' . $this->report->code)
            ->action('Xem chi tiết', url('/supplier_selection_reports/' . $this->report->id));
    }
}
