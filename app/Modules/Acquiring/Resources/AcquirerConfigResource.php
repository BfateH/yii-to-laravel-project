<?php

namespace App\Modules\Acquiring\Resources;

use App\Enums\Role;
use App\Models\AcquirerConfig;
use App\Models\User;
use App\Modules\Acquiring\Enums\AcquirerType;
use App\MoonShine\Resources\PartnerResource;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Laravel\Enums\Ability;
use MoonShine\Laravel\Enums\Action;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

class AcquirerConfigResource extends ModelResource
{
    protected string $model = AcquirerConfig::class;

    public function getTitle(): string
    {
        return __('Настройки эквайринга');
    }

    public function isCan(Ability $ability): bool
    {
        $user = Auth::user();

        if (!$user->isAdminRole() && !$user->isPartnerRole()) {
            return false;
        }

        return parent::isCan($ability);
    }

    protected function activeActions(): ListOf
    {
        return parent::activeActions()->except(Action::MASS_DELETE, Action::VIEW);
    }

    protected function modifyQueryBuilder(\Illuminate\Contracts\Database\Eloquent\Builder $builder): \Illuminate\Contracts\Database\Eloquent\Builder
    {
        $builder = parent::modifyQueryBuilder($builder);

        $currentUser = Auth::user();
        if ($currentUser->isPartnerRole()) {
            $builder->where('user_id', $currentUser->id);
        }

        return $builder;
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make(
                'Партнер',
                'user',
                formatted: static fn(User $user) => $user->name ?? '-',
                resource: PartnerResource::class
            )
                ->sortable()
                ->valuesQuery(fn(Builder $query) => $query->where('role_id', Role::partner->value))
                ->canSee(fn() => Auth::user()->isAdminRole()),

            Enum::make('Тип эквайринга', 'type')->attach(AcquirerType::class)->sortable(),
            Switcher::make('Активна', 'is_active')->sortable(),
            Date::make('Создан', 'created_at'),
        ];
    }

    protected function detailFields(): iterable
    {
        return [
            ID::make(),

            BelongsTo::make(
                'Партнер',
                'user',
                formatted: static fn(User $user) => $user->name ?? '',
                resource: PartnerResource::class
            )
                ->valuesQuery(fn(Builder $query) => $query->where('role_id', Role::partner->value))
                ->canSee(fn() => Auth::user()->isAdminRole()),

            Enum::make('Тип эквайринга', 'type')->attach(AcquirerType::class),
            Switcher::make('Активна', 'is_active'),
            Json::make('Учетные данные (зашифрованы)', 'encrypted_credentials'),
            Date::make('Создан', 'created_at'),
            Date::make('Обновлен', 'updated_at'),
        ];
    }

    protected function formFields(): iterable
    {
        $item = $this->getItem();
        $currentUser = Auth::user();
        $acquirerConfigs = config("acquirers");

        $credentialsFields = [];
        foreach ($acquirerConfigs as $type => $acquirerConfig) {
            if (isset($acquirerConfig['required_fields'])) {
                $fillData = [];
                foreach ($acquirerConfig['required_fields'] as $key => $value) {
                    $decryptedCredentials = $item ? $item->getDecryptedCredentials() : [];

                    $fillData[] = [
                        'key' => $value,
                        'value' => $decryptedCredentials[$value] ?? '',
                    ];
                }

                $credentialsFields[] = Json::make('Данные', 'acquirer_data')
                    ->fields([
                        Text::make('Поле', 'key')->readonly(),
                        Text::make('Значение', 'value')->eye(),
                    ])
                    ->fill($fillData)
                    ->creatable(false)
                    ->removable(false)
                    ->xIf(fn() => "type && (type==='$type' || type.value==='$type')");
            }
        }

        return [
            Box::make([
                BelongsTo::make(
                    'Партнер',
                    'user',
                    formatted: static fn(User $user) => $user->name ?? '',
                    resource: PartnerResource::class
                )->when($currentUser->isPartnerRole(), function (BelongsTo $field) use ($currentUser) {
                    return $field
                        ->valuesQuery(function (Builder $query) use ($currentUser) {
                            return $query->where('id', $currentUser->id);
                        });
                }, function ($field) {
                    return $field
                        ->asyncSearch('name')
                        ->asyncOnInit()
                        ->valuesQuery(function (Builder $query) {
                            return $query->where('role_id', Role::partner->value);
                        })
                        ->nullable();
                }),

                Enum::make('Тип эквайринга', 'type')->attach(AcquirerType::class)->nullable()->xModel(),
                Switcher::make('Конфигурация активна?', 'is_active')->default(true),
                ...$credentialsFields,
            ])->xData(['type' => $item ? $item->type : null]),
        ];
    }

    protected function rules($item): array
    {
        $rules = [
            'user_id' => 'required|exists:users,id',
            'type' => ['required', Rule::enum(AcquirerType::class)],
            'is_active' => 'boolean',
            'acquirer_data' => 'array',
        ];

        $type = request('type') ?? ($item->exists ? $item->type : null);

        if ($type) {
            $config = config("acquirers.{$type}");
            if ($config && isset($config['required_fields'])) {
                $rules['acquirer_data.*.key'] = 'required|string|max:255';
                $rules['acquirer_data.*.value'] = 'required|string|max:255';
            }
        }

        $currentUser = Auth::user();
        if ($currentUser->isPartnerRole()) {
            $rules['user_id'] = 'required|exists:users,id|in:' . $currentUser->id;
        }

        return $rules;
    }

    public function validationMessages(): array
    {
        return [
            'user_id.required' => 'Поле "Партнер" обязательно для заполнения.',
            'user_id.exists' => 'Выбранный партнер не существует.',
            'type.required' => 'Поле "Тип эквайринга" обязательно для заполнения.',
            'acquirer_data.array' => 'Данные эквайринга должны быть массивом.',
            'acquirer_data.*.key.required' => 'Ключ обязателен.',
            'acquirer_data.*.value.required' => 'Значение обязательно.',
        ];
    }

    public function search(): array
    {
        return [];
    }

    public function filters(): array
    {
        $filters = [
            Enum::make('Тип эквайринга', 'type')->attach(AcquirerType::class)->nullable(),
            Select::make('Активна', 'is_active')->options([
                '' => 'Все',
                '0' => 'Не активна',
                '1' => 'Активна',
            ])->default(''),
        ];

        if (Auth::user()->isAdminRole()) {
            $filters[] = BelongsTo::make(
                'Партнер',
                'user',
                formatted: static fn(User $user) => $user->name ?? '',
                resource: PartnerResource::class
            )
                ->asyncSearch('name')
                ->asyncOnInit()
                ->valuesQuery(fn(Builder $query) => $query->where('role_id', Role::partner->value))
                ->nullable()->default(null);
        }

        return $filters;
    }

    public function actions(): array
    {
        return [];
    }

    protected function beforeCreating(mixed $item): mixed
    {
        $currentUser = Auth::user();

        if ($currentUser->isPartnerRole()) {
            $item->user_id = $currentUser->id;
        }

        return $item;
    }

    public function findItem(bool $orFail = false): mixed
    {
        $item = parent::findItem($orFail);
        $currentUser = Auth::user();

        if ($item && $item->user_id !== $currentUser->id && $currentUser->isPartnerRole()) {
            if ($orFail) {
                abort(404, 'Сущность не найдена.');
            }
            return null;
        }

        return $item;
    }

    public function save(mixed $item, ?FieldsContract $fields = null): mixed
    {
        try {
            $requestData = request()->all();
            $credentials = [];

            if (isset($requestData['acquirer_data']) && is_array($requestData['acquirer_data'])) {
                foreach ($requestData['acquirer_data'] as $data) {
                    if (isset($data['key'], $data['value'])) {
                        $credentials[$data['key']] = $data['value'];
                    }
                }
            }

            unset($requestData['acquirer_data']);
            $item->fill($requestData);

            if (!empty($credentials)) {
                $item->setCredentials($credentials);
            }

            $item->save();
        } catch (QueryException $e) {
            Log::error('Acquirer configuration error: ' . $e->getMessage());
            throw new \Exception('Произошла ошибка при сохранении. Конфигурация для этого типа эквайринга уже существует для выбранного партнера.');
        }

        return $item;
    }
}
