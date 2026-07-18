<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Models\User;
use Database\Factories\Central\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Support ticket submitted by or on behalf of a tenant.
 *
 * @property int $id
 * @property string $number
 * @property string|null $tenant_id
 * @property int|null $ticket_category_id
 * @property string $subject
 * @property string $description
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property int|null $created_by
 * @property int|null $assigned_to
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant|null $tenant
 * @property-read TicketCategory|null $category
 * @property-read User|null $creator
 * @property-read User|null $assignee
 * @property-read Collection<int, TicketReply> $replies
 * @property-read Collection<int, TicketHistory> $histories
 *
 * @method static Builder<static> query()
 */
class Ticket extends Model implements HasMedia
{
    /** @use HasFactory<TicketFactory> */
    use CentralConnection, HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'tenant_id',
        'ticket_category_id',
        'subject',
        'description',
        'status',
        'priority',
        'created_by',
        'assigned_to',
        'resolved_at',
        'closed_at',
        'metadata',
    ];

    protected static function newFactory(): TicketFactory
    {
        return TicketFactory::new();
    }

    /**
     * Register media collections for ticket attachments.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    /**
     * Tenant associated with this ticket, if any.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Category assigned to this ticket.
     *
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    /**
     * User who opened the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User currently assigned to the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Replies posted on this ticket.
     *
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * Audit history for ticket changes.
     *
     * @return HasMany<TicketHistory, $this>
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
