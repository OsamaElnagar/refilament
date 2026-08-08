<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

final class HomeController
{
    public function __invoke(): string
    {
        return 'Refilament workbench';
    }
}
