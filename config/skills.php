<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Context-Aware Skill Loading
    |--------------------------------------------------------------------------
    |
    | When true, skills with context_triggers are automatically loaded when
    | resolving an agent for a work item (Story, Bug, OpsRequest) whose
    | project/repo context matches the triggers. Set to false to disable.
    |
    */
    'context_aware_enabled' => env('SKILLS_CONTEXT_AWARE_ENABLED', true),
];
