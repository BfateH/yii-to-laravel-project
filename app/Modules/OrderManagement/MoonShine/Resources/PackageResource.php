<?php

declare(strict_types=1);

namespace App\Modules\OrderManagement\MoonShine\Resources;

use App\Enums\Role;
use App\Models\User;
use App\Modules\OrderManagement\Enums\PackageStatus;
use App\Modules\OrderManagement\Models\Package;
use App\Modules\OrderManagement\MoonShine\Pages\Orders\OrderStatuses;
use App\Modules\Tracking\Services\TrackingService;
use App\MoonShine\Resources\TrackingEventResource;
use App\MoonShine\Resources\users\CommonUserResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends ModelResource<Package>
 */
class PackageResource extends ModelResource
{
    protected string $model = Package::class;

    protected string $title = 'Посылки';

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user?->email,
                resource: CommonUserResource::class
            )->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value, Role::user->value])),
            Enum::make('Статус', 'status')->attach(PackageStatus::class)->sortable(),
            Text::make('Трек номер', 'tracking_number')
        ];
    }

    protected function filters(): iterable
    {
        return [
            Enum::make('Статус', 'status')->attach(PackageStatus::class)->nullable()
        ];
    }

    protected function search(): array
    {
        return [
            'id',
            'user.name',
            'user.email',
            'tracking_number',
        ];
    }


    /**
     * @return FieldContract
     */
    protected function formFields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Enum::make('Статус', 'status')->attach(PackageStatus::class),
                Text::make('Трек номер', 'tracking_number'),
                BelongsTo::make(
                    __('Пользователь'),
                    'user',
                    formatted: fn(User $user) => $user ? ($user->name . ' (' . $user->email . ')') : null,
                    resource: CommonUserResource::class
                )
                    ->asyncSearch('name')
                    ->asyncOnInit()
                    ->valuesQuery(fn(Builder $query) => $query->where('role_id', [Role::partner->value, Role::user->value]))
                    ->placeholder(__('Выберите пользователя'))
                    ->nullable()
                    ->default(null)
            ])
        ];
    }

    protected function pages(): array
    {
        $pages = [
            ...parent::pages(),
        ];

        if(Auth::user()->isAdminRole()) {
            $pages[] = OrderStatuses::class;
        }

        return $pages;
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            BelongsTo::make(
                __('Пользователь'),
                'user',
                formatted: fn(User $user) => $user?->email,
                resource: CommonUserResource::class
            )->valuesQuery(fn(Builder $query) => $query->whereIn('role_id', [Role::partner->value, Role::user->value])),
            Enum::make('Статус', 'status')->attach(PackageStatus::class),
            Text::make('Трек номер', 'tracking_number'),
            HasMany::make(
                'События посылки',
                'trackingEvents',
                resource: TrackingEventResource::class
            )
                ->searchable(false),
        ];
    }

    protected function topButtons(): ListOf
    {
        $buttons = parent::topButtons();

        if(Auth::user()->isAdminRole()) {
            $buttons->add(
                ActionButton::make('Настройка статусов',
                    url: fn($model) => $this->getPageUrl(OrderStatuses::class),
                ),
            );
        }

        return $buttons;
    }

    protected function detailButtons(): ListOf
    {
        return parent::detailButtons()->prepend(
            ActionButton::make('Обновить сейчас')
                ->method('getTrackingInfo', ['updatePage' => true])
                ->icon('arrow-right')
                ->info()
                ->canSee(fn() => $this->getItem()->tracking_number)
        );
    }

    protected function indexButtons(): ListOf
    {
        return parent::indexButtons()->prepend(
            ActionButton::make('Обновить сейчас')
                ->method('getTrackingInfo')
                ->icon('arrow-right')
                ->info()
                ->canSee(fn(Package $package) => $package->tracking_number)
        );
    }

    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        $currentUser = Auth::user();

        if ($currentUser->isPartnerRole()) {
            $builder->where('user_id', $currentUser->id)
                ->orWhereHas('user', function ($query) use ($currentUser) {
                    $query->where('partner_id', $currentUser->id);
                });
        }

        if ($currentUser->isDefaultUserRole()) {
            $builder->where('user_id', $currentUser->id);
        }

        return $builder;
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $userItem = $item->user;
        $currentUser = Auth::user();

        if($currentUser->isPartnerRole()) {
            if($userItem && $userItem->partner_id === $currentUser->id) {
                return $item;
            }

            if($item->user_id === $currentUser->id) {
                return $item;
            }

            if ($orFail) {
                abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
            }

            return null;
        }

        if($currentUser->isDefaultUserRole()) {
            if($item->user_id === $currentUser->id) {
                return $item;
            }

            if ($orFail) {
                abort(404, 'Сущность не найдена или не разрешена для этого ресурса.');
            }

            return null;
        }

        return $item;
    }

    /**
     * @param Package $item
     *
     * @return array<string, string[]|string>
     * @see https://laravel.com/docs/validation#available-validation-rules
     */
    protected function rules(mixed $item): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(PackageStatus::class),
            ],
            'user_id' => [
                'nullable',
                Rule::exists(User::class, 'id'),
            ],
            'tracking_number' => [
                'nullable',
                'string',
                Rule::unique('packages', 'tracking_number')->ignore($item?->id),
            ]
        ];
    }

    public function getTrackingInfo(MoonShineRequest $request): MoonShineJsonResponse
    {
        $itemId = $request->getResource()->getItemID();

        if (!$itemId) {
            return MoonShineJsonResponse::make()->toast(__('Сущность не найдена'), ToastType::ERROR);
        }

        $item = $request->getResource()->getModel()->findOrFail($itemId);
        $tableEventName = "index-table-" . $request->getResource()->getUriKey();
        $rowEventName = $tableEventName . "-{$item->id}";

        $trackingService = app(TrackingService::class);

        try {
            if(is_null($item->tracking_number)) {
                throw new \Exception('Нет трек номера');
            }

            $trackingService->trackPackage($item);
        } catch (\Exception $e) {
            return MoonShineJsonResponse::make()
                ->toast('Ошибка при обнолвении статуса посылки', ToastType::ERROR, 5000)
                ->events([AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $rowEventName)]);
        }

        if(request()->has('updatePage')) {
            return MoonShineJsonResponse::make()
                ->toast(__('Успешно обновлено'), ToastType::SUCCESS)
                ->redirect($this->getDetailPageUrl($item->id));
        }

        return MoonShineJsonResponse::make()
            ->toast(__('Успешно обновлено'), ToastType::SUCCESS)
            ->events([AlpineJs::event(JsEvent::TABLE_ROW_UPDATED, $rowEventName)]);
    }
}
