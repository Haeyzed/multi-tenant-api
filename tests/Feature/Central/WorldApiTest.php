<?php

declare(strict_types=1);

use App\Models\World\Country;
use App\Models\World\Currency;
use App\Models\World\State;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('countries')) {
        $this->markTestSkipped('World countries table is not available.');
    }

    $country = Country::query()->updateOrCreate(
        ['iso2' => 'NG'],
        [
            'name' => 'Nigeria',
            'status' => 1,
            'phone_code' => '234',
            'iso3' => 'NGA',
            'native' => 'Nigeria',
            'region' => 'Africa',
            'subregion' => 'Western Africa',
            'latitude' => '9.0820',
            'longitude' => '8.6753',
            'emoji' => '🇳🇬',
            'emojiU' => 'U+1F1F3 U+1F1EC',
        ],
    );

    Currency::query()->updateOrCreate(
        ['country_id' => $country->id],
        [
            'name' => 'Nigerian Naira',
            'code' => 'NGN',
            'precision' => 2,
            'symbol' => '₦',
            'symbol_native' => '₦',
            'symbol_first' => true,
            'decimal_mark' => '.',
            'thousands_separator' => ',',
        ],
    );
});

it('lists countries with currency codes', function (): void {
    $this->getJson('/api/v1/world/countries')
        ->assertSuccessful()
        ->assertJsonPath('status', true)
        ->assertJsonFragment(['iso2' => 'NG', 'name' => 'Nigeria']);
});

it('returns country options as value/label pairs', function (): void {
    $this->getJson('/api/v1/world/countries/options')
        ->assertSuccessful()
        ->assertJsonFragment(['value' => 'NG', 'label' => 'Nigeria']);
});

it('returns currency options as value/label pairs', function (): void {
    $this->getJson('/api/v1/world/currencies/options')
        ->assertSuccessful()
        ->assertJsonFragment(['value' => 'NGN'])
        ->assertJsonPath('data.0.value', 'NGN');
});

it('returns complete admin country and state option lists', function (): void {
    actingAsCentralUser(['world.view']);

    $country = Country::query()->where('iso2', 'NG')->firstOrFail();
    State::query()->updateOrCreate(
        ['country_id' => $country->id, 'name' => 'Lagos'],
        ['country_code' => 'NG', 'state_code' => 'LA', 'type' => 'state'],
    );

    $countryResponse = $this->getJson('/api/v1/world/admin/countries/options')
        ->assertSuccessful()
        ->assertJsonFragment([
            'value' => (string) $country->id,
            'label' => 'Nigeria',
        ]);

    expect($countryResponse->json('data'))->toHaveCount(
        Country::query()->where('status', 1)->count(),
    );

    $stateResponse = $this->getJson('/api/v1/world/admin/states/options?country_id='.$country->id)
        ->assertSuccessful()
        ->assertJsonFragment(['label' => 'Lagos']);

    expect($stateResponse->json('data'))->toHaveCount(
        State::query()->where('country_id', $country->id)->count(),
    );
    expect($stateResponse->json('data.0'))->toHaveKeys(['value', 'label']);
});

it('shows a country by iso2', function (): void {
    $this->getJson('/api/v1/world/countries/NG')
        ->assertSuccessful()
        ->assertJsonPath('data.iso2', 'NG')
        ->assertJsonPath('data.currency.code', 'NGN');
});

it('paginates and manages world countries for authenticated admins', function (): void {
    actingAsCentralUser(['world.view', 'world.create', 'world.update', 'world.delete']);

    $this->getJson('/api/v1/world/statistics')
        ->assertSuccessful()
        ->assertJsonStructure(['data' => [
            'countries',
            'states',
            'cities',
            'currencies',
            'timezones',
            'languages',
        ]]);

    $this->getJson('/api/v1/world/admin/countries?search=Nigeria&per_page=15')
        ->assertSuccessful()
        ->assertJsonFragment(['iso2' => 'NG', 'name' => 'Nigeria']);

    $created = $this->postJson('/api/v1/world/admin/countries', [
        'name' => 'Testland',
        'iso2' => 'TL',
        'iso3' => 'TLD',
        'status' => 1,
        'phone_code' => '999',
        'native' => 'Testland',
        'region' => 'Test',
        'subregion' => 'Test Sub',
    ])->assertCreated();

    $id = $created->json('data.id');

    $this->putJson("/api/v1/world/admin/countries/{$id}", [
        'name' => 'Testland Updated',
        'status' => 0,
    ])->assertSuccessful()
        ->assertJsonPath('data.name', 'Testland Updated');

    $this->deleteJson("/api/v1/world/admin/countries/{$id}")
        ->assertSuccessful();
});

it('filters paginated states cities currencies timezones and languages', function (): void {
    actingAsCentralUser(['world.view', 'world.create']);

    $country = Country::query()->where('iso2', 'NG')->firstOrFail();

    $this->getJson('/api/v1/world/admin/states?country_id='.$country->id.'&search=&per_page=10')
        ->assertSuccessful();

    $this->getJson('/api/v1/world/admin/cities?country_id='.$country->id.'&per_page=10')
        ->assertSuccessful();

    $this->getJson('/api/v1/world/admin/currencies?search=NGN&per_page=10')
        ->assertSuccessful()
        ->assertJsonFragment(['code' => 'NGN']);

    $this->getJson('/api/v1/world/admin/timezones?country_id='.$country->id.'&per_page=10')
        ->assertSuccessful();

    $this->getJson('/api/v1/world/admin/languages?search=en&per_page=10')
        ->assertSuccessful();
});
