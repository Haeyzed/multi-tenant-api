<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\Support;

use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Support\AssignTicketRequest;
use App\Http\Requests\Central\Support\ReplyTicketRequest;
use App\Http\Requests\Central\Support\StoreTicketCategoryRequest;
use App\Http\Requests\Central\Support\StoreTicketRequest;
use App\Http\Requests\Central\Support\UpdateTicketPriorityRequest;
use App\Http\Requests\Central\Support\UpdateTicketRequest;
use App\Http\Requests\Central\Support\UpdateTicketStatusRequest;
use App\Http\Resources\Central\TicketCategoryResource;
use App\Http\Resources\Central\TicketReplyResource;
use App\Http\Resources\Central\TicketResource;
use App\Models\Central\Ticket;
use App\Models\User;
use App\Services\Central\Support\TicketService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Central Support', description: 'Support tickets and categories.', weight: 200)]
final class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    #[Endpoint(operationId: 'support.ticket.index', title: 'List tickets', description: 'Return a paginated list of tickets.')]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewSupportTickets');

        $tickets = $this->ticketService->paginate($request->only([
            'search', 'status', 'priority', 'tenant_id', 'assigned_to', 'category_id', 'per_page',
        ]));

        return $this->paginated(TicketResource::collection($tickets), 'Tickets retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.store', title: 'Create ticket', description: 'Create a new ticket and return it.')]
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated(), $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket created successfully.', 201);
    }

    #[Endpoint(operationId: 'support.ticket.show', title: 'Show ticket', description: 'Return a single ticket by ID.')]
    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('viewSupportTickets');
        $ticket->load(['category', 'assignee', 'creator', 'tenant', 'replies.author']);

        return $this->success(new TicketResource($ticket), 'Ticket retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.update', title: 'Update ticket', description: 'Update an existing ticket and return it.')]
    public function update(UpdateTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->ticketService->update($ticket, $request->validated(), $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.assign', title: 'Assign ticket', description: 'Assign a support ticket to a staff user.')]
    public function assign(AssignTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validated();

        $assignee = User::query()->findOrFail($data['assigned_to']);
        $ticket = $this->ticketService->assign($ticket, $assignee, $request->user());

        return $this->success(new TicketResource($ticket), 'Ticket assigned successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.updateStatus', title: 'Update status', description: 'Change lifecycle/status for the resource.')]
    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validated();

        $ticket = $this->ticketService->updateStatus(
            $ticket,
            $data['status'] instanceof TicketStatus ? $data['status'] : TicketStatus::from($data['status']),
            $request->user(),
        );

        return $this->success(new TicketResource($ticket), 'Ticket status updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.updatePriority', title: 'Update priority', description: 'Change ticket priority and SLA target.')]
    public function updatePriority(UpdateTicketPriorityRequest $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validated();

        $ticket = $this->ticketService->updatePriority(
            $ticket,
            $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']),
            $request->user(),
        );

        return $this->success(new TicketResource($ticket), 'Ticket priority updated successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.reply', title: 'Reply to ticket', description: 'Add a public reply or internal note, optionally with attachment.')]
    public function reply(ReplyTicketRequest $request, Ticket $ticket): JsonResponse
    {
        $reply = $this->ticketService->reply(
            $ticket,
            $request->validated(),
            $request->user(),
            $request->file('attachment'),
        );

        return $this->success(new TicketReplyResource($reply), 'Reply added successfully.', 201);
    }

    #[Endpoint(operationId: 'support.ticket.history', title: 'ticket history', description: 'Paginate history events for this ticket.')]
    public function history(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('viewSupportTickets');

        $history = $this->ticketService->history($ticket, (int) $request->integer('per_page', 25));

        return $this->paginated($history, 'Ticket history retrieved successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.destroy', title: 'Delete ticket', description: 'Soft-delete or permanently remove a ticket.')]
    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('deleteSupportTickets');
        $this->ticketService->delete($ticket);

        return $this->success(null, 'Ticket deleted successfully.');
    }

    #[Endpoint(operationId: 'support.ticket.categories', title: 'List categories', description: 'List categories for this resource.')]
    public function categories(Request $request): JsonResponse
    {
        $this->authorize('viewSupportTickets');

        return $this->success(
            TicketCategoryResource::collection($this->ticketService->categories()),
            'Ticket categories retrieved successfully.',
        );
    }

    #[Endpoint(operationId: 'support.ticket.storeCategory', title: 'Create category', description: 'Create a new category.')]
    public function storeCategory(StoreTicketCategoryRequest $request): JsonResponse
    {
        $category = $this->ticketService->createCategory($request->validated());

        return $this->success(new TicketCategoryResource($category), 'Ticket category created successfully.', 201);
    }
}
