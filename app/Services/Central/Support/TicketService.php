<?php

declare(strict_types=1);

namespace App\Services\Central\Support;

use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Models\Central\Ticket;
use App\Models\Central\TicketCategory;
use App\Models\Central\TicketHistory;
use App\Models\Central\TicketReply;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Service responsible for central support ticket management.
 *
 * Encapsulates ticket CRUD, assignment, status/priority transitions,
 * replies, categories, and history tracking so controllers remain thin.
 */
final class TicketService
{
    /**
     * Assign a ticket to a platform user.
     *
     * @param Ticket $ticket
     * @param User $assignee
     * @param User|null $actor
     * @return Ticket
     */
    public function assign(Ticket $ticket, User $assignee, ?User $actor = null): Ticket
    {
        $ticket->update(['assigned_to' => $assignee->id]);
        $this->recordHistory($ticket, 'assigned', $actor, ['assigned_to' => $assignee->id]);

        return $ticket->refresh()->load('assignee');
    }

    /**
     * Update ticket attributes and record the change in history.
     *
     * @param Ticket $ticket
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return Ticket
     */
    public function update(Ticket $ticket, array $data, ?User $actor = null): Ticket
    {
        $ticket->update($data);
        $this->recordHistory($ticket, 'updated', $actor, $data);

        return $ticket->refresh()->load(['category', 'assignee', 'creator', 'tenant']);
    }

