<?php

declare(strict_types=1);

it('exposes central and tenant scramble documentation routes', function (): void {
    $this->get('/docs/central')->assertSuccessful();
    $this->get('/docs/central.json')->assertSuccessful()
        ->assertJsonPath('info.title', 'Central Landlord API');

    $this->get('/docs/tenant')->assertSuccessful();
    $this->get('/docs/tenant.json')->assertSuccessful()
        ->assertJsonPath('info.title', 'Tenant API');
});
