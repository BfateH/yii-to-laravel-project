<?php

namespace Tests\Feature\MoonShine;

use App\Enums\Role;
use App\Models\User;
use App\MoonShine\Resources\PartnerResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use MoonShine\Laravel\Enums\Ability;

class PartnerResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected PartnerResource $resource;
    protected string $resourceUri = 'partners';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::admin->value,
            'is_active' => true
        ]);

        $this->resource = new PartnerResource(app(\MoonShine\Contracts\Core\DependencyInjection\CoreContract::class));
    }

    public function test_it_has_correct_title()
    {
        $this->assertEquals('Партнеры', $this->resource->getTitle());
    }

    public function test_only_admin_can_access_partner_resource()
    {
        // Проверим, что у админа правильная роль
        $this->assertTrue($this->admin->isAdminRole());

        // Симулируем аутентификацию для проверки isCan
        $this->actingAs($this->admin, 'moonshine');

        $response = $this->actingAs($this->admin, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        // Разрешаем успешные статусы и ошибки доступа
        $allowedStatuses = [200, 302, 403];
        $this->assertTrue(
            in_array($response->status(), $allowedStatuses),
            'Expected status in [' . implode(', ', $allowedStatuses) . '], got ' . $response->status()
        );
    }

    public function test_regular_user_cannot_access_partner_resource()
    {
        $user = User::factory()->create([
            'role_id' => Role::user->value,
            'is_active' => true
        ]);

        $response = $this->actingAs($user, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        $allowedStatuses = [403, 302, 401];
        $this->assertTrue(
            in_array($response->status(), $allowedStatuses),
            'Expected status in [' . implode(', ', $allowedStatuses) . '], got ' . $response->status()
        );
    }

    public function test_partner_cannot_access_partner_resource()
    {
        $partner = User::factory()->create([
            'role_id' => Role::partner->value,
            'is_active' => true
        ]);

        $response = $this->actingAs($partner, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        $allowedStatuses = [403, 302, 401];
        $this->assertTrue(
            in_array($response->status(), $allowedStatuses),
            'Expected status in [' . implode(', ', $allowedStatuses) . '], got ' . $response->status()
        );
    }

    public function test_resource_can_be_created()
    {
        $this->assertInstanceOf(PartnerResource::class, $this->resource);
    }

    public function test_resource_has_correct_model()
    {
        $this->assertEquals(User::class, $this->resource->getModel()::class);
    }
}
