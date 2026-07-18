<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\World;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\World\CityResource;
use App\Http\Resources\Central\World\CountryResource;
use App\Http\Resources\Central\World\CurrencyResource;
use App\Http\Resources\Central\World\LanguageResource;
use App\Http\Resources\Central\World\StateResource;
use App\Http\Resources\Central\World\TimezoneResource;
use App\Services\Central\World\WorldService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central World', description: 'Countries, states, cities, currencies, timezones, and languages.', weight: 18)]
final class WorldController extends Controller
{
    public function __construct(
        private readonly WorldService $world,
    ) {}

    #[Endpoint(operationId: 'world.countries.index', title: 'List countries', description: 'Return active countries with currency codes.')]
    public function countries(Request $request): JsonResponse
    {
        $countries = $this->world->countries($request->only(['search', 'status']));

        return $this->success(
            CountryResource::collection($countries),
            'Countries retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.countries.options', title: 'Country options', description: 'Return country ISO2 value/label pairs.')]
    public function countryOptions(Request $request): JsonResponse
    {
        return $this->success(
            $this->world->countryOptions($request->string('search')->toString() ?: null),
            'Country options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.countries.show', title: 'Show country', description: 'Return a country by ISO2 code.')]
    public function showCountry(string $iso2): JsonResponse
    {
        $country = $this->world->findCountryByIso2($iso2);

        if ($country === null) {
            return $this->error('Country not found.', 404);
        }

        return $this->success(
            new CountryResource($country),
            'Country retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.countries.states', title: 'List states', description: 'Return states for a country ISO2 code.')]
    public function states(string $iso2): JsonResponse
    {
        if ($this->world->findCountryByIso2($iso2) === null) {
            return $this->error('Country not found.', 404);
        }

        return $this->success(
            StateResource::collection($this->world->statesForCountry($iso2)),
            'States retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.states.cities', title: 'List cities', description: 'Return cities for a state ID.')]
    public function cities(int $state): JsonResponse
    {
        return $this->success(
            CityResource::collection($this->world->citiesForState($state)),
            'Cities retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.currencies.index', title: 'List currencies', description: 'Return world currencies.')]
    public function currencies(Request $request): JsonResponse
    {
        return $this->success(
            CurrencyResource::collection($this->world->currencies($request->only(['search']))),
            'Currencies retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.currencies.options', title: 'Currency options', description: 'Return currency code value/label pairs.')]
    public function currencyOptions(Request $request): JsonResponse
    {
        return $this->success(
            $this->world->currencyOptions($request->string('search')->toString() ?: null),
            'Currency options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.timezones.index', title: 'List timezones', description: 'Return world timezones.')]
    public function timezones(Request $request): JsonResponse
    {
        return $this->success(
            TimezoneResource::collection($this->world->timezones($request->only(['search', 'country_id']))),
            'Timezones retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.languages.index', title: 'List languages', description: 'Return world languages.')]
    public function languages(Request $request): JsonResponse
    {
        return $this->success(
            LanguageResource::collection($this->world->languages($request->only(['search']))),
            'Languages retrieved successfully.',
        );
    }
}
