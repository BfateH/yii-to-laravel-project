<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use Closure;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Facades\Vite;
use MoonShine\AssetManager\Css;
use MoonShine\AssetManager\Js;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\UI\Fields\Field;

class CKEditor extends Field
{
    protected string $view = 'admin.fields.c-k-editor';

    protected function assets(): array
    {
        return [
            Css::make(Vite::asset('resources/css/ckeditor.css')),
            Js::make(Vite::asset('resources/js/ckeditor.js'))->setAttribute('type', 'module')
        ];
    }

    protected function reformatFilledValue(mixed $data): mixed
    {
        return parent::reformatFilledValue($data);
    }

    protected function prepareFill(array $raw = [], ?DataWrapperContract $casted = null, int $index = 0): mixed
    {
        return parent::prepareFill($raw, $casted, $index);
    }

    protected function resolveValue(): mixed
    {
        return $this->toValue();
    }

    protected function resolvePreview(): Renderable|string
    {
        return (string) ($this->toFormattedValue() ?? '');
    }

    protected function resolveOnApply(): ?Closure
    {
        return function (mixed $item): mixed {
            return data_set($item, $this->getColumn(), $this->getRequestValue());
        };
    }

    protected function viewData(): array
    {
        return [
            'value' => $this->resolveValue(),
            'name' => $this->getColumn(),
            'id' => $this->getAttributes()->get('id') ?? $this->getNameAttribute(),
            'uploadUrl' => route('ckeditor.upload'),
        ];
    }
}
