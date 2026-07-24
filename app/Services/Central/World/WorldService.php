<?php

declare(strict_types=1);

namespace App\Services\Central\World;

use App\Models\World\City;
use App\Models\World\Country;
use App\Models\World\Currency;
use App\Models\World\Language;
use App\Models\World\State;
use App\Models\World\Timezone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Geography and locale data for public lookups and central admin management.
 */
final class WorldService
{
    /**
     * Paginate countries for central admin listing.
     *
     * @param  array{search?: string, status?: int|string|null, region?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Country>
     */
    public function paginateCountries(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->countryQuery($filters, defaultActiveOnly: false)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Build a filtered country query, optionally defaulting to active rows only.
     *
     * @param  array{search?: string, status?: int|string|null, region?: string}  $filters
     * @return Builder<Country>
     */
    private function countryQuery(array $filters, bool $defaultActiveOnly = true): Builder
    {
        return Country::query()
            ->with('currency')
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('iso2', 'like', "%{$search}%")
                        ->orWhere('iso3', 'like', "%{$search}%")
                        ->orWhere('native', 'like', "%{$search}%")
                        ->orWhere('region', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['region'] ?? null,
                fn ($query, string $region) => $query->where('region', $region)
            )
            ->when(
                array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '',
                fn ($query) => $query->where('status', (int) $filters['status']),
                fn ($query) => $defaultActiveOnly ? $query->where('status', 1) : $query,
            );
    }

    /**
     * Find a country by primary key with its currency relation.
     */
    public function findCountry(int $id): Country
    {
        return Country::query()->with('currency')->findOrFail($id);
    }

    /**
     * Resolve the ISO currency code for a country (ISO2).
     */
    public function currencyForCountry(string $iso2): ?string
    {
        $country = $this->findCountryByIso2($iso2);

        $code = $country?->currency?->code;

        return filled($code) ? Str::upper((string) $code) : null;
    }

    /**
     * Find a country by ISO 3166-1 alpha-2 code with its currency relation.
     */
    public function findCountryByIso2(string $iso2): ?Country
    {
        return Country::query()
            ->with('currency')
            ->where('iso2', Str::upper($iso2))
            ->first();
    }

    /**
     * Create a country after enforcing unique ISO2.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCountry(array $data): Country
    {
        $iso2 = Str::upper((string) $data['iso2']);

        if (Country::query()->where('iso2', $iso2)->exists()) {
            throw ValidationException::withMessages([
                'iso2' => ['A country with this ISO2 code already exists.'],
            ]);
        }

        $country = Country::query()->create([
            'iso2' => $iso2,
            'iso3' => Str::upper((string) ($data['iso3'] ?? '')),
            'name' => $data['name'],
            'status' => (int) ($data['status'] ?? 1),
            'phone_code' => (string) ($data['phone_code'] ?? ''),
            'native' => (string) ($data['native'] ?? $data['name']),
            'region' => (string) ($data['region'] ?? ''),
            'subregion' => (string) ($data['subregion'] ?? ''),
            'latitude' => (string) ($data['latitude'] ?? '0'),
            'longitude' => (string) ($data['longitude'] ?? '0'),
            'emoji' => (string) ($data['emoji'] ?? ''),
            'emojiU' => (string) ($data['emojiU'] ?? ''),
        ]);

        return $country->load('currency');
    }

    /**
     * Update a country, normalizing and uniqueness-checking ISO codes when present.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCountry(Country $country, array $data): Country
    {
        if (isset($data['iso2'])) {
            $iso2 = Str::upper((string) $data['iso2']);
            $exists = Country::query()
                ->where('iso2', $iso2)
                ->whereKeyNot($country->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'iso2' => ['A country with this ISO2 code already exists.'],
                ]);
            }

            $data['iso2'] = $iso2;
        }

        if (isset($data['iso3'])) {
            $data['iso3'] = Str::upper((string) $data['iso3']);
        }

        $country->update($data);

        return $country->fresh(['currency']);
    }

    /**
     * Soft-delete or remove a country record.
     */
    public function deleteCountry(Country $country): void
    {
        $country->delete();
    }

    /**
     * List states belonging to a country identified by ISO2.
     *
     * @return Collection<int, State>
     */
    public function statesForCountry(string $iso2): Collection
    {
        $country = $this->findCountryByIso2($iso2);

        if ($country === null) {
            return new Collection;
        }

        return State::query()
            ->where('country_id', $country->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Paginate states for central admin listing.
     *
     * @param  array{search?: string, country_id?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, State>
     */
    public function paginateStates(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return State::query()
            ->with('country')
            ->when($filters['country_id'] ?? null, fn ($q, $id) => $q->where('country_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('state_code', 'like', "%{$search}%")
                        ->orWhere('country_code', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a state and load its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createState(array $data): State
    {
        return State::query()->create($data)->load('country');
    }

    /**
     * Update a state and refresh its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateState(State $state, array $data): State
    {
        $state->update($data);

        return $state->fresh(['country']);
    }

    /**
     * Soft-delete or remove a state record.
     */
    public function deleteState(State $state): void
    {
        $state->delete();
    }

    /**
     * List cities belonging to a state.
     *
     * @return Collection<int, City>
     */
    public function citiesForState(int $stateId): Collection
    {
        return City::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Paginate cities for central admin listing.
     *
     * @param  array{search?: string, country_id?: int, state_id?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, City>
     */
    public function paginateCities(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return City::query()
            ->with(['country', 'state'])
            ->when($filters['country_id'] ?? null, fn ($q, $id) => $q->where('country_id', $id))
            ->when($filters['state_id'] ?? null, fn ($q, $id) => $q->where('state_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('country_code', 'like', "%{$search}%")
                        ->orWhere('state_code', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a city and load its country and state relations.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCity(array $data): City
    {
        return City::query()->create($data)->load(['country', 'state']);
    }

    /**
     * Update a city and refresh its country and state relations.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCity(City $city, array $data): City
    {
        $city->update($data);

        return $city->fresh(['country', 'state']);
    }

    /**
     * Soft-delete or remove a city record.
     */
    public function deleteCity(City $city): void
    {
        $city->delete();
    }

    /**
     * Paginate currencies for central admin listing.
     *
     * @param  array{search?: string, country_id?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Currency>
     */
    public function paginateCurrencies(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->currencyQuery($filters)
            ->with('country')
            ->orderBy('code')
            ->paginate($perPage);
    }

    /**
     * Build a filtered currency query.
     *
     * @param  array{search?: string, country_id?: int}  $filters
     * @return Builder<Currency>
     */
    private function currencyQuery(array $filters): Builder
    {
        return Currency::query()
            ->when($filters['country_id'] ?? null, fn ($q, $id) => $q->where('country_id', $id))
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                })
            );
    }

    /**
     * Create a currency and load its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCurrency(array $data): Currency
    {
        return Currency::query()->create($data)->load('country');
    }

    /**
     * Update a currency and refresh its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCurrency(Currency $currency, array $data): Currency
    {
        $currency->update($data);

        return $currency->fresh(['country']);
    }

    /**
     * Soft-delete or remove a currency record.
     */
    public function deleteCurrency(Currency $currency): void
    {
        $currency->delete();
    }

    /**
     * List timezones matching optional search and country filters.
     *
     * @param  array{search?: string, country_id?: int}  $filters
     * @return Collection<int, Timezone>
     */
    public function timezones(array $filters = []): Collection
    {
        return $this->timezoneQuery($filters)->orderBy('name')->get();
    }

    /**
     * Build a filtered timezone query.
     *
     * @param  array{search?: string, country_id?: int}  $filters
     * @return Builder<Timezone>
     */
    private function timezoneQuery(array $filters): Builder
    {
        return Timezone::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where('name', 'like', "%{$search}%")
            )
            ->when(
                $filters['country_id'] ?? null,
                fn ($query, int $countryId) => $query->where('country_id', $countryId)
            );
    }

    /**
     * Paginate timezones for central admin listing.
     *
     * @param  array{search?: string, country_id?: int, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Timezone>
     */
    public function paginateTimezones(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->timezoneQuery($filters)
            ->with('country')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a timezone and load its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function createTimezone(array $data): Timezone
    {
        return Timezone::query()->create($data)->load('country');
    }

    /**
     * Update a timezone and refresh its country relation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTimezone(Timezone $timezone, array $data): Timezone
    {
        $timezone->update($data);

        return $timezone->fresh(['country']);
    }

    /**
     * Soft-delete or remove a timezone record.
     */
    public function deleteTimezone(Timezone $timezone): void
    {
        $timezone->delete();
    }

    /**
     * List languages matching optional search filters.
     *
     * @param  array{search?: string}  $filters
     * @return Collection<int, Language>
     */
    public function languages(array $filters = []): Collection
    {
        return $this->languageQuery($filters)->orderBy('name')->get();
    }

    /**
     * Build a filtered language query.
     *
     * @param  array{search?: string, dir?: string}  $filters
     * @return Builder<Language>
     */
    private function languageQuery(array $filters): Builder
    {
        return Language::query()
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('name_native', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['dir'] ?? null,
                fn ($query, string $dir) => $query->where('dir', $dir)
            );
    }

    /**
     * Paginate languages for central admin listing.
     *
     * @param  array{search?: string, dir?: string, per_page?: int}  $filters
     * @return LengthAwarePaginator<int, Language>
     */
    public function paginateLanguages(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $this->languageQuery($filters)
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a language record.
     *
     * @param  array<string, mixed>  $data
     */
    public function createLanguage(array $data): Language
    {
        return Language::query()->create($data);
    }

    /**
     * Update a language and return a fresh instance.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateLanguage(Language $language, array $data): Language
    {
        $language->update($data);

        return $language->fresh();
    }

    /**
     * Soft-delete or remove a language record.
     */
    public function deleteLanguage(Language $language): void
    {
        $language->delete();
    }

    /**
     * Active country ISO2 codes as value/label pairs for comboboxes.
     *
     * @return list<array{value: string, label: string}>
     */
    public function countryOptions(?string $search = null): array
    {
        return $this->countries(array_filter(['search' => $search]))
            ->map(fn (Country $country): array => [
                'value' => (string) $country->iso2,
                'label' => (string) $country->name,
            ])
            ->values()
            ->all();
    }

    /**
     * List countries matching optional search, status, and region filters.
     *
     * @param  array{search?: string, status?: int}  $filters
     * @return Collection<int, Country>
     */
    public function countries(array $filters = []): Collection
    {
        return $this->countryQuery($filters)->orderBy('name')->get();
    }

    /**
     * Active country database IDs as value/label pairs for admin comboboxes.
     *
     * @return list<array{value: string, label: string}>
     */
    public function countryIdOptions(?string $search = null): array
    {
        return $this->countries(array_filter(['search' => $search]))
            ->map(fn (Country $country): array => [
                'value' => (string) $country->getKey(),
                'label' => (string) $country->name,
            ])
            ->values()
            ->all();
    }

    /**
     * All states for a country as value/label pairs for admin comboboxes.
     *
     * @return list<array{value: string, label: string}>
     */
    public function stateIdOptions(int $countryId, ?string $search = null): array
    {
        return State::query()
            ->where('country_id', $countryId)
            ->when(
                filled($search),
                fn ($query) => $query->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('state_code', 'like', "%{$search}%");
                }),
            )
            ->orderBy('name')
            ->get()
            ->map(fn (State $state): array => [
                'value' => (string) $state->getKey(),
                'label' => (string) $state->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Distinct currency codes as value/label pairs for comboboxes.
     *
     * @return list<array{value: string, label: string}>
     */
    public function currencyOptions(?string $search = null): array
    {
        return $this->currencies(array_filter(['search' => $search]))
            ->unique(fn (Currency $currency): string => Str::upper((string) $currency->code))
            ->sortBy(fn (Currency $currency): string => Str::upper((string) $currency->code))
            ->map(function (Currency $currency): array {
                $code = Str::upper((string) $currency->code);
                $name = (string) $currency->name;
                $symbol = filled($currency->symbol) ? (string) $currency->symbol : null;

                return [
                    'value' => $code,
                    'label' => $symbol !== null
                        ? "{$code} ({$symbol}) — {$name}"
                        : "{$code} — {$name}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * List currencies matching optional search filters.
     *
     * @param  array{search?: string}  $filters
     * @return Collection<int, Currency>
     */
    public function currencies(array $filters = []): Collection
    {
        return $this->currencyQuery($filters)->orderBy('code')->get();
    }

    /**
     * Aggregate counts for the world admin overview dashboard.
     *
     * @return array{
     *     countries: int,
     *     states: int,
     *     cities: int,
     *     currencies: int,
     *     timezones: int,
     *     languages: int,
     *     active_countries: int,
     *     inactive_countries: int
     * }
     */
    public function overviewStatistics(): array
    {
        $activeCountries = Country::query()->where('status', 1)->count();
        $totalCountries = Country::query()->count();

        return [
            'countries' => $totalCountries,
            'states' => State::query()->count(),
            'cities' => City::query()->count(),
            'currencies' => Currency::query()->count(),
            'timezones' => Timezone::query()->count(),
            'languages' => Language::query()->count(),
            'active_countries' => $activeCountries,
            'inactive_countries' => max(0, $totalCountries - $activeCountries),
        ];
    }
}
