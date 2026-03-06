<?php

namespace App\Events;

use App\Models\Bug;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BugTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Bug $bug,
        public string $from,
        public string $to,
    ) {}
}
