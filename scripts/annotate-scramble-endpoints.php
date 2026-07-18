<?php

declare(strict_types=1);

/**
 * Inject Scramble #[Endpoint] attributes onto Central API controller methods.
 *
 * Usage: php scripts/annotate-scramble-endpoints.php
 */

$base = dirname(__DIR__).DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.'Api'.DIRECTORY_SEPARATOR.'Central';

/** @var array<string, array{0: string, 1: string}> $titles */
$titles = [
    'index' => ['List records', 'Return a paginated list of records for this resource.'],
    'store' => ['Create record', 'Create a new record and return it.'],
    'show' => ['Show record', 'Return a single record by ID.'],
    'update' => ['Update record', 'Update an existing record and return it.'],
    'destroy' => ['Delete record', 'Soft-delete or permanently remove a record.'],
    'restore' => ['Restore record', 'Restore a soft-deleted record.'],
    'login' => ['Login', 'Authenticate with email and password. May require 2FA confirmation.'],
    'logout' => ['Logout', 'Revoke the current Sanctum bearer token.'],
    'confirmTwoFactor' => ['Confirm 2FA login', 'Complete login after TOTP challenge.'],
    'forgotPassword' => ['Forgot password', 'Send a password reset link email.'],
    'resetPassword' => ['Reset password', 'Reset password using a valid reset token.'],
    'verify' => ['Verify email', 'Mark the user email as verified via signed link.'],
    'resend' => ['Resend verification', 'Resend the email verification notification.'],
    'enable' => ['Enable 2FA', 'Begin TOTP two-factor setup and return a secret/QR payload.'],
    'confirm' => ['Confirm 2FA setup', 'Confirm TOTP setup with a valid code.'],
    'disable' => ['Disable 2FA', 'Disable two-factor authentication for the user.'],
    'recoveryCodes' => ['2FA recovery codes', 'Regenerate or retrieve two-factor recovery codes.'],
    'destroyOthers' => ['Revoke other sessions', 'Revoke all Sanctum tokens except the current one.'],
    'syncPermissions' => ['Sync permissions', 'Replace assigned permissions for the role or user.'],
    'syncRoles' => ['Sync roles', 'Replace assigned roles for the user.'],
    'updateStatus' => ['Update status', 'Change lifecycle/status for the resource.'],
    'uploadAvatar' => ['Upload avatar', 'Upload and attach a user avatar image.'],
    'security' => ['Security summary', 'Return security-related profile details for a user.'],
    'activities' => ['Activity history', 'Paginate activity log entries for this subject.'],
    'suspend' => ['Suspend tenant', 'Suspend a tenant and block platform access.'],
    'activate' => ['Activate', 'Activate the resource for normal use.'],
    'archive' => ['Archive', 'Archive the resource.'],
    'syncTags' => ['Sync tags', 'Replace tenant tags.'],
    'updateMetadata' => ['Update metadata', 'Replace or merge tenant metadata.'],
    'notes' => ['List notes', 'List internal notes for a tenant.'],
    'storeNote' => ['Create note', 'Add an internal note to a tenant.'],
    'statistics' => ['Statistics', 'Return aggregate statistics.'],
    'health' => ['Health check', 'Return health status details.'],
    'startImpersonation' => ['Start impersonation', 'Create a tenant impersonation session token.'],
    'revokeImpersonation' => ['Revoke impersonation', 'Revoke an active impersonation session.'],
    'makePrimary' => ['Make primary domain', 'Mark the domain as the tenant primary domain.'],
    'regenerateDnsToken' => ['Regenerate DNS token', 'Issue a new DNS verification token.'],
    'verifyDns' => ['Verify DNS', 'Attempt DNS verification for the domain.'],
    'enableSsl' => ['Enable SSL', 'Enable SSL for the domain.'],
    'disableSsl' => ['Disable SSL', 'Disable SSL for the domain.'],
    'setRedirect' => ['Set redirect', 'Configure domain redirect rules.'],
    'categories' => ['List categories', 'List categories for this resource.'],
    'storeCategory' => ['Create category', 'Create a new category.'],
    'updateCategory' => ['Update category', 'Update an existing category.'],
    'destroyCategory' => ['Delete category', 'Delete a category.'],
    'syncFeatures' => ['Sync plan features', 'Replace all features assigned to a plan.'],
    'assignFeature' => ['Assign feature', 'Attach a feature to a plan with limits.'],
    'detachFeature' => ['Detach feature', 'Remove a feature from a plan.'],
    'usageSummary' => ['Feature usage summary', 'Summarize feature usage for a tenant on a plan.'],
    'recordUsage' => ['Record feature usage', 'Increment tracked feature usage for a tenant.'],
    'renew' => ['Renew subscription', 'Advance the subscription billing period.'],
    'upgrade' => ['Upgrade subscription', 'Move the subscription to a higher plan.'],
    'downgrade' => ['Downgrade subscription', 'Move the subscription to a lower plan.'],
    'pause' => ['Pause subscription', 'Pause billing and access temporarily.'],
    'resume' => ['Resume subscription', 'Resume a paused subscription.'],
    'cancel' => ['Cancel', 'Cancel a scheduled, draft, or active resource.'],
    'expire' => ['Expire subscription', 'Mark the subscription as expired.'],
    'markPastDue' => ['Mark past due', 'Mark past due and optionally start grace period.'],
    'history' => ['History', 'Paginate history/audit events for this resource.'],
    'invoices' => ['List invoices', 'Paginate invoices with optional filters.'],
    'storeInvoice' => ['Create invoice', 'Create an invoice for a tenant/subscription.'],
    'showInvoice' => ['Show invoice', 'Return invoice details including line items.'],
    'voidInvoice' => ['Void invoice', 'Void an unpaid invoice.'],
    'chargeInvoice' => ['Charge invoice', 'Charge an invoice through a payment gateway driver.'],
    'payments' => ['List payments', 'Paginate payment records.'],
    'showPayment' => ['Show payment', 'Return payment details.'],
    'refundPayment' => ['Refund payment', 'Issue a full or partial refund.'],
    'storeBillingAddress' => ['Create billing address', 'Add a billing address for a tenant.'],
    'gateways' => ['List gateways', 'List available payment gateway drivers.'],
    'overview' => ['Overview', 'Return a dashboard/monitoring overview payload.'],
    'revenue' => ['Revenue metrics', 'Return MRR, ARR, and related revenue metrics.'],
    'growth' => ['Growth metrics', 'Compare current vs previous period growth.'],
    'charts' => ['Charts data', 'Return time-series chart datasets.'],
    'recentActivities' => ['Recent activities', 'Return the latest platform activity entries.'],
    'notifications' => ['Notification summary', 'Return unread/total notification counts for the user.'],
    'groups' => ['Setting groups', 'List available setting groups.'],
    'grouped' => ['Grouped settings', 'Return settings keyed by group.'],
    'bulkUpdate' => ['Bulk update settings', 'Update multiple settings by key in one request.'],
    'export' => ['Export audit logs', 'Stream matching audit logs as CSV.'],
    'userActivities' => ['User audit logs', 'Paginate activities caused by or about a user.'],
    'tenantActivities' => ['Tenant audit logs', 'Paginate activities performed on a tenant.'],
    'schedule' => ['Schedule', 'Schedule the resource for a future time.'],
    'broadcast' => ['Broadcast notification', 'Deliver the notification to target users/channels.'],
    'inbox' => ['Notification inbox', 'List in-app notification deliveries for the current user.'],
    'markRead' => ['Mark as read', 'Mark a notification delivery as read.'],
    'markUnread' => ['Mark as unread', 'Mark a notification delivery as unread.'],
    'publish' => ['Publish', 'Publish the resource to make it visible.'],
    'assign' => ['Assign ticket', 'Assign a support ticket to a staff user.'],
    'updatePriority' => ['Update priority', 'Change ticket priority and SLA target.'],
    'reply' => ['Reply to ticket', 'Add a public reply or internal note, optionally with attachment.'],
    'queue' => ['Queue status', 'Return queue depth and failed-job health.'],
    'failedJobs' => ['Failed jobs', 'Paginate failed queue jobs.'],
    'retryFailedJob' => ['Retry failed job', 'Delete a failed job record to allow re-queue workflows.'],
    'flushFailedJobs' => ['Flush failed jobs', 'Delete all failed job records.'],
    'database' => ['Database health', 'Probe database connectivity and latency.'],
    'storage' => ['Storage health', 'Check storage path writability and disk space.'],
    'redis' => ['Redis health', 'Probe Redis when configured as cache/queue driver.'],
    'server' => ['Server info', 'Return PHP/Laravel runtime environment details.'],
    'clients' => ['List API clients', 'Paginate registered API clients (secrets omitted).'],
    'storeClient' => ['Create API client', 'Create a client and return the plaintext secret once.'],
    'showClient' => ['Show API client', 'Return an API client (secret omitted).'],
    'updateClient' => ['Update API client', 'Update client scopes, limits, or active state.'],
    'rotateSecret' => ['Rotate client secret', 'Rotate and return a new plaintext client secret once.'],
    'destroyClient' => ['Delete API client', 'Soft-delete an API client.'],
    'webhooks' => ['List webhooks', 'Paginate outbound webhook endpoints.'],
    'storeWebhook' => ['Create webhook', 'Create a webhook and return the signing secret once.'],
    'showWebhook' => ['Show webhook', 'Return webhook configuration (secret omitted).'],
    'updateWebhook' => ['Update webhook', 'Update webhook URL, events, or retry settings.'],
    'destroyWebhook' => ['Delete webhook', 'Soft-delete a webhook endpoint.'],
    'dispatchWebhook' => ['Dispatch webhook', 'Send a test/manual event delivery to the webhook URL.'],
    'webhookLogs' => ['Webhook deliveries', 'Paginate delivery attempts for a webhook.'],
    'retryDelivery' => ['Retry delivery', 'Retry a failed webhook delivery attempt.'],
    'events' => ['Webhook events catalog', 'List all supported webhook event names.'],
    'aiProviders' => ['List AI providers', 'List configured AI provider settings.'],
    'upsertAiProvider' => ['Upsert AI provider', 'Create or update provider credentials, limits, and credits.'],
    'recordAiUsage' => ['Record AI usage', 'Increment token usage and optionally deduct credits.'],
    'integrations' => ['List integrations', 'Paginate marketplace/integration catalog entries.'],
    'storeIntegration' => ['Create integration', 'Add an integration to the marketplace catalog.'],
    'installIntegration' => ['Install integration', 'Install an integration for a tenant or centrally.'],
    'activateInstallation' => ['Activate installation', 'Activate an installed integration.'],
    'configureInstallation' => ['Configure installation', 'Update configuration for an installed integration.'],
    'themes' => ['List themes', 'Paginate theme marketplace entries.'],
    'storeTheme' => ['Create theme', 'Create a theme entry.'],
    'publishTheme' => ['Publish theme', 'Publish a theme so it can be installed.'],
    'installTheme' => ['Install theme', 'Install a published theme for a tenant.'],
    'activateTheme' => ['Activate theme', 'Activate an installed theme (deactivates siblings).'],
    'backups' => ['List backups', 'Paginate backup records.'],
    'storeBackup' => ['Create backup', 'Run a manual backup snapshot and mark it completed.'],
    'restoreBackup' => ['Restore backup', 'Mark a completed backup as restored.'],
    'backupSchedules' => ['List backup schedules', 'List automatic backup schedules.'],
    'storeBackupSchedule' => ['Create backup schedule', 'Create a cron-based backup schedule with retention.'],
    'applyRetention' => ['Apply retention', 'Delete automatic backups older than retention days.'],
    'versions' => ['List platform versions', 'Paginate platform version records.'],
    'currentVersion' => ['Current platform version', 'Return the currently released platform version.'],
    'storeVersion' => ['Create platform version', 'Create a draft platform version with release notes.'],
    'releaseVersion' => ['Release version', 'Release a version and mark it current.'],
    'rollbackVersion' => ['Rollback version', 'Roll back the current release to a previous version.'],
    'changePassword' => ['Change password', 'Update the authenticated user password.'],
];

