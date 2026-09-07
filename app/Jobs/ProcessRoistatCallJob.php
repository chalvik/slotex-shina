<?php

namespace App\Jobs;

use App\Services\Handlers\RoistatCallHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRoistatCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(RoistatCallHandler $handler)
    {
        try {
            Log::info('Processing call', ['call_id' => $this->data['id'] ?? 'unknown']);
            $result = $handler->handle($this->data);

            if ($result) {
                Log::info('Call processed successfully', ['call_id' => $this->data['id'] ?? 'unknown']);
            } else {
                Log::warning('Call processing failed', ['call_id' => $this->data['id'] ?? 'unknown']);
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                }
            }
        } catch (\Exception $e) {
            Log::error('Call job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $this->data,
            ]);
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
            } else {
                throw $e;
            }
        }
    }
}