<?php

declare(strict_types=1);

use Refilament\Refilament\Schemas\Components\FileUpload;
use Refilament\Refilament\Schemas\Components\RichEditor;

it('serializes a file upload with its storage options', function () {
    $node = FileUpload::make('attachment')
        ->label('Attachment')
        ->disk('public')
        ->directory('uploads')
        ->acceptedFileTypes(['image/png', 'application/pdf'])
        ->maxSize(2048)
        ->imagePreview()
        ->toArray();

    expect($node['type'])->toBe('file_upload');
    expect($node['name'])->toBe('attachment');
    expect($node['disk'])->toBe('public');
    expect($node['directory'])->toBe('uploads');
    expect($node['acceptedFileTypes'])->toBe(['image/png', 'application/pdf']);
    expect($node['maxSize'])->toBe(2048);
    expect($node)->not->toHaveKey('multiple');
    expect($node)->not->toHaveKey('imagePreview');
});

it('serializes a multiple upload and omits image preview when off', function () {
    $node = FileUpload::make('gallery')->multiple()->imagePreview(false)->toArray();

    expect($node['multiple'])->toBeTrue()
        ->and($node['imagePreview'])->toBeFalse();
});

it('validates the upload value as a string, or an array when multiple', function () {
    expect(FileUpload::make('file')->getValidationRules())->toBe(['nullable', 'string'])
        ->and(FileUpload::make('files')->multiple()->getValidationRules())->toBe(['nullable', 'array']);
});

it('serializes a rich editor with its options', function () {
    $node = RichEditor::make('body')
        ->label('Body')
        ->placeholder('Write…')
        ->toolbarButtons(['bold', 'italic'])
        ->maxHeight(240)
        ->toArray();

    expect($node['type'])->toBe('rich_editor');
    expect($node['name'])->toBe('body');
    expect($node['placeholder'])->toBe('Write…');
    expect($node['toolbarButtons'])->toBe(['bold', 'italic']);
    expect($node['maxHeight'])->toBe(240);
});

it('validates the rich editor value as a string', function () {
    expect(RichEditor::make('body')->getValidationRules())->toBe(['nullable', 'string']);
});

it('omits unset rich editor options', function () {
    $node = RichEditor::make('body')->toArray();

    expect($node)->not->toHaveKey('placeholder')
        ->and($node)->not->toHaveKey('toolbarButtons')
        ->and($node)->not->toHaveKey('maxHeight');
});
