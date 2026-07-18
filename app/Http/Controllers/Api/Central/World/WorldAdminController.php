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

#[Group('Central World Admin', description: 'Manage world geography and locale reference data.', weight: 19)]
final class WorldAdminController extends Controller
{
    public function __construct(
        private readonly WorldService $world,
    ) {}

    #[Endpoint(operationId: 'world.admin.statistics', title: 'World statistics', description: 'Return world reference data counts.')]
    public function statistics(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        return $this->success(
            $this->world->overviewStatistics(),
            'World statistics retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.index', title: 'Paginate countries', description: 'Paginate countries for admin management.')]
    public function countries(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

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
        abort_unless($request->user()?->can('world.view'), 403);

        return $this->success(
            $this->world->countryIdOptions($request->string('search')->toString() ?: null),
            'Country options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.store', title: 'Create country')]
    public function storeCountry(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'iso2' => ['required', 'string', 'size:2'],
            'iso3' => ['sometimes', 'nullable', 'string', 'size:3'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            'native' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subregion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emojiU' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new CountryResource($this->world->createCountry($data)),
            'Country created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.show', title: 'Show country by ID')]
    public function showCountry(Request $request, Country $country): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $country->loadMissing('currency');

        return $this->success(new CountryResource($country), 'Country retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.countries.update', title: 'Update country')]
    public function updateCountry(Request $request, Country $country): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'iso2' => ['sometimes', 'string', 'size:2'],
            'iso3' => ['sometimes', 'nullable', 'string', 'size:3'],
            'status' => ['sometimes', 'integer', 'in:0,1'],
            'phone_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            'native' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subregion' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emojiU' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new CountryResource($this->world->updateCountry($country, $data)),
            'Country updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.countries.destroy', title: 'Delete country')]
    public function destroyCountry(Request $request, Country $country): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteCountry($country);

        return $this->success(null, 'Country deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.states.index', title: 'Paginate states')]
    public function states(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $states = $this->world->paginateStates($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            StateResource::collection($states),
            'States retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.states.options', title: 'State options', description: 'Return all state ID value/label pairs for a country.')]
    public function stateOptions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            $this->world->stateIdOptions(
                (int) $data['country_id'],
                filled($data['search'] ?? null) ? (string) $data['search'] : null,
            ),
            'State options retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'world.admin.states.store', title: 'Create state')]
    public function storeState(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            'state_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new StateResource($this->world->createState($data)),
            'State created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.states.show', title: 'Show state')]
    public function showState(Request $request, State $state): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $state->loadMissing('country');

        return $this->success(new StateResource($state), 'State retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.states.update', title: 'Update state')]
    public function updateState(Request $request, State $state): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'country_code' => ['sometimes', 'nullable', 'string', 'max:3'],
            'state_code' => ['sometimes', 'nullable', 'string', 'max:5'],
            'type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new StateResource($this->world->updateState($state, $data)),
            'State updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.states.destroy', title: 'Delete state')]
    public function destroyState(Request $request, State $state): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteState($state);

        return $this->success(null, 'State deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.cities.index', title: 'Paginate cities')]
    public function cities(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $cities = $this->world->paginateCities($request->only([
            'search', 'country_id', 'state_id', 'per_page',
        ]));

        return $this->paginated(
            CityResource::collection($cities),
            'Cities retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.store', title: 'Create city')]
    public function storeCity(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'name' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'max:3'],
            'state_code' => ['required', 'string', 'max:5'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new CityResource($this->world->createCity($data)),
            'City created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.show', title: 'Show city')]
    public function showCity(Request $request, City $city): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $city->loadMissing(['country', 'state']);

        return $this->success(new CityResource($city), 'City retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.cities.update', title: 'Update city')]
    public function updateCity(Request $request, City $city): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'state_id' => ['sometimes', 'integer', 'exists:states,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'country_code' => ['sometimes', 'string', 'max:3'],
            'state_code' => ['sometimes', 'string', 'max:5'],
            'latitude' => ['sometimes', 'nullable', 'string', 'max:255'],
            'longitude' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            new CityResource($this->world->updateCity($city, $data)),
            'City updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.cities.destroy', title: 'Delete city')]
    public function destroyCity(Request $request, City $city): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteCity($city);

        return $this->success(null, 'City deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.currencies.index', title: 'Paginate currencies')]
    public function currencies(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $currencies = $this->world->paginateCurrencies($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            CurrencyResource::collection($currencies),
            'Currencies retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.store', title: 'Create currency')]
    public function storeCurrency(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255'],
            'precision' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'symbol' => ['required', 'string', 'max:255'],
            'symbol_native' => ['sometimes', 'nullable', 'string', 'max:255'],
            'symbol_first' => ['sometimes', 'boolean'],
            'decimal_mark' => ['sometimes', 'string', 'size:1'],
            'thousands_separator' => ['sometimes', 'string', 'size:1'],
        ]);

        return $this->success(
            new CurrencyResource($this->world->createCurrency($data)),
            'Currency created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.show', title: 'Show currency')]
    public function showCurrency(Request $request, Currency $currency): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $currency->loadMissing('country');

        return $this->success(new CurrencyResource($currency), 'Currency retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.currencies.update', title: 'Update currency')]
    public function updateCurrency(Request $request, Currency $currency): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:255'],
            'precision' => ['sometimes', 'integer', 'min:0', 'max:8'],
            'symbol' => ['sometimes', 'string', 'max:255'],
            'symbol_native' => ['sometimes', 'nullable', 'string', 'max:255'],
            'symbol_first' => ['sometimes', 'boolean'],
            'decimal_mark' => ['sometimes', 'string', 'size:1'],
            'thousands_separator' => ['sometimes', 'string', 'size:1'],
        ]);

        return $this->success(
            new CurrencyResource($this->world->updateCurrency($currency, $data)),
            'Currency updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.currencies.destroy', title: 'Delete currency')]
    public function destroyCurrency(Request $request, Currency $currency): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteCurrency($currency);

        return $this->success(null, 'Currency deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.timezones.index', title: 'Paginate timezones')]
    public function timezones(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $timezones = $this->world->paginateTimezones($request->only([
            'search', 'country_id', 'per_page',
        ]));

        return $this->paginated(
            TimezoneResource::collection($timezones),
            'Timezones retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.store', title: 'Create timezone')]
    public function storeTimezone(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return $this->success(
            new TimezoneResource($this->world->createTimezone($data)),
            'Timezone created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.show', title: 'Show timezone')]
    public function showTimezone(Request $request, Timezone $timezone): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $timezone->loadMissing('country');

        return $this->success(new TimezoneResource($timezone), 'Timezone retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.timezones.update', title: 'Update timezone')]
    public function updateTimezone(Request $request, Timezone $timezone): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'country_id' => ['sometimes', 'integer', 'exists:countries,id'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return $this->success(
            new TimezoneResource($this->world->updateTimezone($timezone, $data)),
            'Timezone updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.timezones.destroy', title: 'Delete timezone')]
    public function destroyTimezone(Request $request, Timezone $timezone): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteTimezone($timezone);

        return $this->success(null, 'Timezone deleted successfully.');
    }

    #[Endpoint(operationId: 'world.admin.languages.index', title: 'Paginate languages')]
    public function languages(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        $languages = $this->world->paginateLanguages($request->only([
            'search', 'dir', 'per_page',
        ]));

        return $this->paginated(
            LanguageResource::collection($languages),
            'Languages retrieved successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.store', title: 'Create language')]
    public function storeLanguage(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('world.create'), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:2'],
            'name' => ['required', 'string', 'max:255'],
            'name_native' => ['required', 'string', 'max:255'],
            'dir' => ['required', 'string', 'in:ltr,rtl'],
        ]);

        return $this->success(
            new LanguageResource($this->world->createLanguage($data)),
            'Language created successfully.',
            201
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.show', title: 'Show language')]
    public function showLanguage(Request $request, Language $language): JsonResponse
    {
        abort_unless($request->user()?->can('world.view'), 403);

        return $this->success(new LanguageResource($language), 'Language retrieved successfully.');
    }

    #[Endpoint(operationId: 'world.admin.languages.update', title: 'Update language')]
    public function updateLanguage(Request $request, Language $language): JsonResponse
    {
        abort_unless($request->user()?->can('world.update'), 403);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'size:2'],
            'name' => ['sometimes', 'string', 'max:255'],
            'name_native' => ['sometimes', 'string', 'max:255'],
            'dir' => ['sometimes', 'string', 'in:ltr,rtl'],
        ]);

        return $this->success(
            new LanguageResource($this->world->updateLanguage($language, $data)),
            'Language updated successfully.'
        );
    }

    #[Endpoint(operationId: 'world.admin.languages.destroy', title: 'Delete language')]
    public function destroyLanguage(Request $request, Language $language): JsonResponse
    {
        abort_unless($request->user()?->can('world.delete'), 403);

        $this->world->deleteLanguage($language);

        return $this->success(null, 'Language deleted successfully.');
    }
}
