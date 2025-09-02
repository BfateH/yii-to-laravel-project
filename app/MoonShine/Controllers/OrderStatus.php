<?php

declare(strict_types=1);

namespace App\MoonShine\Controllers;

use App\Http\Requests\Moonshine\Order\OrderStatusRequest;
use App\Modules\OrderManagement\Models\OrderPackageStatusMapping;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Http\Controllers\MoonShineController;
use MoonShine\Support\Enums\ToastType;
use Symfony\Component\HttpFoundation\Response;

final class OrderStatus extends MoonShineController
{
    public function store(OrderStatusRequest $request): Response
    {
        $data = $request->validated();
        OrderPackageStatusMapping::query()->delete();

        $elem = 0;
        foreach (\App\Modules\OrderManagement\Enums\OrderStatus::getAll() as $status_id => $status) {

            OrderPackageStatusMapping::query()->create([
                'internal_status' => $status_id,
                'external_status' => $data['packageStatus'][$elem],
            ]);

            $elem++;
        }

        $this->toast('Успешно сохранено', ToastType::SUCCESS);
        return back();
    }
}
