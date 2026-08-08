<?php

declare(strict_types=1);

namespace Refilament\Refilament\Support;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;

/**
 * Sorting helper for relationship (dot-notation) table columns (Slice 2.1).
 *
 * A sortable relationship column like `user.name` has no plain SQL column to
 * ORDER BY on the base table. Instead of building a normal join that could
 * duplicate parent rows, we order by a correlated subquery that selects the
 * related column for each parent row via the relationship's own constraints,
 * capped with LIMIT 1. This mirrors Filament's `RelationshipOrderer` and
 * keeps the row count exact (docs/ARCHITECTURE.md, "Tables & relationship
 * columns").
 *
 * Single-hop relationships are the common case (`author.name`,
 * `user.email`); nested dot-hop paths (`user.profile.city`) are supported by
 * chaining to-one relationships in the subquery.
 */
class RelationshipOrderer
{
    /**
     * Build the correlated subquery that yields the value of `$column` on the
     * related row(s) of `$relationshipPath` for the given parent model.
     */
    public function buildSubquery(EloquentBuilder $query, string $relationshipPath, string $column): Builder
    {
        $relationshipChain = $this->buildRelationshipChain($query->getModel(), $relationshipPath);

        $lastRelationship = $this->lastRelationship($relationshipChain);

        $subquery = $lastRelationship->getQuery();

        $this->applyRelationshipConstraints($subquery, $relationshipChain, $query->getModel());

        return $subquery->select($lastRelationship->getRelated()->qualifyColumn($column))->limit(1)->toBase();
    }

    /**
     * Resolve the ordered list of to-one relationships described by a
     * dot-notation path, starting from the base model.
     *
     * @return array<int, BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>>
     */
    protected function buildRelationshipChain(Model $baseModel, string $relationshipPath): array
    {
        $relationshipSegments = explode('.', $relationshipPath);

        /** @var array<int, BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>> $chain */
        $chain = [];

        $currentModel = $baseModel;

        foreach ($relationshipSegments as $relationshipSegment) {
            /** @var BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model> $relationship */
            $relationship = Relation::noConstraints(static fn (): Relation => $currentModel->{$relationshipSegment}());

            $this->validateRelationshipType($relationship);

            $chain[] = $relationship;

            $currentModel = $relationship->getRelated();
        }

        return $chain;
    }

    /**
     * @param  array<int, BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>>  $chain
     *
     * @return BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>
     */
    protected function lastRelationship(array $chain): BelongsTo|HasOne|MorphOne
    {
        $lastIndex = array_key_last($chain);

        if ($lastIndex === null) {
            throw new \RuntimeException('Cannot order by an empty relationship chain.');
        }

        return $chain[$lastIndex];
    }

    /**
     * @param  Relation<Model, Model, mixed>  $relationship
     */
    protected function validateRelationshipType(Relation $relationship): void
    {
        if ($relationship instanceof BelongsTo || $relationship instanceof HasOne || $relationship instanceof MorphOne) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Sorting a [%s] relationship is not supported. Only [BelongsTo], [HasOne] and [MorphOne] relationships can be ordered by related columns.',
            $relationship::class,
        ));
    }

    /**
     * Constrain the subquery so it only resolves the related rows that belong
     * to each parent row. The relationship chain is walked back-to-front: the
     * last relationship is the subquery's base source; the constraints that
     * pin it to the parent row (and to intermediary "through" hops) are added
     * as the chain unwinds.
     *
     * @param  array<int, BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>>  $relationshipChain
     */
    protected function applyRelationshipConstraints(EloquentBuilder $subquery, array $relationshipChain, Model $baseModel): void
    {
        $chainLength = count($relationshipChain);

        for ($index = $chainLength - 1; $index >= 0; $index--) {
            $relationship = $relationshipChain[$index];

            if ($index === 0) {
                $this->applyFirstRelationshipConstraint($subquery, $relationship, $baseModel);

                continue;
            }

            $this->joinIntermediateRelationship($subquery, $relationship, $relationshipChain[$index - 1]);
        }
    }

    /**
     * Constrain the subquery to the rows belonging to a given parent row.
     *
     * @param  BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>  $relationship
     */
    protected function applyFirstRelationshipConstraint(EloquentBuilder $subquery, BelongsTo|HasOne|MorphOne $relationship, Model $baseModel): void
    {
        if ($relationship instanceof BelongsTo) {
            $subquery->whereColumn(
                $relationship->getQualifiedOwnerKeyName(),
                $relationship->getQualifiedForeignKeyName(),
            );

            return;
        }

        // HasOne / MorphOne: the related table holds the foreign key that
        // points back at the base model's (qualified) local key. A MorphOne
        // also constrains the polymorphic type column to its morph class.
        $subquery->whereColumn(
            $relationship->getQualifiedForeignKeyName(),
            $baseModel->qualifyColumn($relationship->getLocalKeyName()),
        );

        if ($relationship instanceof MorphOne) {
            $subquery->where(
                $relationship->getQualifiedMorphType(),
                $relationship->getMorphClass(),
            );
        }
    }

    /**
     * Join an intermediate (non-base) to-one relationship into the subquery so
     * its "through" model links the target's key back to the previous hop.
     *
     * @param  BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>  $currentRelationship
     * @param  BelongsTo<Model, Model>|HasOne<Model, Model>|MorphOne<Model, Model>  $previousRelationship
     */
    protected function joinIntermediateRelationship(EloquentBuilder $subquery, BelongsTo|HasOne|MorphOne $currentRelationship, BelongsTo|HasOne|MorphOne $previousRelationship): void
    {
        $previousTable = $previousRelationship->getRelated()->getTable();

        if ($currentRelationship instanceof BelongsTo) {
            $subquery->join(
                $previousTable,
                $currentRelationship->getQualifiedOwnerKeyName(),
                '=',
                $currentRelationship->getQualifiedForeignKeyName(),
            );

            return;
        }

        $subquery->join(
            $previousTable,
            $currentRelationship->getQualifiedForeignKeyName(),
            '=',
            $currentRelationship->getQualifiedParentKeyName(),
        );

        if ($currentRelationship instanceof MorphOne) {
            $subquery->where(
                $currentRelationship->getQualifiedMorphType(),
                $currentRelationship->getMorphClass(),
            );
        }
    }
}