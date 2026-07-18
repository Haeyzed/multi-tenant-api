<?php

declare(strict_types=1);

use App\Enums\Central\AnnouncementStatus;
use App\Enums\Central\AnnouncementType;
use App\Enums\Central\NotificationChannel;
use App\Enums\Central\NotificationStatus;
use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Models\Central\Tenant;
use App\Models\User;

it('creates schedules broadcasts and marks notifications read', function (): void {
    $user = actingAsCentralUser([
        'notifications.view',
        'notifications.create',
        'notifications.update',
        'notifications.broadcast',
        'notifications.inbox',
    ]);

    $created = $this->postJson('/api/v1/notifications', [
        'title' => 'Platform update',
        'body' => 'We shipped Phase 6.',
        'channels' => [NotificationChannel::IN_APP->value, NotificationChannel::EMAIL->value],
        'target_user_ids' => [$user->id],
    ])->assertCreated()
        ->assertJsonPath('data.status', NotificationStatus::Draft->value);

    $id = $created->json('data.id');

    $this->postJson("/api/v1/notifications/{$id}/schedule", [
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertSuccessful()
        ->assertJsonPath('data.status', NotificationStatus::Scheduled->value);

    $this->postJson("/api/v1/notifications/{$id}/broadcast")
        ->assertSuccessful()
        ->assertJsonPath('data.status', NotificationStatus::Sent->value);

    $inbox = $this->getJson('/api/v1/notifications/inbox?unread=1')
        ->assertSuccessful();

    $deliveryId = $inbox->json('data.0.id');
    expect($deliveryId)->not->toBeNull();

    $this->postJson("/api/v1/notification-deliveries/{$deliveryId}/read")
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'read');

    $this->getJson("/api/v1/notifications/{$id}/history")
        ->assertSuccessful();
});

it('publishes and archives announcements with history', function (): void {
    actingAsCentralUser([
        'announcements.view',
        'announcements.create',
        'announcements.update',
        'announcements.publish',
        'announcements.delete',
    ]);

    $created = $this->postJson('/api/v1/announcements', [
        'title' => 'Maintenance window',
        'body' => 'API will be briefly unavailable.',
        'type' => AnnouncementType::MAINTENANCE->value,
        'target' => 'all_tenants',
        'regions' => ['us-east', 'eu-west'],
    ])->assertCreated();

    $id = $created->json('data.id');

    $this->postJson("/api/v1/announcements/{$id}/publish")
        ->assertSuccessful()
        ->assertJsonPath('data.status', AnnouncementStatus::Published->value);

    $this->postJson("/api/v1/announcements/{$id}/archive")
        ->assertSuccessful()
        ->assertJsonPath('data.status', AnnouncementStatus::Archived->value);

    $this->getJson("/api/v1/announcements/{$id}/history")
        ->assertSuccessful()
        ->assertJsonPath('status', true);
});

it('manages support tickets replies assignments and categories', function (): void {
    $agent = actingAsCentralUser([
        'support.tickets.view',
        'support.tickets.create',
        'support.tickets.update',
        'support.tickets.assign',
        'support.tickets.reply',
        'support.tickets.delete',
        'support.categories.manage',
    ]);

    $assignee = User::factory()->create();
    $tenant = Tenant::factory()->create();

    $category = $this->postJson('/api/v1/ticket-categories', [
        'name' => 'Billing',
        'description' => 'Billing related issues',
    ])->assertCreated()
        ->json('data');

    $ticket = $this->postJson('/api/v1/tickets', [
        'tenant_id' => $tenant->id,
        'ticket_category_id' => $category['id'],
        'subject' => 'Invoice mismatch',
        'description' => 'Customer reports wrong invoice total.',
        'priority' => TicketPriority::HIGH->value,
    ])->assertCreated()
        ->assertJsonPath('data.status', TicketStatus::OPEN->value);

    $ticketId = $ticket->json('data.id');

    $this->postJson("/api/v1/tickets/{$ticketId}/assign", [
        'assigned_to' => $assignee->id,
    ])->assertSuccessful()
        ->assertJsonPath('data.assignee.id', $assignee->id);

    $this->putJson("/api/v1/tickets/{$ticketId}/priority", [
        'priority' => TicketPriority::URGENT->value,
    ])->assertSuccessful()
        ->assertJsonPath('data.priority', TicketPriority::URGENT->value);

    $this->postJson("/api/v1/tickets/{$ticketId}/replies", [
        'body' => 'Looking into this now.',
        'is_internal' => true,
    ])->assertCreated()
        ->assertJsonPath('data.is_internal', true);

    $this->postJson("/api/v1/tickets/{$ticketId}/replies", [
        'body' => 'We fixed the invoice.',
    ])->assertCreated();

    $this->putJson("/api/v1/tickets/{$ticketId}/status", [
        'status' => TicketStatus::RESOLVED->value,
    ])->assertSuccessful()
        ->assertJsonPath('data.status', TicketStatus::RESOLVED->value);

    $this->getJson("/api/v1/tickets/{$ticketId}/history")
        ->assertSuccessful();

    $this->getJson('/api/v1/tickets?search=Invoice')
        ->assertSuccessful()
        ->assertJsonPath('status', true);

    expect($agent->id)->toBeInt();
});
