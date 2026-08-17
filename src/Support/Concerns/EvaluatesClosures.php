<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support\Concerns;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The shared closure-resolution surface (mirrors Filament's
 * `Support\Concerns\EvaluatesClosures`). `evaluate()` unwraps a plain value or,
 * for a `Closure`, resolves each parameter by *name* then by *type* against the
 * injections the caller supplies, plus a couple of built-in defaults (the
 * component itself, and the container for typed class-hinted dependencies).
 * Every config option that may be a closure resolves through here, so callers
 * get Filament's ergonomics — `->color(fn (Column $column) => ...)` etc. —
 * without a persistent server component.
 */
trait EvaluatesClosures
{
    protected string $evaluationIdentifier;

    /**
     * @template T
     *
     * @param  T | callable(): T  $value
     * @param  array<string, mixed>  $namedInjections
     * @param  array<string, mixed>  $typedInjections
     * @return mixed
     *
     * The return stays `mixed` (the native signature) rather than claiming
     * `T`: PHPStan cannot prove the closure's invocation returns `T` when the
     * value is a bare `Closure` (Filament suppresses this exact case in its
     * baseline; we don't carry a baseline). Callers that need a precise
     * result narrow after the call, as the components do.
     */
    public function evaluate(mixed $value, array $namedInjections = [], array $typedInjections = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        $dependencies = [];

        foreach ((new ReflectionFunction($value))->getParameters() as $parameter) {
            $dependencies[] = $this->resolveClosureDependencyForEvaluation($parameter, $namedInjections, $typedInjections);
        }

        return $value(...$dependencies);
    }

    /**
     * The typed injection map for a record — lets a closure type-hint the
     * concrete model (`fn (Post $post)`) as well as naming it `$record`.
     * Empty when the value isn't an object (there is no class to inject).
     *
     * @return array<string, mixed>
     */
    protected function recordTypeInjections(mixed $record): array
    {
        return is_object($record) ? [get_class($record) => $record] : [];
    }

    /**
     * @param  array<string, mixed>  $namedInjections
     * @param  array<string, mixed>  $typedInjections
     */
    protected function resolveClosureDependencyForEvaluation(ReflectionParameter $parameter, array $namedInjections, array $typedInjections): mixed
    {
        $parameterName = $parameter->getName();

        if (array_key_exists($parameterName, $namedInjections)) {
            return value($namedInjections[$parameterName]);
        }

        $typedParameterClassName = $this->getTypedReflectionParameterClassName($parameter);

        if (filled($typedParameterClassName) && array_key_exists($typedParameterClassName, $typedInjections)) {
            return value($typedInjections[$typedParameterClassName]);
        }

        // Dependencies are wrapped in an array to differentiate between null and no value.
        $defaultWrappedDependencyByName = $this->resolveDefaultClosureDependencyForEvaluationByName($parameterName);

        if (count($defaultWrappedDependencyByName)) {
            // Unwrap the dependency if it was resolved.
            return $defaultWrappedDependencyByName[0];
        }

        if (filled($typedParameterClassName)) {
            // Dependencies are wrapped in an array to differentiate between null and no value.
            $defaultWrappedDependencyByType = $this->resolveDefaultClosureDependencyForEvaluationByType($typedParameterClassName);

            if (count($defaultWrappedDependencyByType)) {
                // Unwrap the dependency if it was resolved.
                return $defaultWrappedDependencyByType[0];
            }
        }

        if (
            (
                isset($this->evaluationIdentifier) &&
                ($parameterName === $this->evaluationIdentifier)
            ) ||
            (is_string($typedParameterClassName) && is_a($this, $typedParameterClassName))
        ) {
            return $this;
        }

        if (
            filled($typedParameterClassName)
            && (! is_subclass_of($typedParameterClassName, Model::class) || app()->bound($typedParameterClassName))
        ) {
            return app()->make($typedParameterClassName);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isOptional() || $parameter->allowsNull()) {
            return null;
        }

        $staticClass = static::class;

        throw new BindingResolutionException("An attempt was made to evaluate a closure for [{$staticClass}], but [\${$parameterName}] was unresolvable.");
    }

    protected function evaluationValueIsFunctionAndHasParameter(mixed $value, string $parameterName): bool
    {
        if (! $value instanceof Closure) {
            return false;
        }

        foreach ((new ReflectionFunction($value))->getParameters() as $parameter) {
            if ($parameter->getName() === $parameterName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return [];
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByType(string $parameterType): array
    {
        return [];
    }

    protected function getTypedReflectionParameterClassName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        if ($type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        $class = $parameter->getDeclaringClass();

        if (is_null($class)) {
            return $name;
        }

        if ($name === 'self') {
            return $class->getName();
        }

        if ($name === 'parent' && ($parent = $class->getParentClass())) {
            return $parent->getName();
        }

        return $name;
    }
}
