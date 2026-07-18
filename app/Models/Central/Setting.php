<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Enums\Central\SettingGroup;
use App\Enums\Central\SettingType;
use Database\Factories\Central\SettingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Throwable;

/**
 * Configurable platform setting stored in the central database.
 *
 * Supports typed values, encryption, and public exposure flags.
 *
 * @property int $id
 * @property SettingGroup $group
 * @property string $key
 * @property string|null $label
 * @property string|null $description
 * @property SettingType $type
 * @property string|null $value
 * @property array<string, mixed>|null $default_value
 * @property array<string, mixed>|null $options
 * @property bool $is_public
 * @property bool $is_encrypted
 * @property bool $is_readonly
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use CentralConnection, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group',
        'key',
        'label',
        'description',
        'type',
        'value',
        'default_value',
        'options',
        'is_public',
        'is_encrypted',
        'is_readonly',
        'sort_order',
    ];

    protected static function newFactory(): SettingFactory
    {
        return SettingFactory::new();
    }

    /**
     * Resolve the effective setting value with decryption and type casting.
     */
    public function resolvedValue(): mixed
    {
        $raw = $this->value;

        if ($raw === null) {
            return $this->default_value['value'] ?? null;
        }

        if ($this->is_encrypted) {
            try {
                $raw = decrypt($raw);
            } catch (Throwable) {
                return null;
            }
        }

        return $this->type->cast($raw);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => SettingGroup::class,
            'type' => SettingType::class,
            'default_value' => 'array',
            'options' => 'array',
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'is_readonly' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
