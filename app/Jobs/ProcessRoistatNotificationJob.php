<?php

namespace App\Jobs;

use App\Services\Handlers\RoistatNotificationHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRoistatNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60];

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(RoistatNotificationHandler $handler)
    {
        try {
            Log::info('Processing notification', [
                'event' => $this->data['notification_event'] ?? 'unknown'
            ]);

            $result = $handler->handle($this->data);

            if ($result) {
                Log::info('Notification processed successfully', [
                    'event' => $this->data['notification_event'] ?? 'unknown'
                ]);
            } else {
                Log::warning('Notification processing failed', [
                    'event' => $this->data['notification_event'] ?? 'unknown'
                ]);
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 30);
                }
            }
        } catch (\Exception $e) {
            Log::error('Notification job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $this->data,
            ]);
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 30);
            } else {
                throw $e;
            }
        }
    }
}