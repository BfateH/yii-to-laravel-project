<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Acquiring\Resources\AcquirerConfigResource;
use App\Modules\Acquiring\Resources\AcquiringResource;
use App\Modules\OrderManagement\MoonShine\Resources\OrderResource;
use App\Modules\OrderManagement\MoonShine\Resources\PackageResource;
use App\MoonShine\Pages\ProfilePageCustom;
use App\MoonShine\Resources\shops\BrandResource;
use App\MoonShine\Resources\shops\ShopCategoryResource;
use App\MoonShine\Resources\shops\ShopResource;
use App\MoonShine\Resources\TrackingEventResource;
use App\MoonShine\Resources\users\CommonUserResource;
use App\MoonShine\Resources\users\MoonShineUserRoleResource;
use App\MoonShine\Resources\users\PartnerResource;
use App\MoonShine\Resources\users\UserResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;
use App\MoonShine\Resources\CountryResource;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  MoonShine  $core
     * @param  MoonShineConfigurator  $config
     *
     */
    public function boot(CoreContract $core, ConfiguratorContract $config): void
    {
        $core
            ->resources([
                MoonShineUserRoleResource::class,
                OrderResource::class,
                PackageResource::class,
                PartnerResource::class,
                UserResource::class,
                AcquiringResource::class,
                AcquirerConfigResource::class,
                CommonUserResource::class,
                TrackingEventResource::class,
                ShopResource::class,
                ShopCategoryResource::class,
                BrandResource::class,
                CountryResource::class,
            ])
            ->pages([
                ...$config->getPages(),
                ProfilePageCustom::class,
            ])
        ;
    }
}
