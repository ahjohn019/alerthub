<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotification implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(
        public readonly int $notificationId,
    ) {}

    public function handle(): void
    {
        $notification = Notification::withoutGlobalScopes()->findOrFail($this->notificationId);

        // Delivery providers are integrated later; for now this records successful async dispatch.
        $notification->update([
            'status' => $notification->status === 'escalated' ? 'escalated' : 'sent',
            'sent_at' => now(),
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    public function failed(Throwable $exception): void
    {
        Notification::withoutGlobalScopes()
            ->whereKey($this->notificationId)
            ->update(['status' => 'failed']);

        Log::error('Notification delivery job failed.', [
            'notification_id' => $this->notificationId,
            'message' => $exception->getMessage(),
        ]);
    }
}
