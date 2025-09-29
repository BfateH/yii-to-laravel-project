<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\Modules\Acquiring\Resources\AcquirerConfigResource;
use App\Modules\Acquiring\Resources\AcquiringResource;
use App\Modules\OrderManagement\MoonShine\Resources\OrderResource;
use App\Modules\OrderManagement\MoonShine\Resources\PackageResource;
use App\MoonShine\Resources\shops\BrandResource;
use App\MoonShine\Resources\shops\ShopCategoryResource;
use App\MoonShine\Resources\shops\ShopResource;
use App\MoonShine\Resources\users\PartnerResource;
use App\MoonShine\Resources\users\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use MoonShine\AssetManager\InlineJs;
use MoonShine\AssetManager\Js;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\Laravel\Resources\MoonShineUserRoleResource;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use MoonShine\UI\Components\{Layout\Footer, Layout\Layout};
use App\MoonShine\Resources\CountryResource;
use App\MoonShine\Resources\TicketResource;
use App\MoonShine\Resources\AlertResource;
use App\MoonShine\Resources\ChannelResource;
use App\MoonShine\Resources\AlertLogResource;
use App\MoonShine\Resources\NotificationTemplateResource;
use App\MoonShine\Resources\SubscriptionResource;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        $currentUser = Auth::user();

        $assets = [
            ...parent::assets(),
            InlineJs::make(
            'window.MoonShineApp = window.MoonShineApp || {}; ' .
            'window.MoonShineApp.currentUserId = ' . (int)$currentUser->id . '; ' .
            'window.MoonShineApp.userRole = ' . $currentUser->role_id . ';'
        )];

        $assets = array_merge($assets, [
            Js::make(Vite::asset('resources/js/moonshine-echo.js'))->setAttribute('type', 'module'),
            Js::make(Vite::asset('resources/js/admin-echo-listener.js'))->setAttribute('type', 'module'),
            Js::make(Vite::asset('resources/js/webpush.js'))->setAttribute('type', 'module'),
        ]);

        return $assets;
    }

    protected function menu(): array
    {
        $currentUser = Auth::user();

        return [
            MenuGroup::make(static fn() => __('Аккаунты'), [
                MenuItem::make('Пользователи', UserResource::class)->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),
                MenuItem::make('Партнеры', PartnerResource::class)->canSee(fn() => $currentUser->isAdminRole()),
                MenuItem::make(static fn() => __('moonshine::ui.resource.role_title'), MoonShineUserRoleResource::class)->canSee(fn() => $currentUser->isAdminRole()),
                MenuGroup::make(static fn() => __('Уведомления'), [
                    MenuItem::make('Каналы', ChannelResource::class)->canSee(fn() => $currentUser->isAdminRole()),
                    MenuItem::make('Список уведомлений', AlertResource::class)->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),
                    MenuItem::make('Настройки шаблонов', NotificationTemplateResource::class)->canSee(fn() => $currentUser->isAdminRole()),
                ])->icon('bell-alert'),
            ])->icon('user-group'),

            MenuGroup::make(static fn() => __('Магазины'), [
                MenuItem::make(static fn() => __('Категории магазинов'), ShopCategoryResource::class)->icon('list-bullet'),
                MenuItem::make(static fn() => __('Бренды магазинов'), BrandResource::class)->icon('tag'),
                MenuItem::make(static fn() => __('Список магазинов'), ShopResource::class)->icon('shopping-bag'),
            ])->icon('shopping-bag')->canSee(fn() => $currentUser->isAdminRole()),

            MenuItem::make('Заказы', OrderResource::class)->icon('shopping-cart'),
            MenuItem::make('Посылки', PackageResource::class)->icon('cube'),
            MenuItem::make('Платежи', AcquiringResource::class)->icon('currency-dollar')->canSee(fn() => $currentUser->isAdminRole()),
            MenuItem::make('Настройки эквайринга', AcquirerConfigResource::class)->icon('cog-6-tooth')->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),
            MenuItem::make('Поддержка', TicketResource::class)->icon('heart'),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }

    protected function getFooterCopyright(): string
    {
        return '';
    }

    protected function getFooterComponent(): Footer
    {
        return parent::getFooterComponent()->menu([]);
    }

    public function build(): Layout
    {
        return parent::build();
    }
}
