<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\Modules\Acquiring\Resources\AcquirerConfigResource;
use App\Modules\Acquiring\Resources\AcquiringResource;
use App\Modules\OrderManagement\MoonShine\Resources\OrderResource;
use App\Modules\OrderManagement\MoonShine\Resources\PackageResource;
use App\MoonShine\Resources\PartnerResource;
use App\MoonShine\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\Laravel\Resources\MoonShineUserRoleResource;
use MoonShine\MenuManager\MenuItem;
use MoonShine\UI\Components\{Layout\Footer, Layout\Layout};
use App\MoonShine\Resources\TrackingEventResource;

final class MoonShineLayout extends AppLayout
{
    protected function assets(): array
    {
        return [
            ...parent::assets(),
        ];
    }

    protected function menu(): array
    {
        $currentUser = Auth::user();

        return [
            MenuItem::make('Пользователи', UserResource::class)->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),
            MenuItem::make('Партнеры', PartnerResource::class)->canSee(fn() => $currentUser->isAdminRole()),
            MenuItem::make(
                static fn() => __('moonshine::ui.resource.role_title'),
                MoonShineUserRoleResource::class
            )->canSee(fn() => $currentUser->isAdminRole()),
            MenuItem::make('Заказы', OrderResource::class)->icon('shopping-cart'),
            MenuItem::make('Посылки', PackageResource::class)->icon('cube'),
            MenuItem::make('Платежи', AcquiringResource::class)->icon('currency-dollar')->canSee(fn() => $currentUser->isAdminRole()),
            MenuItem::make('Настройки эквайринга', AcquirerConfigResource::class)->icon('cog-6-tooth')->canSee(fn() => $currentUser->isAdminRole() || $currentUser->isPartnerRole()),
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
