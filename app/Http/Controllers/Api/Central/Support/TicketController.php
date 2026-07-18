<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Support;

use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\TicketCategoryResource;
use App\Http\Resources\Central\TicketReplyResource;
use App\Http\Resources\Central\TicketResource;
use App\Models\Central\Ticket;
use App\Models\User;
use App\Services\Central\Support\TicketService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Central Support', description: 'Support tickets and categories.', weight: 200)]
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    )
    {
    }

    #[Endpoint(operationId: 'support.ticket.index', title: 'List tickets', description: 'Return a paginated list of tickets.')]
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.view'), 403);

        $tickets = $this->ticketService->paginate($request->only([
            'search', 'status', 'priority', 'tenant_id', 'assigned_to', 'category_id', 'per_page',
        ]));

        return $this->paginated(TicketResource::collection($tickets), 'Tickets retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.store', title: 'Create ticket', description: 'Create a new ticket and return it.')]
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.create'), 403);

        $data = $request->validate([
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
            'ticket_category_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_categories,id'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $ticket = $this->ticketService->create($data, $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket created successfully.', 201);
    }

    #[Endpoint(operationId: 'support.ticket.show', title: 'Show ticket', description: 'Return a single ticket by ID.')]
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.view'), 403);
        $ticket->load(['category', 'assignee', 'creator', 'tenant', 'replies.author']);

        return $this->success(new TicketResource($ticket), 'Ticket retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.update', title: 'Update ticket', description: 'Update an existing ticket and return it.')]
    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.update'), 403);

        $data = $request->validate([
            'subject' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'ticket_category_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_categories,id'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $ticket = $this->ticketService->update($ticket, $data, $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.assign', title: 'Assign ticket', description: 'Assign a support ticket to a staff user.')]
    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.assign'), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $assignee = User::query()->findOrFail($data['assigned_to']);
        $ticket = $this->ticketService->assign($ticket, $assignee, $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket assigned successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.updateStatus', title: 'Update status', description: 'Change lifecycle/status for the resource.')]
    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.update'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ]);

        $ticket = $this->ticketService->updateStatus(
            $ticket,
            $data['status'] instanceof TicketStatus ? $data['status'] : TicketStatus::from($data['status']),
            $request->user(),
        );

        return $this->success(new TicketResource($ticket), 'Ticket status updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.updatePriority', title: 'Update priority', description: 'Change ticket priority and SLA target.')]
    public function updatePriority(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.update'), 403);

        $data = $request->validate([
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ]);

        $ticket = $this->ticketService->updatePriority(
            $ticket,
            $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
            $request->user(),
        );

        return $this->success(new TicketResource($ticket), 'Ticket priority updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.reply', title: 'Reply to ticket', description: 'Add a public reply or internal note, optionally with attachment.')]
    public function reply(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.reply'), 403);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['sometimes', 'boolean'],
            'attachment' => ['sometimes', 'file', 'max:10240'],
        ]);

        $reply = $this->ticketService->reply(
            $ticket,
            $data,
            $request->user(),
            $request->file('attachment'),
        );

        return $this->success(new TicketReplyResource($reply), 'Reply added successfully.', 201);
    }

    #[Endpoint(operationId: 'support.ticket.history', title: 'ticket history', description: 'Paginate history events for this ticket.')]
    public function history(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.view'), 403);

        $history = $this->ticketService->history($ticket, (int)$request->integer('per_page', 25));

        return $this->paginated($history, 'Ticket history retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.destroy', title: 'Delete ticket', description: 'Soft-delete or permanently remove a ticket.')]
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.delete'), 403);
        $this->ticketService->delete($ticket);

        return $this->success(null, 'Ticket deleted successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.categories', title: 'List categories', description: 'List categories for this resource.')]
    public function categories(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('support.tickets.view'), 403);

        return $this->success(
            TicketCategoryResource::collection($this->ticketService->categories()),
            'Ticket categories retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'support.ticket.storeCategory', title: 'Create category', description: 'Create a new category.')]
    public function storeCategory(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('support.categories.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'alpha_dash', 'unique:ticket_categories,slug'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $category = $this->ticketService->createCategory($data);

        return $this->success(new TicketCategoryResource($category), 'Ticket category created successfully.', 201);
    }
}

