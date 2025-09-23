<?php

namespace Tests\Feature\Modules\SupportChat;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Modules\SupportChat\Enums\TicketCategory;
use App\Modules\SupportChat\Enums\TicketStatus;
use App\Modules\SupportChat\Exceptions\TicketNotFoundException;
use App\Modules\SupportChat\Exceptions\UnauthorizedTicketAccessException;
use App\Modules\SupportChat\Repositories\TicketRepository;
use App\Modules\SupportChat\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SupportChatModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;
    protected Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        // Отключаем broadcasting для тестов
        Event::fake();
        config(['broadcasting.default' => 'null']);

        $this->user = User::factory()->create(['role_id' => Role::user]);
        $this->admin = User::factory()->create(['role_id' => Role::admin]);

        $this->ticket = Ticket::create([
            'user_id' => $this->user->id,
            'category' => TicketCategory::ORDER->value,
            'subject' => 'Тестовый тикет',
            'description' => 'Описание тестового тикета',
            'status' => TicketStatus::OPEN->value,
        ]);
    }

    // --- Тесты для MessageService ---

    public function test_message_service_can_send_message()
    {
        $messageService = app(MessageService::class);

        $messageData = [
            'message' => 'Тестовое сообщение',
        ];

        $message = $messageService->sendMessage($this->ticket, $messageData, $this->user);

        $this->assertInstanceOf(TicketMessage::class, $message);
        $this->assertEquals($this->ticket->id, $message->ticket_id);
        $this->assertEquals($this->user->id, $message->user_id);
        $this->assertEquals('Тестовое сообщение', $message->message);
        $this->assertDatabaseHas('ticket_messages', [
            'id' => $message->id,
            'message' => 'Тестовое сообщение',
        ]);
    }

    public function test_message_service_can_get_ticket_messages()
    {
        $messageService = app(MessageService::class);

        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'message' => 'Первое сообщение',
        ]);

        TicketMessage::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->user->id,
            'message' => 'Второе сообщение',
        ]);

        $messages = $messageService->getTicketMessages($this->ticket);

        $this->assertCount(2, $messages);
        $this->assertEquals('Первое сообщение', $messages->first()->message);
    }

    // --- Тесты для Repository ---

    public function test_ticket_repository_can_create_ticket()
    {
        $repository = app(TicketRepository::class);

        $ticketData = [
            'user_id' => $this->user->id,
            'category' => TicketCategory::GENERAL->value,
            'subject' => 'Тикет через репозиторий',
            'description' => 'Описание',
            'status' => TicketStatus::OPEN->value,
        ];

        $ticket = $repository->create($ticketData);

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertEquals('Тикет через репозиторий', $ticket->subject);
    }

    public function test_ticket_repository_can_find_by_user()
    {
        $repository = app(TicketRepository::class);

        $tickets = $repository->findByUser($this->user);

        $this->assertNotEmpty($tickets);
        $this->assertEquals($this->user->id, $tickets->first()->user_id);
    }

    public function test_ticket_repository_can_search_tickets()
    {
        $repository = app(TicketRepository::class);

        Ticket::create([
            'user_id' => $this->user->id,
            'category' => TicketCategory::GENERAL->value,
            'subject' => 'Уникальный поиск',
            'description' => 'Описание для поиска',
            'status' => TicketStatus::OPEN->value,
        ]);

        $results = $repository->search('Уникальный');

        $this->assertNotEmpty($results);
        $this->assertEquals('Уникальный поиск', $results->first()->subject);
    }

    // --- Тесты для Enums ---

    public function test_ticket_category_enum_works_correctly()
    {
        $this->assertEquals('По заказу', TicketCategory::ORDER->label());
        $this->assertEquals('Общий вопрос', TicketCategory::GENERAL->label());

        $options = TicketCategory::options();
        $this->assertArrayHasKey('order', $options);
        $this->assertArrayHasKey('general', $options);
    }

    public function test_ticket_status_enum_works_correctly()
    {
        $this->assertEquals('Открыт', TicketStatus::OPEN->label());
        $this->assertEquals('success', TicketStatus::OPEN->color());

        $this->assertTrue(TicketStatus::OPEN->isOpen());
        $this->assertTrue(TicketStatus::CLOSED->isClosed());

        $options = TicketStatus::options();
        $this->assertArrayHasKey('open', $options);
        $this->assertArrayHasKey('closed', $options);
    }


    // --- Тесты для Exceptions ---

    public function test_ticket_not_found_exception()
    {
        $this->expectException(TicketNotFoundException::class);

        throw new TicketNotFoundException(999);
    }

    public function test_unauthorized_access_exception()
    {
        $this->expectException(UnauthorizedTicketAccessException::class);

        throw new UnauthorizedTicketAccessException(123);
    }

    public function test_has_tickets_relation()
    {
        $user = $this->user;
        $this->assertTrue(method_exists($user, 'tickets'));
    }

    public function test_ticket_scopes_work()
    {
        Ticket::create([
            'user_id' => $this->user->id,
            'category' => TicketCategory::GENERAL->value,
            'subject' => 'Закрытый тикет',
            'description' => 'Описание',
            'status' => TicketStatus::CLOSED->value,
        ]);

        $openTickets = Ticket::open()->get();
        $closedTickets = Ticket::closed()->get();

        $this->assertCount(1, $openTickets);
        $this->assertCount(1, $closedTickets);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
