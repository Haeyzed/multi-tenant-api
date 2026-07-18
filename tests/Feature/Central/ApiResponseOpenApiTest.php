<?php

declare(strict_types=1);

it('documents resource fields inside the ApiResponse data envelope', function (): void {
    $document = $this->getJson('/docs/central.json')->assertSuccessful()->json();

    $schema = data_get(
        $document,
        'paths./users/{user}.get.responses.200.content.application/json.schema',
    );

    expect($schema)->toBeArray()
        ->and(data_get($schema, 'properties.status'))->not->toBeNull()
        ->and(data_get($schema, 'properties.message'))->not->toBeNull()
        ->and(data_get($schema, 'properties.data'))->not->toBeNull();

    $dataSchema = data_get($schema, 'properties.data');
    $dataRef = data_get($dataSchema, '$ref')
        ?? data_get($dataSchema, 'allOf.0.$ref')
        ?? data_get($dataSchema, 'anyOf.0.$ref');

    expect($dataRef)->toBeString()->toContain('UserResource');

    $dataType = data_get($dataSchema, 'type');
    if ($dataType === 'object' && $dataRef === null) {
        expect(data_get($dataSchema, 'properties.id'))->not->toBeNull();
    }
});

it('documents paginated list payloads as resource arrays under data', function (): void {
    $document = $this->getJson('/docs/central.json')->assertSuccessful()->json();

    $schema = data_get(
        $document,
        'paths./tenants.get.responses.200.content.application/json.schema',
    );

    expect($schema)->toBeArray();

    $dataSchema = data_get($schema, 'properties.data')
        ?? data_get($schema, 'anyOf.0.properties.data')
        ?? data_get($schema, 'anyOf.1.properties.data');

    expect($dataSchema)->toBeArray()
        ->and(data_get($dataSchema, 'type'))->toBe('array');

    $itemsRef = data_get($dataSchema, 'items.$ref')
        ?? data_get($dataSchema, 'items.allOf.0.$ref');

    expect($itemsRef)->toBeString()->toContain('TenantResource');
});
