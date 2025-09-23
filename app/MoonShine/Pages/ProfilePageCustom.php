<?php

namespace App\MoonShine\Pages;

use App\Http\Controllers\AccountController;
use App\Models\Channel;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AccountDeletionRequested;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Http\Controllers\ProfileController;
use MoonShine\Laravel\Http\Responses\MoonShineJsonResponse;
use MoonShine\Laravel\MoonShineAuth;
use MoonShine\Laravel\MoonShineRequest;
use MoonShine\Laravel\Pages\ProfilePage;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use MoonShine\Support\Enums\Color;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Badge;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Password;
use MoonShine\UI\Fields\PasswordRepeat;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

class ProfilePageCustom extends ProfilePage
{
    protected ?string $alias = 'profile-page';
    private UserService $userService;

    public function __construct(CoreContract $core)
    {
        parent::__construct($core);
        $this->userService = app(UserService::class);
    }

    protected function fields(): iterable
    {
        $userFields = array_filter([
            ID::make(),

            moonshineConfig()->getUserField('name')
                ? Text::make(__('moonshine::ui.resource.name'), moonshineConfig()->getUserField('name'))
                ->required()
                : null,

            moonshineConfig()->getUserField('username')
                ? Text::make(__('moonshine::ui.login.username'), moonshineConfig()->getUserField('username'))
                ->required()
                : null,

            moonshineConfig()->getUserField('avatar')
                ? Image::make(__('moonshine::ui.resource.avatar'), moonshineConfig()->getUserField('avatar'))
                ->disk(moonshineConfig()->getDisk())
                ->options(moonshineConfig()->getDiskOptions())
                ->dir('moonshine_users')
                ->removable()
                ->allowedExtensions(['jpg', 'png', 'jpeg', 'gif'])
                : null,
        ]);

        $userPasswordsFields = moonshineConfig()->getUserField('password') ? [
            Heading::make(__('moonshine::ui.resource.change_password')),

            Password::make(__('moonshine::ui.resource.password'), moonshineConfig()->getUserField('password'))
                ->customAttributes(['autocomplete' => 'new-password'])
                ->eye(),

            PasswordRepeat::make(__('moonshine::ui.resource.repeat_password'), 'password_repeat')
                ->customAttributes(['autocomplete' => 'confirm-password'])
                ->eye(),
        ] : [];

        $deleteFields = [
            Text::make()
                ->badge(Color::WARNING)
                ->setValue('После удаления аккаунта все ваши данные будут безвозвратно удалены.')
                ->previewMode(),

            ActionButton::make('Запросить удаление аккаунта')
                ->method('sendDeleteRequest')
                ->icon('trash')
                ->withConfirm('Вы уверены, что хотите удалить аккаунт?', '')
                ->error()
        ];

        $currentUser = Auth::user();
        $alertFields = [];

        $channels = Channel::all();
        foreach ($channels as $channel) {
            $isSubscribed = Subscription::where('user_id', $currentUser->id)
                ->where('channel_id', $channel->id)
                ->value('subscribed') ?? false;

            $alertFields[] = Switcher::make('Включить ' . $channel->name . ' уведомления?', 'alert_' . $channel->id)
                ->setValue($isSubscribed)
                ->onValue(1)
                ->offValue(0);
        }

        $alertFields[] = Flex::make([
            Badge::make('Ваш telegram_id: <b>' . ($currentUser->telegram_id ? $currentUser->telegram_id : 'Не определен') . '</b>')
        ])->columnSpan(12)->justifyAlign('start');

        $alertFields[] = Flex::make([
            Badge::make('Для работы <b>webpush</b> уведомлений, нужно разрешить сайту присылать вам уведомления.', Color::INFO)
        ])->columnSpan(12)->justifyAlign('start');

        $alertFields[] = Flex::make([
            Badge::make('Для работы <b>telegram</b> уведомлений, нужно написать боту ваш email, используемый на нашем сайте.', Color::INFO)
        ])->columnSpan(12)->justifyAlign('start');

        return [
            Box::make([
                Tabs::make([
                    Tab::make(__('moonshine::ui.resource.main_information'), $userFields)->icon('information-circle'),

                    Tab::make(__('moonshine::ui.resource.password'), $userPasswordsFields)->canSee(
                        fn(): bool => $userPasswordsFields !== [],
                    )->icon('key'),

                    Tab::make(__('Уведомления'), $alertFields)->icon('bell-alert'),

                    Tab::make(__('moonshine::ui.resource.delete_information'), $deleteFields)->icon('hand-raised'),
                ]),
            ]),
        ];
    }

    public function getForm(): FormBuilderContract
    {
        $user = MoonShineAuth::getGuard()->user() ?? MoonShineAuth::getModel();

        return FormBuilder::make(route('moonshine.admin.profile.store'))
            ->async()
            ->fields($this->fields())
            ->fillCast($user, new ModelCaster($user::class))
            ->submit(__('moonshine::ui.save'), [
                'class' => 'btn-lg btn-primary',
            ]);
    }

    public function sendDeleteRequest(MoonShineRequest $request)
    {
        try {
            $user = User::query()->find(Auth::user()->id);
            $this->userService->requestDeletion($user);
            $user->notify(new AccountDeletionRequested($user));

            return MoonShineJsonResponse::make()
                ->toast('Письмо для подтверждения удаления аккаунта отправлено на ваш email.', ToastType::SUCCESS);
        } catch (\Exception $e) {
            Log::error('Error sendDeleteRequest Exception: ' . $e->getMessage());

            return MoonShineJsonResponse::make()
                ->toast('Произошла ошибка при создании запроса на удаление аккаунта.', ToastType::ERROR);
        }
    }
}
