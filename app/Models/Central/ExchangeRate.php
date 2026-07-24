<?php

declare(strict_types=1);

namespace App\Models\Central;

use App\Models\World\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Historical FX quote used for reporting only (never subscription pricing).
 *
 * @property int $id
 * @property string $base_currency
 * @property string $quote_currency
 * @property string $rate
 * @property string|null $source
 * @property Carbon $observed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Currency|null $baseCurrency
 * @property-read Currency|null $quoteCurrency
 *
 * @method static Builder<static> query()
 */
class ExchangeRate extends Model
{
    use CentralConnection;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'source',
        'observed_at',
    ];

    /**
     * Currency being converted from.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency', 'code');
    }

    /**
     * Currency being converted to.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency', 'code');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'observed_at' => 'datetime',
        ];
    }
}
