<?php

namespace App\Events;

use App\Models\OpsRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OpsRequestTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OpsRequest $opsRequest,
        public string $from,
        public string $to,
    ) {}
}
