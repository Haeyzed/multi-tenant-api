<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Central\Api\ApiManagementController;
use App\Http\Controllers\Api\Central\Audit\AuditController;
use App\Http\Controllers\Api\Central\Auth\AuthController;
use App\Http\Controllers\Api\Central\Auth\EmailVerificationController;
use App\Http\Controllers\Api\Central\Auth\PersonalAccessTokenController;
use App\Http\Controllers\Api\Central\Auth\ProfileController;
use App\Http\Controllers\Api\Central\Auth\SessionController;
use App\Http\Controllers\Api\Central\Auth\TwoFactorController;
use App\Http\Controllers\Api\Central\Billing\BillingController;
use App\Http\Controllers\Api\Central\Billing\FeatureController;
use App\Http\Controllers\Api\Central\Billing\PaymentWebhookController;
use App\Http\Controllers\Api\Central\Billing\PlanController;
use App\Http\Controllers\Api\Central\Billing\SubscriptionController;
use App\Http\Controllers\Api\Central\Communications\AnnouncementController;
use App\Http\Controllers\Api\Central\Communications\NotificationController;
use App\Http\Controllers\Api\Central\Dashboard\DashboardController;
use App\Http\Controllers\Api\Central\Monitoring\MonitoringController;
use App\Http\Controllers\Api\Central\Platform\PlatformOpsController;
use App\Http\Controllers\Api\Central\Public\PublicBillingController;
use App\Http\Controllers\Api\Central\Public\SignupController;
use App\Http\Controllers\Api\Central\Rbac\PermissionController;
use App\Http\Controllers\Api\Central\Rbac\RoleController;
use App\Http\Controllers\Api\Central\Settings\SettingController;
use App\Http\Controllers\Api\Central\Support\TicketController;
use App\Http\Controllers\Api\Central\Tenants\DomainController;
use App\Http\Controllers\Api\Central\Tenants\TenantController;
use App\Http\Controllers\Api\Central\Users\UserController;
use App\Http\Controllers\Api\Central\World\WorldAdminController;
use App\Http\Controllers\Api\Central\World\WorldController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->name('central.auth.login');
    Route::post('auth/two-factor/confirm', [AuthController::class, 'confirmTwoFactor'])->name('central.auth.two-factor.confirm');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset')
        ->name('central.auth.forgot-password');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->name('central.auth.reset-password');
});

Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:auth')
    ->name('central.auth.verification.verify');

Route::post('webhooks/payments/{gateway}', PaymentWebhookController::class)
    ->middleware('throttle:api')
    ->name('central.billing.webhooks.payments');

Route::middleware('throttle:api')->group(function (): void {
    Route::get('public/plans', [SignupController::class, 'plans'])->name('central.public.plans.index');
    Route::get('public/plans/options', [SignupController::class, 'planOptions'])->name('central.public.plans.options');
    Route::get('public/signup/payment-options', [SignupController::class, 'paymentOptions'])->name('central.public.signup.payment-options');
    Route::get('public/settings', [SettingController::class, 'publicSettings'])->name('central.public.settings.index');

    Route::get('world/countries', [WorldController::class, 'countries'])->name('central.world.countries.index');
    Route::get('world/countries/options', [WorldController::class, 'countryOptions'])->name('central.world.countries.options');
    Route::get('world/countries/{iso2}', [WorldController::class, 'showCountry'])->name('central.world.countries.show');
    Route::get('world/countries/{iso2}/states', [WorldController::class, 'states'])->name('central.world.countries.states');
    Route::get('world/states/{state}/cities', [WorldController::class, 'cities'])->name('central.world.states.cities');
    Route::get('world/currencies', [WorldController::class, 'currencies'])->name('central.world.currencies.index');
    Route::get('world/currencies/options', [WorldController::class, 'currencyOptions'])->name('central.world.currencies.options');
    Route::get('world/timezones', [WorldController::class, 'timezones'])->name('central.world.timezones.index');
    Route::get('world/languages', [WorldController::class, 'languages'])->name('central.world.languages.index');
});

