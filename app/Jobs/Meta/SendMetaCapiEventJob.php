<?php

declare(strict_types=1);

namespace App\Jobs\Meta;

use App\Services\Meta\MetaCapiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SendMetaCapiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 20;

    public function __construct(public readonly array $eventPayload) {}

    public function handle(MetaCapiService $service): void
    {
        $service->send($this->eventPayload);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('single')->error('[Meta CAPI Job failed]', [
            'message' => $e->getMessage(),
            'event_name' => $this->eventPayload['event_name'] ?? 'unknown',
        ]);
    }
}
