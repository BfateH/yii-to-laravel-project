<?php

namespace App\Http\Requests\Moonshine\Order;

use App\Modules\OrderManagement\Enums\OrderStatus;
use App\Modules\OrderManagement\Enums\PackageStatus;
use Illuminate\Validation\Rule;
use MoonShine\Laravel\Http\Requests\MoonShineFormRequest;


class OrderStatusRequest extends MoonShineFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $packageStatusValues = array_column(PackageStatus::cases(), 'value');

        return [
            'packageStatus' => ['required', 'array'],
            'packageStatus.*' => ['nullable', 'integer', Rule::in($packageStatusValues)],
        ];
    }
}
