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

    public string $sortDir = 'desc';

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
            $this->sortDir = $this->deskSortDefaultDir($field);
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

    protected function deskSortDefaultDir(string $field): string
    {
        if (preg_match('/(?:^|_)(date|total|amount|balance|qty|id|created|updated|time)(?:$|_)/i', $field)
            || preg_match('/(?:date|total|amount|balance|created_at|updated_at)$/i', $field)) {
            return 'desc';
        }

        return 'asc';
    }

    protected function applyDeskSort(Builder $query, string $defaultColumn = 'id', string $defaultDir = 'desc'): Builder
    {
        $map = $this->deskSortMap();
        $table = $query->getModel()->getTable();
        $dir = strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc';
        $idCol = $table.'.id';

        $key = $this->sortField;
        if ($key === '' || ! isset($map[$key])) {
            $col = str_contains($defaultColumn, '.') ? $defaultColumn : $table.'.'.$defaultColumn;
            $query->orderBy($col, $defaultDir);
            if ($col !== $idCol) {
                $query->orderBy($idCol, $defaultDir);
            }

            return $query;
        }

        $spec = $map[$key];
        if (is_string($spec)) {
            $col = str_contains($spec, '.') ? $spec : $table.'.'.$spec;
            $query->orderBy($col, $dir);
            if ($col !== $idCol) {
                $query->orderBy($idCol, $dir);
            }

            return $query;
        }

        if (is_array($spec) && isset($spec['raw']) && is_string($spec['raw']) && $spec['raw'] !== '') {
            return $query->orderByRaw($spec['raw'].' '.$dir)->orderBy($idCol, $dir);
        }

        $relationName = (string) ($spec['relation'] ?? '');
        $column = (string) ($spec['column'] ?? '');
        if ($relationName === '' || $column === '' || ! method_exists($query->getModel(), $relationName)) {
            $query->orderBy($table.'.'.$defaultColumn, $defaultDir);
            if ($defaultColumn !== 'id') {
                $query->orderBy($idCol, $defaultDir);
            }

            return $query;
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
            $query->orderBy($table.'.'.$defaultColumn, $defaultDir);
            if ($defaultColumn !== 'id') {
                $query->orderBy($idCol, $defaultDir);
            }

            return $query;
        }

        return $query->orderBy($sub, $dir)->orderBy($idCol, $dir);
    }

    /**
     * @param  array<string, callable|string>  $accessors
     */
    protected function sortCollection(Collection $rows, array $accessors, string $defaultKey, string $defaultDir = 'desc'): Collection
    {
        $usingDefault = $this->sortField === '' || ! isset($accessors[$this->sortField]);
        $key = $usingDefault ? $defaultKey : $this->sortField;
        $dir = $usingDefault
            ? (strtolower($defaultDir) === 'desc' ? 'desc' : 'asc')
            : (strtolower($this->sortDir) === 'desc' ? 'desc' : 'asc');
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
