<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\Tenant\UserStatus;
use Database\Factories\Tenant\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Tenant-scoped application user.
 *
 * Represents a user within an isolated tenant database, including
 * owner designation and invitation-based onboarding.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property bool $is_owner
 * @property string|null $invitation_token
 * @property Carbon|null $invitation_expires_at
 * @property UserStatus $status
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder<static> query()
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens;

    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_owner',
        'status',
        'invitation_token',
        'invitation_expires_at',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Determine whether the user may authenticate.
     */
    public function canAuthenticate(): bool
    {
        return ($this->status ?? UserStatus::Active)->canAuthenticate()
            && filled($this->password);
    }

    /**
     * Determine whether the user has a valid pending invitation.
     */
    public function hasPendingInvitation(): bool
    {
        return filled($this->invitation_token)
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
            'status' => UserStatus::class,
            'invitation_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
