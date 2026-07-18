<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Audit log entry for announcement lifecycle actions.
 *
 * @property int $id
 * @property int $announcement_id
 * @property int|null $user_id
 * @property string $action
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Announcement $announcement
 * @property-read User|null $user
 *
 * @method static Builder<static> query()
 */
class AnnouncementHistory extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'announcement_id',
        'user_id',
        'action',
        'properties',
    ];

    /**
     * Announcement this history entry belongs to.
     *
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * User who performed the action, if known.
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
            'properties' => 'array',
        ];
    }
}
