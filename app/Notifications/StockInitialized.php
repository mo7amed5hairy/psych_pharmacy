<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StockInitialized extends Notification
{
    use Queueable;

    protected $medicineName;
    protected $oldQuantity;
    protected $newQuantity;
    protected $date;

    /**
     * Create a new notification instance.
     */
    public function __construct($medicineName, $oldQuantity, $newQuantity, $date)
    {
        $this->medicineName = $medicineName;
        $this->oldQuantity = $oldQuantity;
        $this->newQuantity = $newQuantity;
        $this->date = $date;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $arabicMonths = [
            1 => 'يناير',
            2 => 'فبراير',
            3 => 'مارس',
            4 => 'إبريل',
            5 => 'مايو',
            6 => 'يونيو',
            7 => 'يوليو',
            8 => 'أغسطس',
            9 => 'سبتمبر',
            10 => 'أكتوبر',
            11 => 'نوفمبر',
            12 => 'ديسمبر'
        ];
        $monthNum = (int) date('m', strtotime($this->date));
        $year = date('Y', strtotime($this->date));
        $monthName = $arabicMonths[$monthNum] ?? $monthNum;

        return [
            'title' => 'تم ترحيل رصيد دواء',
            'message' => "الدواء ({$this->medicineName}) تم ترحيل رصيد قدره ({$this->oldQuantity}) في يوم 1 من شهر ({$monthName} {$year}) ليصبح الرصيد الكلي ({$this->newQuantity}).",
            'date' => $this->date,
        ];
    }
}
