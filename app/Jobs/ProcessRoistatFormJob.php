<?php

namespace App\Jobs;

use App\Services\Handlers\RoistatFormHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessRoistatFormJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 60, 120, 300];

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(RoistatFormHandler $handler)
    {
        try {
            Log::info('Processing form', ['visit_id' => $this->data['visit_id'] ?? 'unknown']);
            $result = $handler->handle($this->data);

            if ($result) {
                Log::info('Form processed successfully', ['visit_id' => $this->data['visit_id'] ?? 'unknown']);
            } else {
                Log::warning('Form processing failed', ['visit_id' => $this->data['visit_id'] ?? 'unknown']);
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                }
            }
        } catch (\Exception $e) {
            Log::error('Form job failed', [
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