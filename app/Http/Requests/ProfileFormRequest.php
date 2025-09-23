<?php

namespace App\Http\Requests;

use App\Models\Channel;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Http\Requests\MoonShineFormRequest;
use MoonShine\Laravel\MoonShineAuth;

class ProfileFormRequest extends MoonShineFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return MoonShineAuth::getGuard()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $name = moonshineConfig()->getUserField('name');
        $username = moonshineConfig()->getUserField('username');
        $avatar = moonshineConfig()->getUserField('avatar');
        $password = moonshineConfig()->getUserField('password');

        $notificationRules = $this->getNotificationFieldRules();

        return array_filter([
            $name => blank($name) ? null : ['required'],
            $username => blank($username) ? null : [
                'required',
                Rule::unique(
                    MoonShineAuth::getModel()->getTable(),
                    moonshineConfig()->getUserField('username')
                )->ignore(MoonShineAuth::getGuard()->id()),
            ],
            $avatar => blank($avatar) ? null : ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,gif'],
            $password => blank($password) ? null : 'sometimes|nullable|min:6|required_with:password_repeat|same:password_repeat',
            ...$notificationRules,
        ]);
    }

    protected function getNotificationFieldRules(): array
    {
        $rules = [];
        $channels = Channel::all();

        foreach ($channels as $channel) {
            $fieldName = 'alert_' . $channel->id;
            $rules[$fieldName] = ['sometimes', 'nullable', 'boolean'];
        }

        return $rules;
    }
}
