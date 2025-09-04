<?php

declare(strict_types=1);

namespace App\MoonShine\Components;

use Closure;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make()
 */
final class SocialLinksWithAuth extends MoonShineComponent
{
    protected string $view = 'admin.components.social-links-with-auth';

    public function __construct()
    {
        parent::__construct();

        //
    }

    /*
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [];
    }
}
