<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    /**
     * All available agent permissions grouped by category.
     *
     * @var array<string, list<string>>
     */
    public const AGENT_PERMISSIONS = [
        'Branches' => [
            'create_branches',
            'delete_branches',
        ],
        'Pull Requests' => [
            'create_pull_requests',
            'update_pull_requests',
            'merge_pull_requests',
            'close_pull_requests',
            'comment_on_pull_requests',
        ],
        'Code' => [
            'push_code',
        ],
        'Epics' => [
            'create_epics',
            'update_epics',
            'delete_epics',
        ],
        'Stories' => [
            'create_stories',
            'update_stories',
            'delete_stories',
        ],
        'Bugs' => [
            'create_bugs',
            'update_bugs',
            'close_bugs',
        ],
        'Ops Requests' => [
            'create_ops_requests',
            'update_ops_requests',
            'close_ops_requests',
            'execute_runbooks',
        ],
        'Milestones' => [
            'create_milestones',
            'update_milestones',
            'delete_milestones',
        ],
        'Labels' => [
            'create_labels',
            'update_labels',
            'delete_labels',
        ],
        'Deployments' => [
            'trigger_deployments',
        ],
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'provider',
        'provider_id',
        'github_installation_id',
        'github_account_login',
        'github_account_type',
        'agent_permissions',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'agent_permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Organization $organization) {
            $codingRole = $organization->agentRoles()->create([
                'name' => 'Coding',
                'slug' => 'coding',
                'description' => 'Implements code changes for stories and bugs',
                'instructions' => 'You implement code changes for stories and bugs. Create branches, write code following project conventions, open pull requests, and address review feedback.',
            ]);

            $reviewRole = $organization->agentRoles()->create([
                'name' => 'Review',
                'slug' => 'review',
                'description' => 'Reviews pull requests for code quality',
                'instructions' => 'You review pull requests for code quality, correctness, and adherence to conventions. Approve or request changes with clear, constructive feedback.',
            ]);

            $team = $organization->teams()->create([
                'name' => 'Development',
                'slug' => 'development',
                'workflow_type' => 'evaluator_optimizer',
            ]);

            $coder = $team->agents()->create([
                'agent_role_id' => $codingRole->id,
                'name' => 'Coder',
                'model' => 'claude-sonnet-4-6',
                'provider' => 'anthropic',
                'confidence_threshold' => 0.8,
                'status' => 'idle',
            ]);

            $reviewer = $team->agents()->create([
                'agent_role_id' => $reviewRole->id,
                'name' => 'Reviewer',
                'model' => 'claude-sonnet-4-6',
                'provider' => 'anthropic',
                'confidence_threshold' => 0.8,
                'status' => 'idle',
            ]);

            $team->update(['workflow_config' => [
                'generator_agent_id' => $coder->id,
                'evaluator_agent_id' => $reviewer->id,
                'max_refinements' => 3,
                'min_rating' => 'good',
            ]]);
        });
    }

    public function agentCan(string $permission): bool
    {
        return (bool) ($this->agent_permissions[$permission] ?? false);
    }

    /**
     * @return list<string>
     */
    public static function allAgentPermissionKeys(): array
    {
        return array_merge(...array_values(self::AGENT_PERMISSIONS));
    }

    public function hasGitHubApp(): bool
    {
        return $this->github_installation_id !== null;
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<AgentRole, $this>
     */
    public function agentRoles(): HasMany
    {
        return $this->hasMany(AgentRole::class);
    }

    /**
     * @return HasMany<Skill, $this>
     */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }
}
