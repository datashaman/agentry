<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'current_organization_id',
        'github_id',
        'github_token',
        'github_nickname',
        'jira_account_id',
        'jira_token',
        'jira_refresh_token',
        'jira_cloud_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'github_token',
        'jira_token',
        'jira_refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'github_token' => 'encrypted',
            'jira_token' => 'encrypted',
            'jira_refresh_token' => 'encrypted',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganizationRelation(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function currentOrganization(): ?Organization
    {
        if ($this->current_organization_id) {
            return $this->currentOrganizationRelation;
        }

        return $this->organizations()->first();
    }

    public function switchOrganization(Organization $organization): void
    {
        if ($this->organizations()->where('organizations.id', $organization->id)->exists()) {
            $this->update(['current_organization_id' => $organization->id]);
        }
    }

    public function createPersonalOrganization(): Organization
    {
        $name = $this->name."'s Organization";
        $slug = Str::slug($this->name);

        $baseSlug = $slug;
        $counter = 1;
        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        $organization = Organization::create([
            'name' => $name,
            'slug' => $slug,
        ]);

        $this->organizations()->attach($organization, ['role' => 'owner']);
        $this->update(['current_organization_id' => $organization->id]);

        return $organization;
    }

    public function hasGitHub(): bool
    {
        return $this->github_id !== null;
    }

    public function hasJira(): bool
    {
        return $this->jira_account_id !== null;
    }
}