Route::middleware('throttle:auth')->group(function (): void {
    Route::post('public/signup', [SignupController::class, 'store'])->name('central.public.signup.store');
    Route::post('public/signup/setup', [SignupController::class, 'setup'])->name('central.public.signup.setup');
    Route::post('public/signup/complete', [SignupController::class, 'complete'])->name('central.public.signup.complete');
    Route::get('public/billing/checkout/{subscription}', [PublicBillingController::class, 'checkout'])
        ->middleware('signed')
        ->name('central.public.billing.checkout');
    Route::get('public/billing/invoices/{invoice}', [PublicBillingController::class, 'showInvoice'])
        ->middleware('signed')
        ->name('central.public.billing.invoices.show');
    Route::post('public/billing/invoices/{invoice}/pay', [PublicBillingController::class, 'payInvoice'])
        ->name('central.public.billing.invoices.pay');
    Route::get('public/billing/success', [PublicBillingController::class, 'showSuccess'])
        ->name('central.public.billing.success');
    Route::get('public/billing/cancel', [PublicBillingController::class, 'showCancel'])
        ->name('central.public.billing.cancel');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('central.auth.logout');
    Route::post('auth/email/resend', [EmailVerificationController::class, 'resend'])->name('central.auth.verification.resend');

    Route::get('profile', [ProfileController::class, 'show'])->name('central.profile.show');
    Route::put('profile', [ProfileController::class, 'update'])->name('central.profile.update');
    Route::put('profile/password', [ProfileController::class, 'changePassword'])->name('central.profile.password');

    Route::post('auth/two-factor/enable', [TwoFactorController::class, 'enable'])->name('central.two-factor.enable');
    Route::post('auth/two-factor/confirm-setup', [TwoFactorController::class, 'confirm'])->name('central.two-factor.confirm-setup');
    Route::post('auth/two-factor/disable', [TwoFactorController::class, 'disable'])->name('central.two-factor.disable');
    Route::post('auth/two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('central.two-factor.recovery-codes');

    Route::get('sessions', [SessionController::class, 'index'])->name('central.sessions.index');
    Route::delete('sessions/others', [SessionController::class, 'destroyOthers'])->name('central.sessions.destroy-others');
    Route::delete('sessions/{token}', [SessionController::class, 'destroy'])->name('central.sessions.destroy');

    Route::get('tokens', [PersonalAccessTokenController::class, 'index'])->name('central.tokens.index');
    Route::post('tokens', [PersonalAccessTokenController::class, 'store'])->name('central.tokens.store');
    Route::delete('tokens/{token}', [PersonalAccessTokenController::class, 'destroy'])->name('central.tokens.destroy');

    Route::get('roles', [RoleController::class, 'index'])->name('central.roles.index');
    Route::get('roles/statistics', [RoleController::class, 'statistics'])->name('central.roles.statistics');
    Route::post('roles', [RoleController::class, 'store'])->name('central.roles.store');
    Route::delete('roles/bulk', [RoleController::class, 'bulkDestroy'])->name('central.roles.bulk-destroy');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('central.roles.show');
    Route::put('roles/{role}', [RoleController::class, 'update'])->name('central.roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('central.roles.destroy');
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('central.roles.permissions');

    Route::get('permissions', [PermissionController::class, 'index'])->name('central.permissions.index');
    Route::get('permissions/grouped', [PermissionController::class, 'grouped'])->name('central.permissions.grouped');
    Route::get('permissions/matrix', [PermissionController::class, 'matrix'])->name('central.permissions.matrix');
    Route::get('permissions/statistics', [PermissionController::class, 'statistics'])->name('central.permissions.statistics');
    Route::post('permissions', [PermissionController::class, 'store'])->name('central.permissions.store');
    Route::delete('permissions/bulk', [PermissionController::class, 'bulkDestroy'])->name('central.permissions.bulk-destroy');
    Route::get('permissions/{permission}', [PermissionController::class, 'show'])->name('central.permissions.show');
    Route::put('permissions/{permission}', [PermissionController::class, 'update'])->name('central.permissions.update');
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->name('central.permissions.destroy');

    Route::get('users', [UserController::class, 'index'])->name('central.users.index');
    Route::get('users/statistics', [UserController::class, 'statistics'])->name('central.users.statistics');
    Route::post('users', [UserController::class, 'store'])->name('central.users.store');
    Route::delete('users/bulk', [UserController::class, 'bulkDestroy'])->name('central.users.bulk-destroy');
    Route::post('users/bulk/suspend', [UserController::class, 'bulkSuspend'])->name('central.users.bulk-suspend');
    Route::post('users/bulk/activate', [UserController::class, 'bulkActivate'])->name('central.users.bulk-activate');
    Route::get('users/{user}', [UserController::class, 'show'])->name('central.users.show');
    Route::put('users/{user}', [UserController::class, 'update'])->name('central.users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('central.users.destroy');
    Route::post('users/{user}/restore', [UserController::class, 'restore'])
        ->withTrashed()
        ->name('central.users.restore');
    Route::put('users/{user}/roles', [UserController::class, 'syncRoles'])->name('central.users.roles');
    Route::put('users/{user}/permissions', [UserController::class, 'syncPermissions'])->name('central.users.permissions');
    Route::put('users/{user}/status', [UserController::class, 'updateStatus'])->name('central.users.status');
    Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('central.users.reset-password');
    Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar'])->name('central.users.avatar');
    Route::get('users/{user}/security', [UserController::class, 'security'])->name('central.users.security');
    Route::get('users/{user}/activities', [UserController::class, 'activities'])->name('central.users.activities');

    Route::get('tenants', [TenantController::class, 'index'])->name('central.tenants.index');
    Route::get('tenants/statistics', [TenantController::class, 'overviewStatistics'])->name('central.tenants.statistics');
    Route::get('tenants/options', [TenantController::class, 'options'])->name('central.tenants.options');
    Route::post('tenants', [TenantController::class, 'store'])->name('central.tenants.store');
    Route::delete('tenants/bulk', [TenantController::class, 'bulkDestroy'])->name('central.tenants.bulk-destroy');
    Route::post('tenants/bulk/suspend', [TenantController::class, 'bulkSuspend'])->name('central.tenants.bulk-suspend');
    Route::post('tenants/bulk/activate', [TenantController::class, 'bulkActivate'])->name('central.tenants.bulk-activate');
    Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('central.tenants.show');
    Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('central.tenants.update');
    Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('central.tenants.destroy');
    Route::post('tenants/{tenant}/restore', [TenantController::class, 'restore'])->withTrashed()->name('central.tenants.restore');
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('central.tenants.suspend');
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('central.tenants.activate');
    Route::post('tenants/{tenant}/archive', [TenantController::class, 'archive'])->name('central.tenants.archive');
    Route::put('tenants/{tenant}/tags', [TenantController::class, 'syncTags'])->name('central.tenants.tags');
    Route::put('tenants/{tenant}/metadata', [TenantController::class, 'updateMetadata'])->name('central.tenants.metadata');
    Route::get('tenants/{tenant}/notes', [TenantController::class, 'notes'])->name('central.tenants.notes.index');
    Route::post('tenants/{tenant}/notes', [TenantController::class, 'storeNote'])->name('central.tenants.notes.store');
    Route::get('tenants/{tenant}/statistics', [TenantController::class, 'statistics'])->name('central.tenants.statistics');
    Route::get('tenants/{tenant}/health', [TenantController::class, 'health'])->name('central.tenants.health');
    Route::get('tenants/{tenant}/activities', [TenantController::class, 'activities'])->name('central.tenants.activities');
    Route::post('tenants/{tenant}/owner/resend-invite', [TenantController::class, 'resendOwnerInvite'])
        ->name('central.tenants.owner.resend-invite');
    Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'startImpersonation'])->name('central.tenants.impersonate');
    Route::post('tenants/{tenant}/impersonations/{impersonation}/revoke', [TenantController::class, 'revokeImpersonation'])
        ->name('central.tenants.impersonations.revoke');

    Route::get('tenants/{tenant}/domains', [DomainController::class, 'index'])->name('central.domains.index');
    Route::post('tenants/{tenant}/domains', [DomainController::class, 'store'])->name('central.domains.store');
    Route::get('tenants/{tenant}/domains/{domain}', [DomainController::class, 'show'])->scopeBindings()->name('central.domains.show');
    Route::put('tenants/{tenant}/domains/{domain}', [DomainController::class, 'update'])->scopeBindings()->name('central.domains.update');
    Route::delete('tenants/{tenant}/domains/{domain}', [DomainController::class, 'destroy'])->scopeBindings()->name('central.domains.destroy');
    Route::post('tenants/{tenant}/domains/{domain}/primary', [DomainController::class, 'makePrimary'])->scopeBindings()->name('central.domains.primary');
    Route::post('tenants/{tenant}/domains/{domain}/dns-token', [DomainController::class, 'regenerateDnsToken'])->scopeBindings()->name('central.domains.dns-token');
    Route::post('tenants/{tenant}/domains/{domain}/verify-dns', [DomainController::class, 'verifyDns'])->scopeBindings()->name('central.domains.verify-dns');
    Route::post('tenants/{tenant}/domains/{domain}/ssl/enable', [DomainController::class, 'enableSsl'])->scopeBindings()->name('central.domains.ssl.enable');
    Route::post('tenants/{tenant}/domains/{domain}/ssl/disable', [DomainController::class, 'disableSsl'])->scopeBindings()->name('central.domains.ssl.disable');
    Route::put('tenants/{tenant}/domains/{domain}/redirect', [DomainController::class, 'setRedirect'])->scopeBindings()->name('central.domains.redirect');

    Route::get('feature-categories', [FeatureController::class, 'categories'])->name('central.feature-categories.index');
    Route::get('feature-categories/options', [FeatureController::class, 'categoryOptions'])->name('central.feature-categories.options');
    Route::post('feature-categories', [FeatureController::class, 'storeCategory'])->name('central.feature-categories.store');
    Route::put('feature-categories/{feature_category}', [FeatureController::class, 'updateCategory'])->name('central.feature-categories.update');
    Route::delete('feature-categories/{feature_category}', [FeatureController::class, 'destroyCategory'])->name('central.feature-categories.destroy');

    Route::get('features', [FeatureController::class, 'index'])->name('central.features.index');
    Route::post('features', [FeatureController::class, 'store'])->name('central.features.store');
    Route::get('features/{feature}', [FeatureController::class, 'show'])->name('central.features.show');
    Route::put('features/{feature}', [FeatureController::class, 'update'])->name('central.features.update');
    Route::delete('features/{feature}', [FeatureController::class, 'destroy'])->name('central.features.destroy');
    Route::post('features/{feature}/restore', [FeatureController::class, 'restore'])->withTrashed()->name('central.features.restore');

    Route::get('plans', [PlanController::class, 'index'])->name('central.plans.index');
    Route::get('plans/statistics', [PlanController::class, 'statistics'])->name('central.plans.statistics');
    Route::post('plans', [PlanController::class, 'store'])->name('central.plans.store');
    Route::delete('plans/bulk', [PlanController::class, 'bulkDestroy'])->name('central.plans.bulk-destroy');
    Route::post('plans/bulk/activate', [PlanController::class, 'bulkActivate'])->name('central.plans.bulk-activate');
    Route::post('plans/bulk/archive', [PlanController::class, 'bulkArchive'])->name('central.plans.bulk-archive');
    Route::get('plans/{plan}', [PlanController::class, 'show'])->name('central.plans.show');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('central.plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('central.plans.destroy');
    Route::post('plans/{plan}/restore', [PlanController::class, 'restore'])->withTrashed()->name('central.plans.restore');
    Route::post('plans/{plan}/activate', [PlanController::class, 'activate'])->name('central.plans.activate');
    Route::post('plans/{plan}/archive', [PlanController::class, 'archive'])->name('central.plans.archive');
    Route::put('plans/{plan}/features', [PlanController::class, 'syncFeatures'])->name('central.plans.features.sync');
    Route::post('plans/{plan}/features', [PlanController::class, 'assignFeature'])->name('central.plans.features.assign');
    Route::delete('plans/{plan}/features/{feature}', [PlanController::class, 'detachFeature'])->name('central.plans.features.detach');
    Route::get('plans/{plan}/features/{feature}/tenants/{tenant}/usage', [PlanController::class, 'usageSummary'])
        ->name('central.plans.features.usage');
    Route::post('feature-usages', [PlanController::class, 'recordUsage'])->name('central.feature-usages.store');

    Route::get('plans/{plan}/prices', [PlanController::class, 'prices'])->name('central.plans.prices.index');
    Route::post('plans/{plan}/prices', [PlanController::class, 'storePrice'])->name('central.plans.prices.store');
    Route::put('plans/{plan}/prices/{planPrice}', [PlanController::class, 'updatePrice'])->scopeBindings()->name('central.plans.prices.update');
    Route::delete('plans/{plan}/prices/{planPrice}', [PlanController::class, 'destroyPrice'])->scopeBindings()->name('central.plans.prices.destroy');

    Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('central.subscriptions.index');
    Route::get('subscriptions/statistics', [SubscriptionController::class, 'statistics'])->name('central.subscriptions.statistics');
    Route::get('subscriptions/options', [SubscriptionController::class, 'options'])->name('central.subscriptions.options');
    Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('central.subscriptions.store');
    Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->name('central.subscriptions.show');
    Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('central.subscriptions.renew');
    Route::post('subscriptions/{subscription}/upgrade', [SubscriptionController::class, 'upgrade'])->name('central.subscriptions.upgrade');
    Route::post('subscriptions/{subscription}/downgrade', [SubscriptionController::class, 'downgrade'])->name('central.subscriptions.downgrade');
    Route::post('subscriptions/{subscription}/pause', [SubscriptionController::class, 'pause'])->name('central.subscriptions.pause');
    Route::post('subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume'])->name('central.subscriptions.resume');
    Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('central.subscriptions.cancel');
    Route::post('subscriptions/{subscription}/expire', [SubscriptionController::class, 'expire'])->name('central.subscriptions.expire');
    Route::post('subscriptions/{subscription}/past-due', [SubscriptionController::class, 'markPastDue'])->name('central.subscriptions.past-due');
    Route::get('subscriptions/{subscription}/history', [SubscriptionController::class, 'history'])->name('central.subscriptions.history');

    Route::get('invoices', [BillingController::class, 'invoices'])->name('central.invoices.index');
    Route::get('invoices/statistics', [BillingController::class, 'invoiceStatistics'])->name('central.invoices.statistics');
    Route::post('invoices', [BillingController::class, 'storeInvoice'])->name('central.invoices.store');
    Route::get('invoices/{invoice}', [BillingController::class, 'showInvoice'])->name('central.invoices.show');
    Route::post('invoices/{invoice}/void', [BillingController::class, 'voidInvoice'])->name('central.invoices.void');
    Route::post('invoices/{invoice}/charge', [BillingController::class, 'chargeInvoice'])->name('central.invoices.charge');
    Route::post('invoices/{invoice}/send-payment-link', [BillingController::class, 'sendInvoicePaymentLink'])->name('central.invoices.send-payment-link');

    Route::get('payments', [BillingController::class, 'payments'])->name('central.payments.index');
    Route::get('payments/statistics', [BillingController::class, 'paymentStatistics'])->name('central.payments.statistics');
    Route::get('payments/{payment}', [BillingController::class, 'showPayment'])->name('central.payments.show');
    Route::post('payments/{payment}/refund', [BillingController::class, 'refundPayment'])->name('central.payments.refund');

    Route::post('tenants/{tenant}/billing-addresses', [BillingController::class, 'storeBillingAddress'])->name('central.billing-addresses.store');
    Route::get('tenants/{tenant}/billing-profile', [BillingController::class, 'showBillingProfile'])->name('central.billing-profiles.show');
    Route::put('tenants/{tenant}/billing-profile', [BillingController::class, 'updateBillingProfile'])->name('central.billing-profiles.update');
    Route::get('payment-gateways', [BillingController::class, 'gateways'])->name('central.payment-gateways.index');
    Route::get('payment-gateways/options', [BillingController::class, 'gatewayOptions'])->name('central.payment-gateways.options');

    Route::get('dashboard', [DashboardController::class, 'overview'])->name('central.dashboard.overview');
    Route::get('dashboard/statistics', [DashboardController::class, 'statistics'])->name('central.dashboard.statistics');
    Route::get('dashboard/revenue', [DashboardController::class, 'revenue'])->name('central.dashboard.revenue');
    Route::get('dashboard/growth', [DashboardController::class, 'growth'])->name('central.dashboard.growth');
    Route::get('dashboard/charts', [DashboardController::class, 'charts'])->name('central.dashboard.charts');
    Route::get('dashboard/activities', [DashboardController::class, 'recentActivities'])->name('central.dashboard.activities');
    Route::get('dashboard/notifications', [DashboardController::class, 'notifications'])->name('central.dashboard.notifications');
    Route::get('dashboard/health', [DashboardController::class, 'health'])->name('central.dashboard.health');

    Route::get('settings', [SettingController::class, 'index'])->name('central.settings.index');
    Route::get('settings/groups', [SettingController::class, 'groups'])->name('central.settings.groups');
    Route::get('settings/grouped', [SettingController::class, 'grouped'])->name('central.settings.grouped');
    Route::post('settings', [SettingController::class, 'store'])->name('central.settings.store');
    Route::put('settings/bulk', [SettingController::class, 'bulkUpdate'])->name('central.settings.bulk');
    Route::post('settings/mail/test', [SettingController::class, 'sendTestMail'])->name('central.settings.mail.test');
    Route::get('settings/{setting}', [SettingController::class, 'show'])->name('central.settings.show');
    Route::put('settings/{setting}', [SettingController::class, 'update'])->name('central.settings.update');
    Route::delete('settings/{setting}', [SettingController::class, 'destroy'])->name('central.settings.destroy');

    Route::get('audit-logs', [AuditController::class, 'index'])->name('central.audit.index');
    Route::get('audit-logs/export', [AuditController::class, 'export'])->name('central.audit.export');
    Route::get('audit-logs/{activity}', [AuditController::class, 'show'])->name('central.audit.show');
    Route::get('users/{user}/audit-logs', [AuditController::class, 'userActivities'])->name('central.audit.users');
    Route::get('tenants/{tenant}/audit-logs', [AuditController::class, 'tenantActivities'])->name('central.audit.tenants');

    Route::get('notifications/inbox', [NotificationController::class, 'inbox'])->name('central.notifications.inbox');
    Route::post('notification-deliveries/{delivery}/read', [NotificationController::class, 'markRead'])->name('central.notifications.read');
    Route::post('notification-deliveries/{delivery}/unread', [NotificationController::class, 'markUnread'])->name('central.notifications.unread');
    Route::get('notifications', [NotificationController::class, 'index'])->name('central.notifications.index');
    Route::post('notifications', [NotificationController::class, 'store'])->name('central.notifications.store');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('central.notifications.show');
    Route::put('notifications/{notification}', [NotificationController::class, 'update'])->name('central.notifications.update');
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('central.notifications.destroy');
    Route::post('notifications/{notification}/schedule', [NotificationController::class, 'schedule'])->name('central.notifications.schedule');
    Route::post('notifications/{notification}/broadcast', [NotificationController::class, 'broadcast'])->name('central.notifications.broadcast');
    Route::post('notifications/{notification}/cancel', [NotificationController::class, 'cancel'])->name('central.notifications.cancel');
    Route::get('notifications/{notification}/history', [NotificationController::class, 'history'])->name('central.notifications.history');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('central.announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('central.announcements.store');
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('central.announcements.show');
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('central.announcements.update');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('central.announcements.destroy');
    Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])->name('central.announcements.publish');
    Route::post('announcements/{announcement}/schedule', [AnnouncementController::class, 'schedule'])->name('central.announcements.schedule');
    Route::post('announcements/{announcement}/archive', [AnnouncementController::class, 'archive'])->name('central.announcements.archive');
    Route::get('announcements/{announcement}/history', [AnnouncementController::class, 'history'])->name('central.announcements.history');

    Route::get('ticket-categories', [TicketController::class, 'categories'])->name('central.ticket-categories.index');
    Route::post('ticket-categories', [TicketController::class, 'storeCategory'])->name('central.ticket-categories.store');
    Route::get('tickets', [TicketController::class, 'index'])->name('central.tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('central.tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('central.tickets.show');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('central.tickets.update');
    Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('central.tickets.destroy');
    Route::post('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('central.tickets.assign');
    Route::put('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('central.tickets.status');
    Route::put('tickets/{ticket}/priority', [TicketController::class, 'updatePriority'])->name('central.tickets.priority');
    Route::post('tickets/{ticket}/replies', [TicketController::class, 'reply'])->name('central.tickets.replies');
    Route::get('tickets/{ticket}/history', [TicketController::class, 'history'])->name('central.tickets.history');

    Route::get('monitoring', [MonitoringController::class, 'overview'])->name('central.monitoring.overview');
    Route::get('monitoring/queue', [MonitoringController::class, 'queue'])->name('central.monitoring.queue');
    Route::get('monitoring/failed-jobs', [MonitoringController::class, 'failedJobs'])->name('central.monitoring.failed-jobs');
    Route::post('monitoring/failed-jobs/{failedJob}/retry', [MonitoringController::class, 'retryFailedJob'])->name('central.monitoring.failed-jobs.retry');
    Route::delete('monitoring/failed-jobs', [MonitoringController::class, 'flushFailedJobs'])->name('central.monitoring.failed-jobs.flush');
    Route::get('monitoring/database', [MonitoringController::class, 'database'])->name('central.monitoring.database');
    Route::get('monitoring/storage', [MonitoringController::class, 'storage'])->name('central.monitoring.storage');
    Route::get('monitoring/redis', [MonitoringController::class, 'redis'])->name('central.monitoring.redis');
    Route::get('monitoring/server', [MonitoringController::class, 'server'])->name('central.monitoring.server');

    Route::get('world/statistics', [WorldAdminController::class, 'statistics'])->name('central.world.statistics');
    Route::get('world/admin/countries', [WorldAdminController::class, 'countries'])->name('central.world.admin.countries.index');
    Route::get('world/admin/countries/options', [WorldAdminController::class, 'countryOptions'])->name('central.world.admin.countries.options');
    Route::post('world/admin/countries', [WorldAdminController::class, 'storeCountry'])->name('central.world.admin.countries.store');
    Route::get('world/admin/countries/{country}', [WorldAdminController::class, 'showCountry'])->name('central.world.admin.countries.show');
    Route::put('world/admin/countries/{country}', [WorldAdminController::class, 'updateCountry'])->name('central.world.admin.countries.update');
    Route::delete('world/admin/countries/{country}', [WorldAdminController::class, 'destroyCountry'])->name('central.world.admin.countries.destroy');
    Route::get('world/admin/states', [WorldAdminController::class, 'states'])->name('central.world.admin.states.index');
    Route::get('world/admin/states/options', [WorldAdminController::class, 'stateOptions'])->name('central.world.admin.states.options');
    Route::post('world/admin/states', [WorldAdminController::class, 'storeState'])->name('central.world.admin.states.store');
    Route::get('world/admin/states/{state}', [WorldAdminController::class, 'showState'])->name('central.world.admin.states.show');
    Route::put('world/admin/states/{state}', [WorldAdminController::class, 'updateState'])->name('central.world.admin.states.update');
    Route::delete('world/admin/states/{state}', [WorldAdminController::class, 'destroyState'])->name('central.world.admin.states.destroy');
    Route::get('world/admin/cities', [WorldAdminController::class, 'cities'])->name('central.world.admin.cities.index');
    Route::post('world/admin/cities', [WorldAdminController::class, 'storeCity'])->name('central.world.admin.cities.store');
    Route::get('world/admin/cities/{city}', [WorldAdminController::class, 'showCity'])->name('central.world.admin.cities.show');
    Route::put('world/admin/cities/{city}', [WorldAdminController::class, 'updateCity'])->name('central.world.admin.cities.update');
    Route::delete('world/admin/cities/{city}', [WorldAdminController::class, 'destroyCity'])->name('central.world.admin.cities.destroy');
    Route::get('world/admin/currencies', [WorldAdminController::class, 'currencies'])->name('central.world.admin.currencies.index');
    Route::post('world/admin/currencies', [WorldAdminController::class, 'storeCurrency'])->name('central.world.admin.currencies.store');
    Route::get('world/admin/currencies/{currency}', [WorldAdminController::class, 'showCurrency'])->name('central.world.admin.currencies.show');
    Route::put('world/admin/currencies/{currency}', [WorldAdminController::class, 'updateCurrency'])->name('central.world.admin.currencies.update');
    Route::delete('world/admin/currencies/{currency}', [WorldAdminController::class, 'destroyCurrency'])->name('central.world.admin.currencies.destroy');
    Route::get('world/admin/timezones', [WorldAdminController::class, 'timezones'])->name('central.world.admin.timezones.index');
    Route::post('world/admin/timezones', [WorldAdminController::class, 'storeTimezone'])->name('central.world.admin.timezones.store');
    Route::get('world/admin/timezones/{timezone}', [WorldAdminController::class, 'showTimezone'])->name('central.world.admin.timezones.show');
    Route::put('world/admin/timezones/{timezone}', [WorldAdminController::class, 'updateTimezone'])->name('central.world.admin.timezones.update');
    Route::delete('world/admin/timezones/{timezone}', [WorldAdminController::class, 'destroyTimezone'])->name('central.world.admin.timezones.destroy');
    Route::get('world/admin/languages', [WorldAdminController::class, 'languages'])->name('central.world.admin.languages.index');
    Route::post('world/admin/languages', [WorldAdminController::class, 'storeLanguage'])->name('central.world.admin.languages.store');
    Route::get('world/admin/languages/{language}', [WorldAdminController::class, 'showLanguage'])->name('central.world.admin.languages.show');
    Route::put('world/admin/languages/{language}', [WorldAdminController::class, 'updateLanguage'])->name('central.world.admin.languages.update');
    Route::delete('world/admin/languages/{language}', [WorldAdminController::class, 'destroyLanguage'])->name('central.world.admin.languages.destroy');

    Route::get('api-clients', [ApiManagementController::class, 'clients'])->name('central.api-clients.index');
    Route::post('api-clients', [ApiManagementController::class, 'storeClient'])->name('central.api-clients.store');
    Route::get('api-clients/{api_client}', [ApiManagementController::class, 'showClient'])->name('central.api-clients.show');
    Route::put('api-clients/{api_client}', [ApiManagementController::class, 'updateClient'])->name('central.api-clients.update');
    Route::post('api-clients/{api_client}/rotate-secret', [ApiManagementController::class, 'rotateSecret'])->name('central.api-clients.rotate');
    Route::delete('api-clients/{api_client}', [ApiManagementController::class, 'destroyClient'])->name('central.api-clients.destroy');

    Route::get('webhook-events', [ApiManagementController::class, 'events'])->name('central.webhook-events.index');
    Route::get('webhooks', [ApiManagementController::class, 'webhooks'])->name('central.webhooks.index');
    Route::post('webhooks', [ApiManagementController::class, 'storeWebhook'])->name('central.webhooks.store');
    Route::get('webhooks/{webhook}', [ApiManagementController::class, 'showWebhook'])->name('central.webhooks.show');
    Route::put('webhooks/{webhook}', [ApiManagementController::class, 'updateWebhook'])->name('central.webhooks.update');
    Route::delete('webhooks/{webhook}', [ApiManagementController::class, 'destroyWebhook'])->name('central.webhooks.destroy');
    Route::post('webhooks/{webhook}/dispatch', [ApiManagementController::class, 'dispatchWebhook'])->name('central.webhooks.dispatch');
    Route::get('webhooks/{webhook}/deliveries', [ApiManagementController::class, 'webhookLogs'])->name('central.webhooks.deliveries');
    Route::post('webhook-deliveries/{delivery}/retry', [ApiManagementController::class, 'retryDelivery'])->name('central.webhook-deliveries.retry');

    Route::get('ai-providers', [PlatformOpsController::class, 'aiProviders'])->name('central.ai-providers.index');
    Route::put('ai-providers', [PlatformOpsController::class, 'upsertAiProvider'])->name('central.ai-providers.upsert');
    Route::post('ai-providers/{ai_provider}/usage', [PlatformOpsController::class, 'recordAiUsage'])->name('central.ai-providers.usage');

    Route::get('integrations', [PlatformOpsController::class, 'integrations'])->name('central.integrations.index');
    Route::post('integrations', [PlatformOpsController::class, 'storeIntegration'])->name('central.integrations.store');
    Route::post('integrations/{integration}/install', [PlatformOpsController::class, 'installIntegration'])->name('central.integrations.install');
    Route::post('installed-integrations/{installation}/activate', [PlatformOpsController::class, 'activateInstallation'])->name('central.installed-integrations.activate');
    Route::put('installed-integrations/{installation}/configuration', [PlatformOpsController::class, 'configureInstallation'])->name('central.installed-integrations.configure');

    Route::get('themes', [PlatformOpsController::class, 'themes'])->name('central.themes.index');
    Route::post('themes', [PlatformOpsController::class, 'storeTheme'])->name('central.themes.store');
    Route::post('themes/{theme}/publish', [PlatformOpsController::class, 'publishTheme'])->name('central.themes.publish');
    Route::post('themes/{theme}/install', [PlatformOpsController::class, 'installTheme'])->name('central.themes.install');
    Route::post('theme-installations/{installation}/activate', [PlatformOpsController::class, 'activateTheme'])->name('central.theme-installations.activate');

    Route::get('backups', [PlatformOpsController::class, 'backups'])->name('central.backups.index');
    Route::post('backups', [PlatformOpsController::class, 'storeBackup'])->name('central.backups.store');
    Route::post('backups/{backup}/restore', [PlatformOpsController::class, 'restoreBackup'])->name('central.backups.restore');
    Route::get('backup-schedules', [PlatformOpsController::class, 'backupSchedules'])->name('central.backup-schedules.index');
    Route::post('backup-schedules', [PlatformOpsController::class, 'storeBackupSchedule'])->name('central.backup-schedules.store');
    Route::post('backup-schedules/{schedule}/apply-retention', [PlatformOpsController::class, 'applyRetention'])->name('central.backup-schedules.retention');

    Route::get('platform-versions', [PlatformOpsController::class, 'versions'])->name('central.platform-versions.index');
    Route::get('platform-versions/current', [PlatformOpsController::class, 'currentVersion'])->name('central.platform-versions.current');
    Route::post('platform-versions', [PlatformOpsController::class, 'storeVersion'])->name('central.platform-versions.store');
    Route::post('platform-versions/{version}/release', [PlatformOpsController::class, 'releaseVersion'])->name('central.platform-versions.release');
    Route::post('platform-versions/{version}/rollback', [PlatformOpsController::class, 'rollbackVersion'])->name('central.platform-versions.rollback');
});
