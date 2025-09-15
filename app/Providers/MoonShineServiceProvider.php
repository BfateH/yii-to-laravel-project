<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Acquiring\Resources\AcquirerConfigResource;
use App\Modules\Acquiring\Resources\AcquiringResource;
use App\Modules\OrderManagement\MoonShine\Resources\OrderResource;
use App\Modules\OrderManagement\MoonShine\Resources\PackageResource;
use App\MoonShine\Pages\ProfilePageCustom;
use App\MoonShine\Resources\MoonShineUserRoleResource;
use App\MoonShine\Resources\PartnerResource;
use App\MoonShine\Resources\UserResource;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

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
            ])
            ->pages([
                ...$config->getPages(),
                ProfilePageCustom::class,
            ])
        ;
    }
}
