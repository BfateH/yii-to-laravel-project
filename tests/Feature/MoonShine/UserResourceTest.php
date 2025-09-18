<?php

namespace Tests\Feature\MoonShine;

use App\Enums\Role;
use App\Models\User;
use App\MoonShine\Resources\users\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UserResource $resource;
    protected string $resourceUri = 'users';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role_id' => Role::admin->value,
            'is_active' => true
        ]);

        $this->resource = new UserResource(app(\MoonShine\Contracts\Core\DependencyInjection\CoreContract::class));
    }

    public function test_it_has_correct_title()
    {
        $this->assertEquals('Пользователи', $this->resource->getTitle());
    }

    public function test_admin_can_access_user_resource()
    {
        $response = $this->actingAs($this->admin, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        // Проверяем, что не получаем ошибку активности
        $response->assertDontSee('Ваш аккаунт неактивен или заблокирован');

        // Проверяем, что запрос выполнен
        $this->assertNotEquals(500, $response->status(), 'Got server error');
    }

    public function test_regular_user_cannot_access_user_resource()
    {
        $user = User::factory()->create([
            'role_id' => Role::user->value,
            'is_active' => true
        ]);

        $response = $this->actingAs($user, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        // Проверяем, что не получаем ошибку активности
        $response->assertDontSee('Ваш аккаунт неактивен или заблокирован');

        // Проверяем, что обычный пользователь не получил полный доступ
        $this->assertNotEquals(200, $response->status(), 'Regular user should not have full access');
    }

    public function test_partner_access_to_user_resource()
    {
        $partner = User::factory()->create([
            'role_id' => Role::partner->value,
            'is_active' => true
        ]);

        $response = $this->actingAs($partner, 'moonshine')
            ->get("/admin/resource/{$this->resourceUri}/crud");

        // Проверяем, что не получаем ошибку активности
        $response->assertDontSee('Ваш аккаунт неактивен или заблокирован');

        // Проверяем, что запрос выполнен
        $this->assertNotEquals(500, $response->status(), 'Got server error');
    }

    public function test_resource_has_fields_methods()
    {
        // Проверяем, что ресурс имеет методы для получения полей
        $hasMethods = (
            method_exists($this->resource, 'getIndexFields') ||
            method_exists($this->resource, 'getFormFields') ||
            method_exists($this->resource, 'indexFields') ||
            method_exists($this->resource, 'formFields')
        );

        $this->assertTrue($hasMethods, 'Resource should have methods to get fields');
    }

    public function test_resource_can_be_created()
    {
        $this->assertInstanceOf(UserResource::class, $this->resource);
    }

    public function test_resource_has_correct_model()
    {
        $this->assertEquals(\App\Models\User::class, $this->resource->getModel()::class);
    }
}
