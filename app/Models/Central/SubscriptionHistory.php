<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Audit log entry for subscription lifecycle events.
 *
 * @property int $id
 * @property int $subscription_id
 * @property string $event
 * @property SubscriptionStatus|null $from_status
 * @property SubscriptionStatus|null $to_status
 * @property int|null $from_plan_id
 * @property int|null $to_plan_id
 * @property int|null $user_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 * @property-read Plan|null $fromPlan
 * @property-read Plan|null $toPlan
 * @property-read User|null $user
 *
 * @method static Builder<static> query()
 */
class SubscriptionHistory extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'event',
        'from_status',
        'to_status',
        'from_plan_id',
        'to_plan_id',
        'user_id',
        'meta',
    ];

    /**
     * Subscription this history entry belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Plan before the change, if applicable.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    /**
     * Plan after the change, if applicable.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    /**
     * User who triggered the change, if known.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => SubscriptionStatus::class,
            'to_status' => SubscriptionStatus::class,
            'meta' => 'array',
        ];
    }
}