    /**
     * Record a ticket history entry for audit purposes.
     *
     * @param Ticket $ticket
     * @param string $action
     * @param User|null $actor
     * @param array<string, mixed>|null $properties
     * @return void
     */
    private function recordHistory(Ticket $ticket, string $action, ?User $actor, ?array $properties = null): void
    {
        TicketHistory::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'properties' => $properties,
        ]);
    }

    /**
     * Create a new support ticket.
     *
     * @param array<string, mixed> $data
     * @param User|null $actor
     * @return Ticket
     *
     * @throws Throwable
     */
    public function create(array $data, ?User $actor = null): Ticket
    {
        return DB::transaction(function () use ($data, $actor): Ticket {
            $ticket = Ticket::query()->create([
                'number' => $this->uniqueNumber(),
                'tenant_id' => $data['tenant_id'] ?? null,
                'ticket_category_id' => $data['ticket_category_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => $data['status'] ?? TicketStatus::OPEN->value,
                'priority' => $data['priority'] ?? TicketPriority::MEDIUM->value,
                'created_by' => $actor?->id,
                'assigned_to' => $data['assigned_to'] ?? null,
                'metadata' => $data['metadata'] ?? [],
            ]);

            $this->recordHistory($ticket, 'created', $actor);

            if (!empty($data['assigned_to'])) {
                $this->recordHistory($ticket, 'assigned', $actor, ['assigned_to' => $data['assigned_to']]);
            }

            return $ticket->load(['category', 'assignee', 'creator', 'tenant']);
        });
    }

    /**
     * Generate a unique ticket number identifier.
     *
     * @return string
     */
    private function uniqueNumber(): string
    {
        do {
            $number = 'TCK-' . strtoupper(Str::random(8));
        } while (Ticket::query()->where('number', $number)->exists());

        return $number;
    }

    /**
     * Update a ticket's status and set resolution/closure timestamps.
     *
     * @param Ticket $ticket
     * @param TicketStatus $status
     * @param User|null $actor
     * @return Ticket
     */
    public function updateStatus(Ticket $ticket, TicketStatus $status, ?User $actor = null): Ticket
    {
        $attributes = ['status' => $status];

        if ($status === TicketStatus::RESOLVED) {
            $attributes['resolved_at'] = now();
        }

        if ($status === TicketStatus::CLOSED) {
            $attributes['closed_at'] = now();
            $attributes['resolved_at'] = $ticket->resolved_at ?? now();
        }

        $ticket->update($attributes);
        $this->recordHistory($ticket, 'status_changed', $actor, ['status' => $status->value]);

        return $ticket->refresh();
    }

    /**
     * Update a ticket's priority level.
     *
     * @param Ticket $ticket
     * @param TicketPriority $priority
     * @param User|null $actor
     * @return Ticket
     */
    public function updatePriority(Ticket $ticket, TicketPriority $priority, ?User $actor = null): Ticket
    {
        $ticket->update(['priority' => $priority]);
        $this->recordHistory($ticket, 'priority_changed', $actor, ['priority' => $priority->value]);

        return $ticket->refresh();
    }

    /**
     * Add a reply or internal note to a ticket.
     *
     * Transitions open tickets to pending on public replies and supports
     * optional file attachments.
     *
     * @param Ticket $ticket
     * @param array{body: string, is_internal?: bool} $data
     * @param User|null $actor
     * @param UploadedFile|null $attachment
     * @return TicketReply
     *
     * @throws ValidationException
     * @throws Throwable
     */
    public function reply(Ticket $ticket, array $data, ?User $actor = null, ?UploadedFile $attachment = null): TicketReply
    {
        if (in_array($ticket->status, [TicketStatus::CLOSED], true)) {
            throw ValidationException::withMessages([
                'ticket' => ['Closed tickets cannot receive replies.'],
            ]);
        }

        return DB::transaction(function () use ($ticket, $data, $actor, $attachment): TicketReply {
            $reply = TicketReply::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor?->id,
                'body' => $data['body'],
                'is_internal' => (bool)($data['is_internal'] ?? false),
            ]);

            if ($attachment) {
                $reply->addMedia($attachment)->toMediaCollection('attachments');
            }

            if ($ticket->status === TicketStatus::OPEN && !($data['is_internal'] ?? false)) {
                $ticket->update(['status' => TicketStatus::PENDING]);
            }

            $this->recordHistory($ticket, ($data['is_internal'] ?? false) ? 'internal_note' : 'replied', $actor, [
                'reply_id' => $reply->id,
            ]);

            return $reply->load('author');
        });
    }

    /**
     * Paginate history entries for a ticket.
     *
     * @param Ticket $ticket
     * @param int $perPage
     * @return LengthAwarePaginator<int, TicketHistory>
     */
    public function history(Ticket $ticket, int $perPage = 25): LengthAwarePaginator
    {
        return $ticket->histories()
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(min($perPage, 100));
    }

    /**
     * Paginate support tickets with optional filters.
     *
     * @param array{search?: string, status?: string, priority?: string, tenant_id?: string, assigned_to?: int, category_id?: int, per_page?: int} $filters
     * @return LengthAwarePaginator<int, Ticket>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = min((int)($filters['per_page'] ?? 15), 100);

        return Ticket::query()
            ->with(['category', 'assignee:id,name,email', 'creator:id,name,email', 'tenant:id,name,slug'])
            ->when(
                $filters['search'] ?? null,
                fn($query, string $search) => $query->where(function ($q) use ($search): void {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
            )
            ->when($filters['status'] ?? null, fn($q, string $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn($q, string $priority) => $q->where('priority', $priority))
            ->when($filters['tenant_id'] ?? null, fn($q, string $tenantId) => $q->where('tenant_id', $tenantId))
            ->when($filters['assigned_to'] ?? null, fn($q, $assignedTo) => $q->where('assigned_to', $assignedTo))
            ->when($filters['category_id'] ?? null, fn($q, $categoryId) => $q->where('ticket_category_id', $categoryId))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * List all ticket categories ordered by sort order and name.
     *
     * @return Collection<int, TicketCategory>
     */
    public function categories(): Collection
    {
        return TicketCategory::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Create a new ticket category.
     *
     * @param array<string, mixed> $data
     * @return TicketCategory
     */
    public function createCategory(array $data): TicketCategory
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);

        return TicketCategory::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Delete a support ticket.
     *
     * @param Ticket $ticket
     * @return void
     */
    public function delete(Ticket $ticket): void
    {
        $ticket->delete();
    }
}
