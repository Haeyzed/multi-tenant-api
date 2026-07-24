<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\World\StateOptionsRequest;
use App\Http\Requests\Central\World\StoreCityRequest;
use App\Http\Requests\Central\World\StoreCountryRequest;
use App\Http\Requests\Central\World\StoreCurrencyRequest;
use App\Http\Requests\Central\World\StoreLanguageRequest;
use App\Http\Requests\Central\World\StoreStateRequest;
use App\Http\Requests\Central\World\StoreTimezoneRequest;
use App\Http\Requests\Central\World\UpdateCityRequest;
use App\Http\Requests\Central\World\UpdateCountryRequest;
use App\Http\Requests\Central\World\UpdateCurrencyRequest;
use App\Http\Requests\Central\World\UpdateLanguageRequest;
use App\Http\Requests\Central\World\UpdateStateRequest;
use App\Http\Requests\Central\World\UpdateTimezoneRequest;
use App\Http\Resources\Central\World\CityResource;
use App\Http\Resources\Central\World\CountryResource;
use App\Http\Resources\Central\World\CurrencyResource;
use App\Http\Resources\Central\World\LanguageResource;
use App\Http\Resources\Central\World\StateResource;
use App\Http\Resources\Central\World\TimezoneResource;
use App\Models\World\City;
use App\Models\World\Country;
use App\Models\World\Currency;
use App\Models\World\Language;
use App\Models\World\State;
use App\Models\World\Timezone;
use App\Services\Central\World\WorldService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central admin endpoints for world geography and locale reference data.
 */
#[Group('Central World Admin', description: 'Manage world geography and locale reference data.', weight: 19)]
final class WorldAdminController extends Controller
{
    public function __construct(
        private readonly WorldService $world,
    ) {}

