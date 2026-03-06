<?php

namespace App\Events;

use App\Models\Story;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoryTransitioned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Story $story,
        public string $from,
        public string $to,
    ) {}
}
