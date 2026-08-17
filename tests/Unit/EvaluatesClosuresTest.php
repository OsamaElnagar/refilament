<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\BindingResolutionException;
use Refilament\Refilament\Support\Concerns\EvaluatesClosures;

beforeEach(function (): void {
    $this->evaluator = new class
    {
        use EvaluatesClosures;

        public function setEvaluationIdentifier(string $identifier): void
        {
            $this->evaluationIdentifier = $identifier;
        }

        public function isFunctionAndHasParameter(mixed $value, string $parameterName): bool
        {
            return $this->evaluationValueIsFunctionAndHasParameter($value, $parameterName);
        }
    };

    $this->evaluator->setEvaluationIdentifier('component');
});

it('returns plain values unchanged', function (): void {
    expect($this->evaluator->evaluate('plain'))->toBe('plain')
        ->and($this->evaluator->evaluate(5))->toBe(5)
        ->and($this->evaluator->evaluate(null))->toBeNull();
});

it('evaluates a closure with no parameters', function (): void {
    expect($this->evaluator->evaluate(fn (): string => 'resolved'))->toBe('resolved');
});

it('injects named dependencies', function (): void {
    $result = $this->evaluator->evaluate(
        fn (string $slug) => strtoupper($slug),
        ['slug' => 'products'],
    );

    expect($result)->toBe('PRODUCTS');
});

it('injects typed dependencies', function (): void {
    $result = $this->evaluator->evaluate(
        fn (DateTimeInterface $when): string => $when->format('Y'),
        [],
        [DateTimeInterface::class => new DateTimeImmutable('2026-01-01')],
    );

    expect($result)->toBe('2026');
});

it('injects the component itself by the evaluation identifier', function (): void {
    $result = $this->evaluator->evaluate(
        fn ($component) => get_class($component),
    );

    expect($result)->toBe(get_class($this->evaluator));
});

it('injects the component itself by type', function (): void {
    $result = $this->evaluator->evaluate(
        fn (mixed $component) => get_class($component),
    );

    expect($result)->toBe(get_class($this->evaluator));
});

it('falls back to a parameter default value', function (): void {
    $result = $this->evaluator->evaluate(
        fn (string $missing = 'fallback'): string => $missing,
    );

    expect($result)->toBe('fallback');
});

it('throws when a parameter is unresolvable', function (): void {
    $this->evaluator->evaluate(fn (string $missing): string => $missing);
})->throws(BindingResolutionException::class);

it('reports whether a value is a closure and mentions a parameter', function (): void {
    expect($this->evaluator->isFunctionAndHasParameter(fn (string $slug) => $slug, 'slug'))->toBeTrue()
        ->and($this->evaluator->isFunctionAndHasParameter('nope', 'slug'))->toBeFalse()
        ->and($this->evaluator->isFunctionAndHasParameter(fn () => null, 'slug'))->toBeFalse();
});
