<?php

namespace App\Console\Commands;

use App\Jobs\RunScheduledAgentWork;
use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunScheduledAgents extends Command
{
    protected $signature = 'agents:run-scheduled';

    protected $description = 'Dispatch jobs for agents whose schedule is due';

    /**
     * Supported schedule presets mapped to minutes.
     *
     * @var array<string, int>
     */
    public const SCHEDULES = [
        'every_minute' => 1,
        'every_5_minutes' => 5,
        'every_15_minutes' => 15,
        'every_30_minutes' => 30,
        'hourly' => 60,
        'daily' => 1440,
    ];

    public function handle(): int
    {
        $agents = Agent::query()
            ->whereNotNull('schedule')
            ->where('schedule', '!=', '')
            ->whereNotNull('scheduled_instructions')
            ->get();

        $dispatched = 0;

        foreach ($agents as $agent) {
            if (! $this->isDue($agent)) {
                continue;
            }

            RunScheduledAgentWork::dispatch($agent);
            $dispatched++;
            $this->components->info("Dispatched: {$agent->name}");
        }

        $this->components->info("Done. Dispatched {$dispatched} agent(s).");

        return self::SUCCESS;
    }

    protected function isDue(Agent $agent): bool
    {
        $minutes = self::SCHEDULES[$agent->schedule] ?? null;

        if ($minutes === null) {
            return false;
        }

        if ($agent->last_scheduled_at === null) {
            return true;
        }

        return $agent->last_scheduled_at->addMinutes($minutes)->lte(Carbon::now());
    }
}
