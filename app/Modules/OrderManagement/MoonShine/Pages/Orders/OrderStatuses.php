<?php

declare(strict_types=1);

namespace App\Modules\OrderManagement\MoonShine\Pages\Orders;

use App\Modules\OrderManagement\Enums\OrderStatus;
use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\OrderPackageStatusMapping;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Enum;



class OrderStatuses extends Page
{
    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ...parent::getBreadcrumbs(),
            '#' => $this->getTitle()
        ];
    }

    public function getTitle(): string
    {
        return $this->title ?: 'Настройка статусов';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
	{
        $items = $this->getItems();

		return [
            FormBuilder::make(
                action: route('admin.orders.statuses.store'),
                method: FormMethod::POST,
                fields: [
                    TableBuilder::make()
                        ->items($items)
                        ->fields([
                            Enum::make('Order Статус', 'orderStatus[]')
                                ->attach(OrderStatus::class)->disabled(),
                            Enum::make('Package Статус', 'packageStatus[]')
                                ->attach(PackageStatus::class)->nullable()
                        ])->editable()
                ]
            )->name('order-statuses-form')
        ];
	}

    private function getItems(): array
    {
        $items = [];
        $statuses = OrderStatus::getAll();

        foreach ($statuses as $status_id => $status) {
            $mappingData = OrderPackageStatusMapping::query()->where('internal_status', $status_id)->first();

            $items[] = [
                "orderStatus[]" => $status_id,
                "packageStatus[]" => $mappingData ? $mappingData->external_status : null,
            ];
        }

        return $items;
    }
}
