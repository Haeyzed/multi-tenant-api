<?php

declare(strict_types=1);

namespace App\Services\Central\Settings;

use App\Services\Central\Billing\PaymentGatewayConfigService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Applies database-backed platform settings onto Laravel runtime config.
 *
 * Mail and filesystem defaults are sourced from Settings so scheduled jobs
 * and HTTP requests use the same configuration without writing .env files.
 */
final class ApplySettingsToConfig
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly PaymentGatewayConfigService $gatewayConfigs,
    ) {}

    public function __invoke(): void
    {
        $this->apply();
    }

    public function apply(): void
    {
        if (! $this->settingsTableReady()) {
            return;
        }

        try {
            $map = $this->settings->cachedMap();
        } catch (Throwable) {
            return;
        }

        $this->applyMail($map);
        $this->applyStorage($map);
        $this->applyPayments($map);
    }

    private function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function applyMail(array $map): void
    {
        $mailer = $this->stringValue($map['mail.mailer'] ?? null);

        if (filled($mailer)) {
            Config::set('mail.default', $mailer);
        }

        $fromAddress = $this->stringValue($map['mail.from_address'] ?? null);
        if (filled($fromAddress)) {
            Config::set('mail.from.address', $fromAddress);
        }

        $fromName = $this->stringValue($map['mail.from_name'] ?? null);
        if (filled($fromName)) {
            Config::set('mail.from.name', $fromName);
        }

        if ($mailer !== 'smtp') {
            return;
        }

        $host = $this->stringValue($map['mail.host'] ?? null);
        if (filled($host)) {
            Config::set('mail.mailers.smtp.host', $host);
        }

        if (array_key_exists('mail.port', $map) && $map['mail.port'] !== null && $map['mail.port'] !== '') {
            Config::set('mail.mailers.smtp.port', (int) $map['mail.port']);
        }

        $username = $this->stringValue($map['mail.username'] ?? null);
        if ($username !== null) {
            Config::set('mail.mailers.smtp.username', $username !== '' ? $username : null);
        }

        $password = $this->stringValue($map['mail.password'] ?? null);
        if ($password !== null && $password !== '') {
            Config::set('mail.mailers.smtp.password', $password);
        }

        if (array_key_exists('mail.scheme', $map)) {
            $scheme = $this->stringValue($map['mail.scheme'] ?? null);
            Config::set('mail.mailers.smtp.scheme', filled($scheme) ? $scheme : null);
        }

        try {
            Mail::purge('smtp');
        } catch (Throwable) {
            // Mail manager may not be resolved yet during early boot.
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function applyStorage(array $map): void
    {
        $disk = $this->stringValue($map['storage.default_disk'] ?? null);

        if (filled($disk) && in_array($disk, ['local', 's3', 'public'], true)) {
            Config::set('filesystems.default', $disk);
        }

        $key = $this->stringValue($map['storage.s3_key'] ?? null);
        if ($key !== null) {
            Config::set('filesystems.disks.s3.key', $key !== '' ? $key : null);
        }

        $secret = $this->stringValue($map['storage.s3_secret'] ?? null);
        if ($secret !== null && $secret !== '') {
            Config::set('filesystems.disks.s3.secret', $secret);
        }

        $region = $this->stringValue($map['storage.s3_region'] ?? null);
        if (filled($region)) {
            Config::set('filesystems.disks.s3.region', $region);
        }

        $bucket = $this->stringValue($map['storage.s3_bucket'] ?? null);
        if ($bucket !== null) {
            Config::set('filesystems.disks.s3.bucket', $bucket !== '' ? $bucket : null);
        }

        $url = $this->stringValue($map['storage.s3_url'] ?? null);
        if ($url !== null) {
            Config::set('filesystems.disks.s3.url', $url !== '' ? $url : null);
        }

        $endpoint = $this->stringValue($map['storage.s3_endpoint'] ?? null);
        if ($endpoint !== null) {
            Config::set('filesystems.disks.s3.endpoint', $endpoint !== '' ? $endpoint : null);
        }

        if (array_key_exists('storage.s3_use_path_style_endpoint', $map)) {
            Config::set(
                'filesystems.disks.s3.use_path_style_endpoint',
                filter_var($map['storage.s3_use_path_style_endpoint'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        try {
            Storage::forgetDisk('s3');
        } catch (Throwable) {
            // Disk may not have been resolved yet.
        }
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function applyPayments(array $map): void
    {
        $mode = $this->stringValue($map['billing.mode'] ?? null);
        if (filled($mode)) {
            Config::set(
                'payments.mode',
                in_array($mode, ['test', 'live'], true) ? $mode : 'test',
            );
        }

        $defaultGateway = $this->stringValue($map['billing.default_gateway'] ?? null);
        if (filled($defaultGateway)) {
            Config::set('payments.default', $defaultGateway);
        }

        $defaultCurrency = $this->stringValue($map['billing.default_currency'] ?? null);
        if (filled($defaultCurrency)) {
            Config::set('payments.currency', strtoupper($defaultCurrency));
        }

        $this->setPaymentCredential($map, 'billing.stripe_secret', 'payments.stripe.secret');
        $this->setPaymentCredential($map, 'billing.stripe_publishable', 'payments.stripe.publishable', encrypted: false);
        $this->setPaymentCredential($map, 'billing.stripe_webhook_secret', 'payments.stripe.webhook_secret');

        $this->setPaymentCredential($map, 'billing.paystack_secret', 'payments.paystack.secret');
        $this->setPaymentCredential($map, 'billing.paystack_public', 'payments.paystack.public', encrypted: false);
        $this->setPaymentCredential($map, 'billing.paystack_webhook_secret', 'payments.paystack.webhook_secret');

        $this->setPaymentCredential($map, 'billing.flutterwave_secret', 'payments.flutterwave.secret');
        $this->setPaymentCredential($map, 'billing.flutterwave_public', 'payments.flutterwave.public', encrypted: false);
        $this->setPaymentCredential($map, 'billing.flutterwave_webhook_secret', 'payments.flutterwave.webhook_secret');

        $this->gatewayConfigs->applyActiveEnvironmentToConfig();
    }

    /**
     * @param  array<string, mixed>  $map
     */
    private function setPaymentCredential(
        array $map,
        string $settingKey,
        string $configKey,
        bool $encrypted = true,
    ): void {
        if (! array_key_exists($settingKey, $map)) {
            return;
        }

        $value = $this->stringValue($map[$settingKey] ?? null);

        if ($encrypted && ($value === null || $value === '')) {
            return;
        }

        Config::set($configKey, $value !== '' ? $value : null);
    }
}
