<?php

declare(strict_types=1);

/**
 * Recompute operationId values and improve titles/descriptions on #[Endpoint] attributes.
 * Usage: php scripts/fix-scramble-operation-ids.php
 */

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'Api'.DIRECTORY_SEPARATOR.'Central';

function slugOp(string $path, string $method): string
{
    $normalized = str_replace('\\', '/', $path);
    $needle = '/Controllers/Api/Central/';
    $pos = strpos($normalized, $needle);
    $relative = $pos === false
        ? basename($normalized, '.php')
        : substr($normalized, $pos + strlen($needle));

    $relative = preg_replace('/Controller\\.php$/', '', $relative) ?? $relative;
    $parts = array_values(array_filter(explode('/', $relative)));
    $lower = array_map(static fn (string $p): string => strtolower($p), $parts);
    $unique = [];
    foreach ($lower as $part) {
        if ($unique === [] || end($unique) !== $part) {
            $unique[] = $part;
        }
    }

    return implode('.', $unique).'.'.$method;
}

/**
 * @return array{0: string, 1: string}
 */
function resourceLabels(string $operationId): array
{
    $parts = explode('.', $operationId);
    array_pop($parts);
    $resource = end($parts) ?: 'record';

    $singularMap = [
        'personalaccesstoken' => 'personal access token',
        'emailverification' => 'email verification',
        'twofactor' => 'two-factor',
        'featureusage' => 'feature usage',
        'apicomanagement' => 'API management',
        'platformops' => 'platform ops',
        'ticketcategory' => 'ticket category',
        'installedintegration' => 'installed integration',
        'themeinstallation' => 'theme installation',
        'backupschedule' => 'backup schedule',
        'platformversion' => 'platform version',
        'aiprovider' => 'AI provider',
        'apiclient' => 'API client',
        'auditlog' => 'audit log',
        'featurecategory' => 'feature category',
    ];

    $pluralMap = [
        'personalaccesstoken' => 'personal access tokens',
        'emailverification' => 'email verifications',
        'twofactor' => 'two-factor settings',
        'featureusage' => 'feature usages',
        'ticketcategory' => 'ticket categories',
        'installedintegration' => 'installed integrations',
        'themeinstallation' => 'theme installations',
        'backupschedule' => 'backup schedules',
        'platformversion' => 'platform versions',
        'aiprovider' => 'AI providers',
        'apiclient' => 'API clients',
        'auditlog' => 'audit logs',
        'featurecategory' => 'feature categories',
        'activity' => 'activities',
        'history' => 'histories',
    ];

    $key = strtolower($resource);
    $singular = $singularMap[$key] ?? str_replace(['-', '_'], ' ', $key);
    $plural = $pluralMap[$key] ?? (str_ends_with($singular, 's') ? $singular : $singular.'s');

    return [$singular, $plural];
}

/**
 * @return array{0: string, 1: string}|null
 */
function crudDocs(string $method, string $singular, string $plural): ?array
{
    return match ($method) {
        'index' => ["List {$plural}", "Return a paginated list of {$plural}."],
        'store' => ["Create {$singular}", "Create a new {$singular} and return it."],
        'show' => ["Show {$singular}", "Return a single {$singular} by ID."],
        'update' => ["Update {$singular}", "Update an existing {$singular} and return it."],
        'destroy' => ["Delete {$singular}", "Soft-delete or permanently remove a {$singular}."],
        'restore' => ["Restore {$singular}", "Restore a soft-deleted {$singular}."],
        'activate' => ["Activate {$singular}", "Activate the {$singular} for normal use."],
        'archive' => ["Archive {$singular}", "Archive the {$singular}."],
        'cancel' => ["Cancel {$singular}", "Cancel a scheduled, draft, or active {$singular}."],
        'history' => ["{$singular} history", "Paginate history events for this {$singular}."],
        'publish' => ["Publish {$singular}", "Publish the {$singular} to make it visible."],
        'statistics' => ["{$singular} statistics", "Return aggregate statistics for {$plural}."],
        'health' => ["{$singular} health", "Return health status details."],
        'overview' => ["{$singular} overview", "Return an overview payload for {$plural}."],
        default => null,
    };
}

$titleOverrides = [
    'auth.profile.show' => ['Show profile', 'Return the authenticated user profile.'],
    'auth.profile.update' => ['Update profile', 'Update the authenticated user profile.'],
    'auth.emailverification.verify' => ['Verify email', 'Mark the user email as verified via signed link.'],
    'auth.emailverification.resend' => ['Resend verification', 'Resend the email verification notification.'],
    'auth.personalaccesstoken.index' => ['List personal tokens', 'List personal access tokens for the current user.'],
    'auth.personalaccesstoken.store' => ['Create personal token', 'Create a personal access token and return the plaintext value once.'],
    'auth.personalaccesstoken.destroy' => ['Revoke personal token', 'Revoke a personal access token.'],
    'auth.session.index' => ['List sessions', 'List active Sanctum sessions/tokens for the current user.'],
    'auth.session.destroy' => ['Revoke session', 'Revoke a specific Sanctum session/token.'],
    'auth.twofactor.enable' => ['Enable 2FA', 'Begin TOTP two-factor setup and return a secret/QR payload.'],
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$fixed = 0;

foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $updated = preg_replace_callback(
        '/#\\[Endpoint\\(operationId: \'([^\']+)\', title: \'([^\']*)\', description: \'([^\']*)\'\\)\\]\\s*\\n(\\s*)public function ([a-zA-Z_][a-zA-Z0-9_]*)\\(/',
        function (array $m) use ($path, $titleOverrides, &$fixed): string {
            $method = $m[5];
            $indent = $m[4];
            $operationId = slugOp($path, $method);
            $title = $m[2];
            $description = $m[3];

            if (isset($titleOverrides[$operationId])) {
                [$title, $description] = $titleOverrides[$operationId];
            } else {
                [$singular, $plural] = resourceLabels($operationId);
                $crud = crudDocs($method, $singular, $plural);
                if ($crud !== null) {
                    [$title, $description] = $crud;
                }
            }

            $fixed++;

            return sprintf(
                "#[Endpoint(operationId: '%s', title: '%s', description: '%s')]\n%spublic function %s(",
                str_replace("'", "\\'", $operationId),
                str_replace("'", "\\'", $title),
                str_replace("'", "\\'", $description),
                $indent,
                $method
            );
        },
        $content
    );

    if (is_string($updated) && $updated !== $content) {
        file_put_contents($path, $updated);
        echo 'Fixed '.$path.PHP_EOL;
    }
}

echo "Done. Endpoints rewritten: {$fixed}".PHP_EOL;
