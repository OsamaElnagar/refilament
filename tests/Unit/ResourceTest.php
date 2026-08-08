<?php

declare(strict_types=1);

use LogicException;
use Refilament\Refilament\Tests\Fixtures\DemoResource;
use Refilament\Refilament\Tests\Fixtures\HiddenResource;
use Workbench\App\Models\Post;

it('derives table and form ids from the class name', function () {
    expect(DemoResource::getTableId())->toBe('demo');
    expect(DemoResource::getFormId())->toBe('demo-form');
});

it('returns the configured model', function () {
    expect(DemoResource::getModel())->toBe(Post::class);
});

it('throws when no model is defined', function () {
    HiddenResource::getModel();
})->throws(LogicException::class, 'must define a [$model] property');

it('builds form data from the fields defaults', function () {
    expect(DemoResource::formData())->toBe([
        'title' => 'Hello',
        'status' => null,
    ]);
});

it('is discovered by default unless opted out', function () {
    expect(DemoResource::isDiscovered())->toBeTrue();
    expect(HiddenResource::isDiscovered())->toBeFalse();
});