    #[Endpoint(operationId: 'world.admin.statistics', title: 'World statistics', description: 'Return world reference data counts.')]
    public function statistics(): JsonResponse
    {
        $this->authorize('viewWorld');

        return $this->success(
            $this->world->overviewStatistics(),
            'World statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.index', title: 'Paginate countries', description: 'Paginate countries for admin management.')]
    public function countries(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $countries = $this->world->paginateCountries($request->only([
            'search', 'status', 'region', 'per_page',
        ]));

        return $this->paginated(
            CountryResource::collection($countries),
            'Countries retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.options', title: 'Country options', description: 'Return all active country ID value/label pairs.')]
    public function countryOptions(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        return $this->success(
            $this->world->countryIdOptions($request->string('search')->toString() ?: null),
            'Country options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.store', title: 'Create country')]
    public function storeCountry(StoreCountryRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new CountryResource($this->world->createCountry($request->validated())),
            'Country created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.show', title: 'Show country by ID')]
    public function showCountry(Country $country): JsonResponse
    {
        $this->authorize('viewWorld');

        $country->loadMissing('currency');

        return $this->success(new CountryResource($country), 'Country retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.countries.update', title: 'Update country')]
    public function updateCountry(UpdateCountryRequest $request, Country $country): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new CountryResource($this->world->updateCountry($country, $request->validated())),
            'Country updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.destroy', title: 'Delete country')]
    public function destroyCountry(Country $country): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteCountry($country);

        return $this->success(null, 'Country deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.states.index', title: 'Paginate states')]
    public function states(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $states = $this->world->paginateStates($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            StateResource::collection($states),
            'States retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.states.options', title: 'State options', description: 'Return all state ID value/label pairs for a country.')]
    public function stateOptions(StateOptionsRequest $request): JsonResponse
    {
        $data = $request->validated();

        return $this->success(
            $this->world->stateIdOptions(
                (int) $data['country_id'],
                filled($data['search'] ?? null) ? (string) $data['search'] : null,
            ),
            'State options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.admin.states.store', title: 'Create state')]
    public function storeState(StoreStateRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new StateResource($this->world->createState($request->validated())),
            'State created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.states.show', title: 'Show state')]
    public function showState(State $state): JsonResponse
    {
        $this->authorize('viewWorld');

        $state->loadMissing('country');

        return $this->success(new StateResource($state), 'State retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.states.update', title: 'Update state')]
    public function updateState(UpdateStateRequest $request, State $state): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new StateResource($this->world->updateState($state, $request->validated())),
            'State updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.states.destroy', title: 'Delete state')]
    public function destroyState(State $state): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteState($state);

        return $this->success(null, 'State deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.cities.index', title: 'Paginate cities')]
    public function cities(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $cities = $this->world->paginateCities($request->only([
            'search', 'country_id', 'state_id', 'per_page',
        ]));

        return $this->paginated(
            CityResource::collection($cities),
            'Cities retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.store', title: 'Create city')]
    public function storeCity(StoreCityRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new CityResource($this->world->createCity($request->validated())),
            'City created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.show', title: 'Show city')]
    public function showCity(City $city): JsonResponse
    {
        $this->authorize('viewWorld');

        $city->loadMissing(['country', 'state']);

        return $this->success(new CityResource($city), 'City retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.cities.update', title: 'Update city')]
    public function updateCity(UpdateCityRequest $request, City $city): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new CityResource($this->world->updateCity($city, $request->validated())),
            'City updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.destroy', title: 'Delete city')]
    public function destroyCity(City $city): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteCity($city);

        return $this->success(null, 'City deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.currencies.index', title: 'Paginate currencies')]
    public function currencies(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $currencies = $this->world->paginateCurrencies($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            CurrencyResource::collection($currencies),
            'Currencies retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.store', title: 'Create currency')]
    public function storeCurrency(StoreCurrencyRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new CurrencyResource($this->world->createCurrency($request->validated())),
            'Currency created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.show', title: 'Show currency')]
    public function showCurrency(Currency $currency): JsonResponse
    {
        $this->authorize('viewWorld');

        $currency->loadMissing('country');

        return $this->success(new CurrencyResource($currency), 'Currency retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.currencies.update', title: 'Update currency')]
    public function updateCurrency(UpdateCurrencyRequest $request, Currency $currency): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new CurrencyResource($this->world->updateCurrency($currency, $request->validated())),
            'Currency updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.destroy', title: 'Delete currency')]
    public function destroyCurrency(Currency $currency): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteCurrency($currency);

        return $this->success(null, 'Currency deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.timezones.index', title: 'Paginate timezones')]
    public function timezones(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $timezones = $this->world->paginateTimezones($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            TimezoneResource::collection($timezones),
            'Timezones retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.store', title: 'Create timezone')]
    public function storeTimezone(StoreTimezoneRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new TimezoneResource($this->world->createTimezone($request->validated())),
            'Timezone created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.show', title: 'Show timezone')]
    public function showTimezone(Timezone $timezone): JsonResponse
    {
        $this->authorize('viewWorld');

        $timezone->loadMissing('country');

        return $this->success(new TimezoneResource($timezone), 'Timezone retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.timezones.update', title: 'Update timezone')]
    public function updateTimezone(UpdateTimezoneRequest $request, Timezone $timezone): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new TimezoneResource($this->world->updateTimezone($timezone, $request->validated())),
            'Timezone updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.destroy', title: 'Delete timezone')]
    public function destroyTimezone(Timezone $timezone): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteTimezone($timezone);

        return $this->success(null, 'Timezone deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.languages.index', title: 'Paginate languages')]
    public function languages(Request $request): JsonResponse
    {
        $this->authorize('viewWorld');

        $languages = $this->world->paginateLanguages($request->only([
            'search', 'dir', 'per_page',
        ]));

        return $this->paginated(
            LanguageResource::collection($languages),
            'Languages retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.store', title: 'Create language')]
    public function storeLanguage(StoreLanguageRequest $request): JsonResponse
    {
        $this->authorize('createWorld');

        return $this->success(
            new LanguageResource($this->world->createLanguage($request->validated())),
            'Language created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.show', title: 'Show language')]
    public function showLanguage(Language $language): JsonResponse
    {
        $this->authorize('viewWorld');

        return $this->success(new LanguageResource($language), 'Language retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.languages.update', title: 'Update language')]
    public function updateLanguage(UpdateLanguageRequest $request, Language $language): JsonResponse
    {
        $this->authorize('updateWorld');

        return $this->success(
            new LanguageResource($this->world->updateLanguage($language, $request->validated())),
            'Language updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.destroy', title: 'Delete language')]
    public function destroyLanguage(Language $language): JsonResponse
    {
        $this->authorize('deleteWorld');

        $this->world->deleteLanguage($language);

        return $this->success(null, 'Language deleted successfully.');
    }
}
