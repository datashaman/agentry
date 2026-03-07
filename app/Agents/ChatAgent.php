<?php

namespace App\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class ChatAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function __construct(public string $instructions = 'You are a helpful assistant.') {}

    public function instructions(): Stringable|string
    {
        return $this->instructions;
    }
}
