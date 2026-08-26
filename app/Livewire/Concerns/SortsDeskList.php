<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Click-to-sort for desk list tables. Pages override deskSortMap().
 *
 * Map values:
 * - 'column_name' or 'table.column'
 * - ['relation' => 'customer', 'column' => 'company_name']
 */
trait SortsDeskList
{
    public string $sortField = '';

    public string $sortDir = 'asc';

    public function sortBy(string $field): void
    {
        $allowed = $this->deskSortMap();
        if ($field === '' || ! isset($allowed[$field])) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** @return array<string, string|array{relation: string, column: string}> */
    protected function deskSortMap(): array
    {
        return [];
    }

    protected function applyDeskSort(Builder $query, string $defaultColumn = 'id', string $defaultDir = 'desc'): Builder
    {
        $map = $this->deskSortMap();
        $table = $query->getModel()->getTable();
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';

        $key = $this->sortField;
        if ($key === '' || ! isset($map[$key])) {
            $col = str_contains($defaultColumn, '.') ? $defaultColumn : $table.'.'.$defaultColumn;

            return $query->orderBy($col, $defaultDir);
        }

        $spec = $map[$key];
        if (is_string($spec)) {
            $col = str_contains($spec, '.') ? $spec : $table.'.'.$spec;

            return $query->orderBy($col, $dir);
        }

        if (is_array($spec) && isset($spec['raw']) && is_string($spec['raw']) && $spec['raw'] !== '') {
            return $query->orderByRaw($spec['raw'].' '.$dir);
        }

        $relationName = (string) ($spec['relation'] ?? '');
        $column = (string) ($spec['column'] ?? '');
        if ($relationName === '' || $column === '' || ! method_exists($query->getModel(), $relationName)) {
            return $query->orderBy($table.'.'.$defaultColumn, $defaultDir);
        }

        $relation = $query->getModel()->{$relationName}();
        $related = $relation->getRelated();
        $sub = $related->newQuery()
            ->select($related->qualifyColumn($column))
            ->limit(1);

        if ($relation instanceof BelongsTo) {
            $sub->whereColumn($relation->getQualifiedOwnerKeyName(), $relation->getQualifiedForeignKeyName());
        } elseif ($relation instanceof HasOne) {
            $sub->whereColumn($relation->getQualifiedForeignKeyName(), $relation->getQualifiedParentKeyName());
        } else {
            return $query->orderBy($table.'.'.$defaultColumn, $defaultDir);
        }

        return $query->orderBy($sub, $dir);
    }

    /**
     * @param  array<string, callable|string>  $accessors
     */
    protected function sortCollection(Collection $rows, array $accessors, string $defaultKey, string $defaultDir = 'asc'): Collection
    {
        $key = $this->sortField !== '' && isset($accessors[$this->sortField]) ? $this->sortField : $defaultKey;
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $accessor = $accessors[$key] ?? $accessors[$defaultKey];

        $sorted = $rows->sortBy(function ($row) use ($accessor) {
            $value = is_callable($accessor) ? $accessor($row) : data_get($row, $accessor);
            if (is_numeric($value)) {
                return (float) $value;
            }

            return mb_strtolower((string) $value);
        }, SORT_NATURAL);

        return ($dir === 'desc' ? $sorted->reverse() : $sorted)->values();
    }
}
