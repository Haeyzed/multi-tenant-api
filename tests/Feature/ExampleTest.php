<?php

declare(strict_types=1);

test('the application health endpoint returns a successful response', function (): void {
    $this->get('/up')->assertSuccessful();
});
