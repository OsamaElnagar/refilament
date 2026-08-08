<?php

declare(strict_types=1);

namespace Workbench\App\Enums;

/**
 * Demo backed enum (slice C2): `Select::make('status')->options(PostStatus::class)`
 * derives the option list from the enum's cases — the production-reference
 * §5.2 idiom (`->options(SomeEnum::class)`).
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