function humanize(string $method): string
{
    $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $method) ?? $method;

    return ucfirst(strtolower($spaced));
}

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
    $prefix = strtolower(implode('.', array_map(static fn (string $p): string => lcfirst($p), $parts)));

    return $prefix.'.'.$method;
}

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$updatedFiles = 0;
$updatedMethods = 0;

foreach ($iterator as $file) {
    if (! $file->isFile() || ! str_ends_with($file->getFilename(), 'Controller.php')) {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }

    $changed = false;

    if (! str_contains($content, 'use Dedoc\\Scramble\\Attributes\\Endpoint;')) {
        $withImport = preg_replace(
            '/(use Dedoc\\\\Scramble\\\\Attributes\\\\Group;)/',
            "$1\nuse Dedoc\\Scramble\\Attributes\\Endpoint;",
            $content,
            1
        );
        if (is_string($withImport) && $withImport !== $content) {
            $content = $withImport;
            $changed = true;
        }
    }

    $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];
    $out = [];

    foreach ($lines as $line) {
        if (preg_match('/^(\\s*)public function ([a-zA-Z_][a-zA-Z0-9_]*)\\(/', $line, $m) === 1) {
            $indent = $m[1];
            $method = $m[2];

            if ($method !== '__construct') {
                // Only treat as documented when #[Endpoint] sits immediately above this method.
                $hasEndpoint = false;
                for ($j = count($out) - 1; $j >= 0; $j--) {
                    $prev = trim($out[$j]);
                    if ($prev === '') {
                        continue;
                    }
                    if (str_starts_with($prev, '#[Endpoint') || str_starts_with($prev, '#[\\Dedoc') && str_contains($out[$j], 'Endpoint')) {
                        $hasEndpoint = true;
                    }
                    break;
                }

                if (! $hasEndpoint) {
                    [$title, $description] = $titles[$method] ?? [humanize($method), 'Central API endpoint: '.$method.'.'];
                    $operationId = slugOp($path, $method);
                    $out[] = sprintf(
                        "%s#[Endpoint(operationId: '%s', title: '%s', description: '%s')]",
                        $indent,
                        str_replace("'", "\\'", $operationId),
                        str_replace("'", "\\'", $title),
                        str_replace("'", "\\'", $description),
                    );
                    $updatedMethods++;
                    $changed = true;
                }
            }
        }

        $out[] = $line;
    }

    if ($changed) {
        $newline = str_contains($content, "\r\n") ? "\r\n" : "\n";
        file_put_contents($path, implode($newline, $out).$newline);
        $updatedFiles++;
        echo 'Updated '.$path.PHP_EOL;
    }
}

echo "Done. Files: {$updatedFiles}, methods annotated: {$updatedMethods}".PHP_EOL;
