<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Database\Factories\Central\TenantNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Internal or customer-facing note attached to a tenant.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $user_id
 * @property string $body
 * @property bool $is_internal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User $author
 *
 * @method static Builder<static> query()
 */
class TenantNote extends Model
{
    /** @use HasFactory<TenantNoteFactory> */
    use CentralConnection;

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'body',
        'is_internal',
    ];

    protected static function newFactory(): TenantNoteFactory
    {
        return TenantNoteFactory::new();
    }

    /**
     * Tenant this note belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Platform user who authored the note.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
