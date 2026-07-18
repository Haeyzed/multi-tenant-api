<?php

declare(strict_types=1);

namespace Database\Seeders\Central;

use App\Enums\Central\AIProvider;
use App\Enums\Central\AnnouncementStatus;
use App\Enums\Central\AnnouncementTarget;
use App\Enums\Central\AnnouncementType;
use App\Enums\Central\ApiKeyType;
use App\Enums\Central\BackupStatus;
use App\Enums\Central\BackupType;
use App\Enums\Central\DeliveryStatus;
use App\Enums\Central\DomainStatus;
use App\Enums\Central\DomainType;
use App\Enums\Central\FeatureStatus;
use App\Enums\Central\IntegrationStatus;
use App\Enums\Central\InvoiceStatus;
use App\Enums\Central\NotificationChannel;
use App\Enums\Central\NotificationStatus;
use App\Enums\Central\PaymentGateway;
use App\Enums\Central\PaymentStatus;
use App\Enums\Central\PlanFeatureLimitType;
use App\Enums\Central\PlanStatus;
use App\Enums\Central\PlanVisibility;
use App\Enums\Central\PlatformVersionStatus;
use App\Enums\Central\SubscriptionInterval;
use App\Enums\Central\SubscriptionStatus;
use App\Enums\Central\TenantStatus;
use App\Enums\Central\ThemeStatus;
use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Enums\Central\UserStatus;
use App\Enums\Central\WebhookEvent;
use App\Enums\Central\WebhookStatus;
use App\Models\Central\AiProviderSetting;
use App\Models\Central\Announcement;
use App\Models\Central\ApiClient;
use App\Models\Central\Backup;
use App\Models\Central\BackupSchedule;
use App\Models\Central\BillingAddress;
use App\Models\Central\Domain;
use App\Models\Central\Feature;
use App\Models\Central\FeatureCategory;
use App\Models\Central\InstalledIntegration;
use App\Models\Central\Integration;
use App\Models\Central\Invoice;
use App\Models\Central\InvoiceItem;
use App\Models\Central\NotificationDelivery;
use App\Models\Central\Payment;
use App\Models\Central\Plan;
use App\Models\Central\PlanPrice;
use App\Models\Central\PlatformNotification;
use App\Models\Central\PlatformVersion;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\TenantNote;
use App\Models\Central\Theme;
use App\Models\Central\ThemeInstallation;
use App\Models\Central\Ticket;
use App\Models\Central\TicketCategory;
use App\Models\Central\TicketReply;
use App\Models\Central\Webhook;
use App\Models\Central\WebhookDelivery;
use App\Models\User;
use App\Services\Central\Tenants\TenantOwnerProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Random\RandomException;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding RBAC + settings…');
        $this->call([
            RbacSeeder::class,
            SettingSeeder::class,
        ]);

        $admin = $this->seedUsers();
        $operator = User::query()->where('email', 'operator@example.com')->firstOrFail();

        $this->command?->info('Seeding catalog (features/plans)…');
        [$starter, $growth, $scale] = $this->seedCatalog();

        $this->command?->info('Seeding tenants, billing, and subscriptions…');
        $tenants = $this->seedTenantsAndBilling($starter, $growth, $scale, $admin);

        $this->command?->info('Provisioning Acme tenant DB + owner…');
        $this->provisionAcmeOwner($tenants[0]);

        $this->command?->info('Seeding communications + support…');
        $this->seedCommunications($admin, $operator, $tenants);

        $this->command?->info('Seeding platform ops…');
        $this->seedPlatformOps($admin, $tenants);

        $this->command?->info('Demo data ready.');
        $this->command?->table(
            ['Account', 'Email', 'Password', 'Role'],
            [
                ['Super Admin', 'admin@example.com', 'password', 'super-admin'],
                ['Operator', 'operator@example.com', 'password', 'operator'],
                ['Support Agent', 'support@example.com', 'password', 'operator'],
                ['Acme Owner', 'acme@example.com', 'password', 'tenant owner @ acme.localhost'],
            ],
        );
    }

    private function provisionAcmeOwner(Tenant $tenant): void
    {
        Mail::fake();

        try {
            (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
            (new MigrateDatabase($tenant))->handle();
            app(TenantOwnerProvisioningService::class)->provisionWithPassword($tenant, 'password', 'Acme Owner');
            $this->command?->info('Acme owner ready: acme@example.com / password (host: acme.localhost)');
        } catch (\Throwable $e) {
            $this->command?->warn('Skipped Acme tenant DB provisioning: '.$e->getMessage());
            $this->command?->warn('Run: php artisan tenants:provision-owner acme --password=password --migrate');
        }
    }

    private function seedUsers(): User
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'timezone' => 'UTC',
            ],
        );
        $admin->syncRoles(['super-admin']);

        $operator = User::query()->updateOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name' => 'Ops Operator',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'timezone' => 'UTC',
            ],
        );
        $operator->syncRoles(['operator']);

        $support = User::query()->updateOrCreate(
            ['email' => 'support@example.com'],
            [
                'name' => 'Support Agent',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
                'timezone' => 'UTC',
            ],
        );
        $support->syncRoles(['operator']);

        activity()->causedBy($admin)->log('demo.seeded');

        return $admin;
    }

    /**
     * @return array{0: Plan, 1: Plan, 2: Plan}
     */
    private function seedCatalog(): array
    {
        $commerce = FeatureCategory::query()->updateOrCreate(
            ['slug' => 'commerce'],
            ['name' => 'Commerce', 'description' => 'Storefront and catalog capabilities', 'sort_order' => 1],
        );
        $ops = FeatureCategory::query()->updateOrCreate(
            ['slug' => 'operations'],
            ['name' => 'Operations', 'description' => 'Ops and automation', 'sort_order' => 2],
        );

        $products = Feature::query()->updateOrCreate(
            ['key' => 'products'],
            [
                'feature_category_id' => $commerce->id,
                'name' => 'Products',
                'slug' => 'products',
                'description' => 'Product catalog limit',
                'status' => FeatureStatus::Active,
                'default_limit_type' => PlanFeatureLimitType::COUNT,
                'default_limit_value' => 100,
                'unit' => 'products',
                'is_available' => true,
                'tracks_usage' => true,
                'sort_order' => 1,
            ],
        );
        $staff = Feature::query()->updateOrCreate(
            ['key' => 'staff_seats'],
            [
                'feature_category_id' => $ops->id,
                'name' => 'Staff seats',
                'slug' => 'staff-seats',
                'description' => 'Team member seats',
                'status' => FeatureStatus::Active,
                'default_limit_type' => PlanFeatureLimitType::COUNT,
                'default_limit_value' => 5,
                'unit' => 'seats',
                'is_available' => true,
                'tracks_usage' => true,
                'sort_order' => 2,
            ],
        );
        $aiAssist = Feature::query()->updateOrCreate(
            ['key' => 'ai_assistant'],
            [
                'feature_category_id' => $ops->id,
                'name' => 'AI Assistant',
                'slug' => 'ai-assistant',
                'description' => 'AI writing and insights',
                'status' => FeatureStatus::Active,
                'default_limit_type' => PlanFeatureLimitType::BOOLEAN,
                'default_limit_value' => 1,
                'is_available' => true,
                'tracks_usage' => false,
                'sort_order' => 3,
            ],
        );

        // Multi-currency catalog: one plan, many PlanPrice rows (NGN local + USD international).
        $starter = Plan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'For new stores',
                'price' => 15000,
                'currency' => 'NGN',
                'billing_interval' => SubscriptionInterval::MONTHLY,
                'trial_days' => 1,
                'status' => PlanStatus::Active,
                'visibility' => PlanVisibility::Public,
                'is_featured' => false,
                'sort_order' => 1,
            ],
        );
        $growth = Plan::query()->updateOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'description' => 'For scaling brands',
                'price' => 45000,
                'currency' => 'NGN',
                'billing_interval' => SubscriptionInterval::MONTHLY,
                'trial_days' => 1,
                'status' => PlanStatus::Active,
                'visibility' => PlanVisibility::Public,
                'is_featured' => true,
                'sort_order' => 2,
            ],
        );
        $scale = Plan::query()->updateOrCreate(
            ['slug' => 'scale'],
            [
                'name' => 'Scale',
                'description' => 'For high-volume merchants',
                'price' => 250000,
                'currency' => 'NGN',
                'billing_interval' => SubscriptionInterval::YEARLY,
                'trial_days' => 0,
                'status' => PlanStatus::Active,
                'visibility' => PlanVisibility::Public,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        );

        Plan::query()->where('slug', 'global')->delete();

        $this->seedPlanPrices($starter, [
            ['amount' => 15000, 'currency' => 'NGN', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 29, 'currency' => 'USD', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 27, 'currency' => 'EUR', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 25, 'currency' => 'GBP', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
        ]);
        $this->seedPlanPrices($growth, [
            ['amount' => 45000, 'currency' => 'NGN', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 79, 'currency' => 'USD', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 75, 'currency' => 'EUR', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
            ['amount' => 69, 'currency' => 'GBP', 'billing_interval' => SubscriptionInterval::MONTHLY, 'trial_days' => 1],
        ]);
        $this->seedPlanPrices($scale, [
            ['amount' => 250000, 'currency' => 'NGN', 'billing_interval' => SubscriptionInterval::YEARLY, 'trial_days' => 0],
            ['amount' => 799, 'currency' => 'USD', 'billing_interval' => SubscriptionInterval::YEARLY, 'trial_days' => 0],
            ['amount' => 749, 'currency' => 'EUR', 'billing_interval' => SubscriptionInterval::YEARLY, 'trial_days' => 0],
            ['amount' => 699, 'currency' => 'GBP', 'billing_interval' => SubscriptionInterval::YEARLY, 'trial_days' => 0],
        ]);

        foreach ([$starter, $growth, $scale] as $index => $plan) {
            $plan->features()->sync([
                $products->id => [
                    'limit_type' => PlanFeatureLimitType::COUNT->value,
                    'limit_value' => [100, 1000, null][$index],
                    'is_unlimited' => $index === 2,
                    'is_enabled' => true,
                    'tracks_usage' => true,
                ],
                $staff->id => [
                    'limit_type' => PlanFeatureLimitType::COUNT->value,
                    'limit_value' => [3, 15, 50][$index],
                    'is_unlimited' => false,
                    'is_enabled' => true,
                    'tracks_usage' => true,
                ],
                $aiAssist->id => [
                    'limit_type' => PlanFeatureLimitType::BOOLEAN->value,
                    'limit_value' => $index === 0 ? 0 : 1,
                    'is_unlimited' => false,
                    'is_enabled' => $index > 0,
                    'tracks_usage' => false,
                ],
            ]);
        }

        return [$starter, $growth, $scale];
    }

    /**
     * @param  list<array{amount: float|int, currency: string, billing_interval: SubscriptionInterval, trial_days: int}>  $rows
     */
    private function seedPlanPrices(Plan $plan, array $rows): void
    {
        foreach ($rows as $row) {
            PlanPrice::query()->updateOrCreate(
                [
                    'plan_id' => $plan->id,
                    'currency' => $row['currency'],
                    'billing_interval' => $row['billing_interval'],
                ],
                [
                    'amount' => $row['amount'],
                    'trial_days' => $row['trial_days'],
                    'status' => PlanStatus::Active,
                ],
            );
        }
    }

    /**
     * @return list<Tenant>
     * @throws RandomException
     */
    private function seedTenantsAndBilling(Plan $starter, Plan $growth, Plan $scale, User $admin): array
    {
        $definitions = [
            ['Acme Commerce', 'acme', TenantStatus::ACTIVE, $growth, 'NG', 'NGN', PaymentGateway::PAYSTACK],
            ['Northwind Traders', 'northwind', TenantStatus::TRIAL, $starter, 'NG', 'NGN', PaymentGateway::PAYSTACK],
            ['Brightline Retail', 'brightline', TenantStatus::ACTIVE, $scale, 'US', 'USD', PaymentGateway::STRIPE],
            ['Paused Outfitters', 'paused-outfitters', TenantStatus::SUSPENDED, $starter, 'GH', 'NGN', PaymentGateway::FLUTTERWAVE],
            ['Atlas Global', 'atlas-global', TenantStatus::TRIAL, $growth, 'US', 'USD', PaymentGateway::STRIPE],
        ];

        $tenants = [];

        foreach ($definitions as [$name, $slug, $status, $plan, $country, $currency, $gateway]) {
            /** @var Tenant $tenant */
            $tenant = Tenant::withoutEvents(function () use ($name, $slug, $status, $country): Tenant {
                $tenant = Tenant::query()->firstOrCreate(
                    ['slug' => $slug],
                    [
                        'id' => (string) Str::uuid(),
                        'name' => $name,
                        'email' => $slug.'@example.com',
                        'phone' => '+1555000'.random_int(1000, 9999),
                        'status' => $status,
                        'tags' => ['demo', $slug],
                        'metadata' => ['seeded' => true, 'billing_country' => $country],
                        'trial_ends_at' => $status === TenantStatus::TRIAL ? now()->subHour() : null,
                        'suspended_at' => $status === TenantStatus::SUSPENDED ? now()->subDay() : null,
                        'suspended_reason' => $status === TenantStatus::SUSPENDED ? 'Payment retry exhausted' : null,
                    ],
                );

                $tenant->fill([
                    'name' => $name,
                    'email' => $slug.'@example.com',
                    'status' => $status,
                    'tags' => ['demo', $slug],
                    'metadata' => ['seeded' => true, 'billing_country' => $country],
                    'trial_ends_at' => $status === TenantStatus::TRIAL ? now()->addDays(10) : null,
                    'suspended_at' => $status === TenantStatus::SUSPENDED ? now()->subDay() : null,
                    'suspended_reason' => $status === TenantStatus::SUSPENDED ? 'Payment retry exhausted' : null,
                ])->save();

                return $tenant;
            });

            $planPrice = $plan->prices()
                ->where('currency', $currency)
                ->orderBy('id')
                ->first()
                ?? $plan->prices()->orderBy('id')->first();

            $amount = $planPrice?->amount ?? $plan->price;
            $interval = $planPrice?->billing_interval ?? $plan->billing_interval;

            Domain::query()->updateOrCreate(
                ['domain' => $slug.'.localhost'],
                [
                    'tenant_id' => $tenant->id,
                    'type' => DomainType::SUBDOMAIN,
                    'status' => DomainStatus::ACTIVE,
                    'is_primary' => true,
                    'ssl_enabled' => true,
                    'force_https' => true,
                ],
            );

            Domain::query()->updateOrCreate(
                ['domain' => 'shop.'.$slug.'.example'],
                [
                    'tenant_id' => $tenant->id,
                    'type' => DomainType::CUSTOM,
                    'status' => DomainStatus::PENDING,
                    'is_primary' => false,
                    'dns_verification_token' => Str::random(32),
                ],
            );

            TenantNote::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'body' => 'Demo note: onboarded via DemoSeeder.',
                ],
                [
                    'user_id' => $admin->id,
                    'is_internal' => true,
                ],
            );

            $address = BillingAddress::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'line1' => '100 Market Street',
                ],
                [
                    'name' => $name.' Billing',
                    'city' => $country === 'NG' ? 'Lagos' : 'San Francisco',
                    'state' => $country === 'NG' ? 'LA' : 'CA',
                    'postal_code' => $country === 'NG' ? '100001' : '94105',
                    'country' => $country,
                    'tax_id' => $country.'-'.$slug,
                    'is_default' => true,
                ],
            );

            $subscription = Subscription::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                ],
                [
                    'plan_price_id' => $planPrice?->id,
                    'status' => match ($status) {
                        TenantStatus::TRIAL => SubscriptionStatus::TRIALING,
                        TenantStatus::SUSPENDED => SubscriptionStatus::PAST_DUE,
                        default => SubscriptionStatus::ACTIVE,
                    },
                    'billing_interval' => $interval,
                    'price' => $amount,
                    'currency' => $currency,
                    'gateway' => $gateway,
                    'starts_at' => now()->subMonths(2),
                    'current_period_start' => now()->startOfMonth(),
                    'current_period_end' => now()->startOfMonth()->addMonth(),
                    'trial_ends_at' => $status === TenantStatus::TRIAL ? now()->subHour() : null,
                    'grace_ends_at' => $status === TenantStatus::SUSPENDED ? now()->addDays(3) : null,
                    'metadata' => ['seeded' => true],
                ],
            );

            $invoice = Invoice::query()->updateOrCreate(
                [
                    'number' => 'INV-DEMO-'.strtoupper($slug),
                ],
                [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'billing_address_id' => $address->id,
                    'status' => InvoiceStatus::PAID,
                    'currency' => $currency,
                    'subtotal' => $amount,
                    'tax_rate' => 0,
                    'tax' => 0,
                    'total' => $amount,
                    'amount_paid' => $amount,
                    'issued_at' => now()->subDays(20),
                    'due_at' => now()->subDays(10),
                    'paid_at' => now()->subDays(18),
                    'metadata' => ['seeded' => true],
                ],
            );

            InvoiceItem::query()->updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'description' => $plan->name.' subscription',
                ],
                [
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            );

            Payment::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'invoice_id' => $invoice->id,
                    'gateway_reference' => 'demo_pay_'.$slug,
                ],
                [
                    'subscription_id' => $subscription->id,
                    'gateway' => $gateway,
                    'status' => PaymentStatus::COMPLETED,
                    'amount' => $amount,
                    'currency' => $currency,
                    'paid_at' => now()->subDays(18),
                    'metadata' => ['seeded' => true],
                ],
            );

            activity()
                ->causedBy($admin)
                ->event('created')
                ->withProperties(['tenant_id' => $tenant->id, 'slug' => $slug])
                ->log('tenant.seeded');

            $tenants[] = $tenant->fresh();
        }

        return $tenants;
    }

    /**
     * @param  list<Tenant>  $tenants
     */
    private function seedCommunications(User $admin, User $operator, array $tenants): void
    {
        $notification = PlatformNotification::query()->updateOrCreate(
            ['title' => 'Welcome to the Central console'],
            [
                'body' => 'This is a demo broadcast notification seeded for local exploration.',
                'channels' => [NotificationChannel::IN_APP->value, NotificationChannel::EMAIL->value],
                'status' => NotificationStatus::Sent,
                'sent_at' => now()->subHour(),
                'created_by' => $admin->id,
                'target_user_ids' => [$admin->id, $operator->id],
                'metadata' => ['seeded' => true],
            ],
        );

        foreach ([$admin, $operator] as $user) {
            NotificationDelivery::query()->updateOrCreate(
                [
                    'platform_notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'channel' => NotificationChannel::IN_APP,
                ],
                [
                    'status' => DeliveryStatus::Delivered,
                    'delivered_at' => now()->subHour(),
                ],
            );
        }

        Announcement::query()->updateOrCreate(
            ['title' => 'Scheduled maintenance window'],
            [
                'body' => 'Central API will be briefly unavailable on Sunday 02:00 UTC.',
                'type' => AnnouncementType::MAINTENANCE,
                'target' => AnnouncementTarget::ALL_TENANTS,
                'status' => AnnouncementStatus::Published,
                'is_dismissible' => true,
                'regions' => ['us-east', 'eu-west'],
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(7),
                'published_at' => now()->subDay(),
                'created_by' => $admin->id,
                'metadata' => ['seeded' => true],
            ],
        );

        $billingCat = TicketCategory::query()->updateOrCreate(
            ['slug' => 'billing'],
            ['name' => 'Billing', 'description' => 'Invoices and payments', 'is_active' => true, 'sort_order' => 1],
        );
        $accessCat = TicketCategory::query()->updateOrCreate(
            ['slug' => 'access'],
            ['name' => 'Access', 'description' => 'Login and permissions', 'is_active' => true, 'sort_order' => 2],
        );

        $ticket = Ticket::query()->updateOrCreate(
            ['number' => 'TCK-DEMO0001'],
            [
                'tenant_id' => $tenants[0]->id,
                'ticket_category_id' => $billingCat->id,
                'subject' => 'Invoice PDF missing line items',
                'description' => 'Customer reports the latest invoice PDF is blank.',
                'status' => TicketStatus::OPEN,
                'priority' => TicketPriority::HIGH,
                'created_by' => $operator->id,
                'assigned_to' => $admin->id,
                'metadata' => ['seeded' => true],
            ],
        );

        TicketReply::query()->firstOrCreate(
            [
                'ticket_id' => $ticket->id,
                'body' => 'Investigating invoice renderer for Acme.',
            ],
            [
                'user_id' => $admin->id,
                'is_internal' => true,
            ],
        );

        Ticket::query()->updateOrCreate(
            ['number' => 'TCK-DEMO0002'],
            [
                'tenant_id' => $tenants[1]->id,
                'ticket_category_id' => $accessCat->id,
                'subject' => 'Cannot invite staff member',
                'description' => 'Invite email never arrives during trial.',
                'status' => TicketStatus::PENDING,
                'priority' => TicketPriority::MEDIUM,
                'created_by' => $operator->id,
                'assigned_to' => $operator->id,
                'metadata' => ['seeded' => true],
            ],
        );
    }

    /**
     * @param  list<Tenant>  $tenants
     */
    private function seedPlatformOps(User $admin, array $tenants): void
    {
        $client = ApiClient::query()->updateOrCreate(
            ['client_id' => 'cli_demo_central_ops'],
            [
                'name' => 'Demo Ops Client',
                'client_secret' => 'sec_demo_secret_do_not_use_in_prod',
                'type' => ApiKeyType::SERVICE,
                'scopes' => ['tenants.read', 'billing.read', 'webhooks.manage'],
                'rate_limit_per_minute' => 120,
                'is_active' => true,
                'created_by' => $admin->id,
                'metadata' => ['seeded' => true],
            ],
        );

        $webhook = Webhook::query()->updateOrCreate(
            ['name' => 'Demo tenant webhook'],
            [
                'url' => 'https://example.test/hooks/central',
                'secret' => 'whsec_demo_secret',
                'events' => [
                    WebhookEvent::TENANT_CREATED->value,
                    WebhookEvent::PAYMENT_SUCCEEDED->value,
                    WebhookEvent::SUBSCRIPTION_CANCELLED->value,
                ],
                'is_active' => true,
                'max_retries' => 3,
                'timeout_seconds' => 10,
                'api_client_id' => $client->id,
                'created_by' => $admin->id,
                'metadata' => ['seeded' => true],
            ],
        );

        WebhookDelivery::query()->updateOrCreate(
            [
                'webhook_id' => $webhook->id,
                'event' => WebhookEvent::TENANT_CREATED,
                'attempt' => 1,
            ],
            [
                'status' => WebhookStatus::DELIVERED,
                'response_code' => 200,
                'payload' => json_encode(['event' => 'tenant.created', 'data' => ['id' => $tenants[0]->id]]),
                'response_body' => '{"ok":true}',
                'delivered_at' => now()->subMinutes(30),
            ],
        );

        foreach ([AIProvider::OPENAI, AIProvider::ANTHROPIC, AIProvider::GOOGLE_GEMINI, AIProvider::GROK] as $provider) {
            AiProviderSetting::query()->updateOrCreate(
                ['provider' => $provider],
                [
                    'label' => $provider->label(),
                    'is_enabled' => $provider === AIProvider::OPENAI,
                    'api_key' => $provider === AIProvider::OPENAI ? 'sk-demo-openai-key' : null,
                    'default_model' => $provider->defaultModel(),
                    'monthly_token_limit' => 1_000_000,
                    'monthly_token_usage' => $provider === AIProvider::OPENAI ? 12500 : 0,
                    'credits_remaining' => $provider === AIProvider::OPENAI ? 84.50 : 0,
                    'config' => ['seeded' => true],
                ],
            );
        }

        $slack = Integration::query()->updateOrCreate(
            ['slug' => 'slack-connect'],
            [
                'name' => 'Slack Connect',
                'vendor' => 'Slack',
                'description' => 'Push central alerts into Slack channels.',
                'version' => '1.2.0',
                'status' => IntegrationStatus::ACTIVE,
                'is_marketplace' => true,
                'price' => 0,
                'permissions' => ['notifications.broadcast'],
                'config_schema' => ['channel' => 'string'],
                'metadata' => ['seeded' => true],
            ],
        );

        InstalledIntegration::query()->updateOrCreate(
            [
                'integration_id' => $slack->id,
                'tenant_id' => $tenants[0]->id,
            ],
            [
                'status' => IntegrationStatus::ACTIVE,
                'installed_version' => $slack->version,
                'configuration' => ['channel' => '#acme-ops'],
                'activated_at' => now()->subDays(5),
                'installed_by' => $admin->id,
            ],
        );

        $theme = Theme::query()->updateOrCreate(
            ['slug' => 'aurora'],
            [
                'name' => 'Aurora',
                'description' => 'Clean commerce storefront theme',
                'version' => '2.1.0',
                'status' => ThemeStatus::PUBLISHED,
                'preview_url' => 'https://example.test/themes/aurora',
                'price' => 49,
                'author' => 'Central Themes',
                'metadata' => ['seeded' => true],
            ],
        );

        ThemeInstallation::query()->updateOrCreate(
            [
                'theme_id' => $theme->id,
                'tenant_id' => $tenants[0]->id,
            ],
            [
                'is_active' => true,
                'installed_version' => $theme->version,
                'activated_at' => now()->subDays(2),
                'installed_by' => $admin->id,
            ],
        );

        Backup::query()->updateOrCreate(
            ['name' => 'demo-full-backup'],
            [
                'type' => BackupType::FULL,
                'status' => BackupStatus::COMPLETED,
                'disk' => 'local',
                'path' => 'backups/demo/demo-full-backup.json',
                'size_bytes' => 2048,
                'is_automatic' => false,
                'started_at' => now()->subDay(),
                'completed_at' => now()->subDay()->addMinutes(2),
                'created_by' => $admin->id,
                'metadata' => ['seeded' => true],
            ],
        );

        BackupSchedule::query()->updateOrCreate(
            ['name' => 'Nightly full backup'],
            [
                'type' => BackupType::FULL,
                'cron_expression' => '0 2 * * *',
                'retention_days' => 30,
                'is_active' => true,
                'next_run_at' => now()->addDay()->setTime(2, 0),
                'metadata' => ['seeded' => true],
            ],
        );

        $v1 = PlatformVersion::query()->updateOrCreate(
            ['version' => '1.0.0'],
            [
                'status' => PlatformVersionStatus::Deprecated,
                'release_notes' => 'Initial Central API release (phases 1–4).',
                'is_current' => false,
                'released_at' => now()->subMonths(2),
                'migration_status' => ['ran' => 40, 'pending' => 0],
                'metadata' => ['seeded' => true],
            ],
        );

        PlatformVersion::query()->updateOrCreate(
            ['version' => '1.1.0'],
            [
                'status' => PlatformVersionStatus::Released,
                'release_notes' => 'Dashboard, settings, audit, notifications, support, and platform ops.',
                'is_current' => true,
                'released_at' => now()->subDay(),
                'migration_status' => ['ran' => 55, 'pending' => 0, 'previous' => $v1->version],
                'metadata' => ['seeded' => true],
            ],
        );
    }
}
