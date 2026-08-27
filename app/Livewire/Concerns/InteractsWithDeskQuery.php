<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Session;

/**
 * Field-wise query (same pattern as Inventory → Items).
 *
 * Each page must implement deskQueryFields() and deskQuerySessionKey().
 */
trait InteractsWithDeskQuery
{
    public bool $showQueryModal = false;

    public string $queryField = '';

    public string $queryOperator = 'contains';

    public string $queryValue = '';

    public string $queryJoin = 'and';

    /** @var list<array{field: string, operator: string, value: string, join: string, label: string}> */
    public array $queryCriteria = [];

    public ?int $querySelectedIndex = null;

    public string $queryStatus = '';

    public string $querySaveName = '';

    public string $querySavedPick = '';

    public string $queryLoadedName = '';

    public function openDeskQuery(): void
    {
        $this->showQueryModal = true;
        $this->queryStatus = '';
        if ($this->queryField === '' || ! isset($this->deskQueryFields()[$this->queryField])) {
            $fields = $this->deskQueryFields();
            $this->queryField = (string) array_key_first($fields);
            $this->syncQueryOperatorForField($this->queryField);
        }
    }

    public function closeDeskQuery(): void
    {
        $this->showQueryModal = false;
        $this->queryStatus = '';
    }

    public function updatedQueryField(?string $value = null): void
    {
        $this->syncQueryOperatorForField((string) ($value ?? $this->queryField));
        $this->queryValue = '';
    }

    public function addQueryCriterion(): void
    {
        $fields = $this->deskQueryFields();
        $field = $this->queryField;
        if (! isset($fields[$field])) {
            $this->queryStatus = 'Choose a field.';

            return;
        }
        $op = $this->queryOperator;
        if (! in_array($op, ['empty', 'not_empty'], true) && trim((string) $this->queryValue) === '') {
            $this->queryStatus = 'Enter a value, or use Is empty / Is not empty.';

            return;
        }

        $criterion = [
            'field' => $field,
            'operator' => $op,
            'value' => trim((string) $this->queryValue),
            'join' => $this->queryJoin === 'or' ? 'or' : 'and',
            'label' => $this->deskQueryCriterionLabel($field, $op, trim($this->queryValue)),
        ];

        if ($this->querySelectedIndex !== null && isset($this->queryCriteria[$this->querySelectedIndex])) {
            $this->queryCriteria[$this->querySelectedIndex] = $criterion;
            $this->querySelectedIndex = null;
            $this->queryStatus = 'Criterion updated.';
        } else {
            $this->queryCriteria[] = $criterion;
            $this->queryStatus = 'Criterion added.';
        }
        $this->queryCriteria = array_values($this->queryCriteria);
    }

    public function selectQueryCriterion(int $index): void
    {
        if (! isset($this->queryCriteria[$index])) {
            return;
        }
        $row = $this->queryCriteria[$index];
        $this->querySelectedIndex = $index;
        $this->queryField = (string) ($row['field'] ?? $this->queryField);
        $this->queryOperator = (string) ($row['operator'] ?? 'contains');
        $this->queryValue = (string) ($row['value'] ?? '');
        $this->queryJoin = (string) ($row['join'] ?? 'and');
    }

    public function removeQueryCriterion(): void
    {
        if ($this->querySelectedIndex === null || ! isset($this->queryCriteria[$this->querySelectedIndex])) {
            $this->queryStatus = 'Select a criterion to remove.';

            return;
        }
        unset($this->queryCriteria[$this->querySelectedIndex]);
        $this->queryCriteria = array_values($this->queryCriteria);
        $this->querySelectedIndex = null;
        $this->queryStatus = 'Criterion removed.';
    }

    public function clearQueryCriteria(): void
    {
        $this->queryCriteria = [];
        $this->querySelectedIndex = null;
        $this->queryLoadedName = '';
        $this->querySavedPick = '';
        $this->queryStatus = '';
        $this->resetPage();
    }

    public function runDeskQuery(): void
    {
        if ($this->queryCriteria === []) {
            $this->addQueryCriterion();
        }
        if ($this->queryCriteria === []) {
            return;
        }
        $this->showQueryModal = false;
        $this->selectedId = null;
        $this->resetPage();
    }

    public function saveDeskQuery(): void
    {
        $name = trim($this->querySaveName);
        if ($name === '') {
            $this->queryStatus = 'Enter a name to save this search.';

            return;
        }
        if ($this->queryCriteria === []) {
            $this->queryStatus = 'Add at least one criterion before saving.';

            return;
        }
        if (isset($this->builtInDeskQueries()[$name])) {
            $this->queryStatus = '“'.$name.'” is a built-in search. Choose a different name.';

            return;
        }
        $saved = $this->userSavedDeskQueries();
        $saved[$name] = $this->queryCriteria;
        $this->storeSavedDeskQueries($saved);
        $this->queryLoadedName = $name;
        $this->queryStatus = 'Saved “'.$name.'”.';
    }

    public function loadDeskQuery(string $name): void
    {
        $saved = $this->loadSavedDeskQueries();
        if (! isset($saved[$name]) || ! is_array($saved[$name])) {
            return;
        }
        $this->queryCriteria = array_values($saved[$name]);
        $this->queryLoadedName = $name;
        $this->querySavedPick = $name;
        $this->queryStatus = 'Loaded “'.$name.'”.';
    }

    public function updatedQuerySavedPick(string $name): void
    {
        if ($name !== '') {
            $this->loadDeskQuery($name);
        }
    }

    public function deleteSavedDeskQuery(): void
    {
        $name = trim($this->querySavedPick ?: $this->queryLoadedName);
        if ($name === '') {
            $this->queryStatus = 'Choose a saved search to delete.';

            return;
        }
        if (isset($this->builtInDeskQueries()[$name])) {
            $this->queryStatus = '“'.$name.'” is a built-in search and cannot be deleted.';

            return;
        }
        $saved = $this->userSavedDeskQueries();
        unset($saved[$name]);
        $this->storeSavedDeskQueries($saved);
        $this->queryLoadedName = '';
        $this->querySavedPick = '';
        $this->queryStatus = 'Deleted “'.$name.'”.';
    }

    /**
     * @return array<string, array{label: string, column: string, has?: string, type?: string}>
     */
    protected function deskQueryFields(): array
    {
        return [];
    }

    protected function deskQuerySessionKey(): string
    {
        return 'desk_query_'.(int) auth()->id();
    }

    /** @return array<string, string> */
    protected function deskQueryFieldOptions(): array
    {
        return collect($this->deskQueryFields())->mapWithKeys(
            fn ($meta, $key) => [$key => $meta['label']]
        )->all();
    }

    /** @return array<string, string> */
    protected function deskQueryFieldTypes(): array
    {
        return collect($this->deskQueryFields())->mapWithKeys(
            fn ($meta, $key) => [$key => $meta['type'] ?? 'text']
        )->all();
    }

    /** @return array<string, string> */
    protected function deskQueryOperatorOptions(): array
    {
        return [
            'eq' => 'Equals',
            'ne' => 'Not equal',
            'contains' => 'Contains',
            'starts' => 'Starts with',
            'lt' => 'Less than',
            'lte' => 'Less than or equal',
            'gt' => 'Greater than',
            'gte' => 'Greater than or equal',
            'empty' => 'Is empty',
            'not_empty' => 'Is not empty',
        ];
    }

    public function deskQueryValueInputType(): string
    {
        return match ($this->deskQueryFields()[$this->queryField]['type'] ?? 'text') {
            'date' => 'date',
            'number' => 'number',
            default => 'text',
        };
    }

    protected function syncQueryOperatorForField(string $field): void
    {
        $type = $this->deskQueryFields()[$field]['type'] ?? 'text';
        $this->queryOperator = match ($type) {
            'date' => 'gte',
            'number' => 'eq',
            default => 'contains',
        };
    }

    protected function applyQueryCriteria($query)
    {
        $rows = $this->queryCriteria;
        if ($rows === []) {
            return $query;
        }

        $query->where(function ($outer) use ($rows) {
            foreach ($rows as $i => $row) {
                $join = strtolower((string) ($row['join'] ?? 'and')) === 'or' ? 'or' : 'and';
                $callback = function ($q) use ($row) {
                    $this->applyDeskQueryCriterion($q, $row);
                };
                if ($i === 0) {
                    $outer->where($callback);
                } elseif ($join === 'or') {
                    $outer->orWhere($callback);
                } else {
                    $outer->where($callback);
                }
            }
        });

        return $query;
    }

    protected function applyDeskQueryCriterion($q, array $row): void
    {
        $fields = $this->deskQueryFields();
        $field = (string) ($row['field'] ?? '');
        $meta = $fields[$field] ?? null;
        if (! $meta) {
            return;
        }

        $op = (string) ($row['operator'] ?? 'contains');
        $value = (string) ($row['value'] ?? '');
        $column = $meta['column'];
        $type = $meta['type'] ?? 'text';
        $has = $meta['has'] ?? null;

        if ($has) {
            $q->whereHas($has, function ($rel) use ($column, $op, $value, $type) {
                $this->applyDeskQueryOperator($rel, $column, $op, $value, $type);
            });

            return;
        }

        $this->applyDeskQueryOperator($q, $column, $op, $value, $type);
    }

    protected function applyDeskQueryOperator($q, string $column, string $operator, string $value, string $type = 'text'): void
    {
        $qualified = $this->qualifyDeskQueryColumn($q, $column);

        if ($operator === 'empty') {
            $q->where(function ($inner) use ($qualified) {
                $inner->whereNull($qualified)->orWhere($qualified, '');
            });

            return;
        }
        if ($operator === 'not_empty') {
            $q->whereNotNull($qualified)->where($qualified, '!=', '');

            return;
        }

        if ($type === 'date') {
            $parsed = $this->parseDeskQueryDate($value);
            if ($parsed === null && ! in_array($operator, ['contains', 'starts'], true)) {
                return;
            }
            if ($parsed !== null && in_array($operator, ['eq', 'ne', 'lt', 'lte', 'gt', 'gte'], true)) {
                match ($operator) {
                    'ne' => $q->whereDate($qualified, '!=', $parsed),
                    'lt' => $q->whereDate($qualified, '<', $parsed),
                    'lte' => $q->whereDate($qualified, '<=', $parsed),
                    'gt' => $q->whereDate($qualified, '>', $parsed),
                    'gte' => $q->whereDate($qualified, '>=', $parsed),
                    default => $q->whereDate($qualified, '=', $parsed),
                };

                return;
            }
        }

        match ($operator) {
            'ne' => $q->where($qualified, '!=', $value),
            'contains' => $q->where($qualified, 'like', '%'.$value.'%'),
            'starts' => $q->where($qualified, 'like', $value.'%'),
            'lt' => $q->where($qualified, '<', $value),
            'lte' => $q->where($qualified, '<=', $value),
            'gt' => $q->where($qualified, '>', $value),
            'gte' => $q->where($qualified, '>=', $value),
            default => $q->where($qualified, $value),
        };
    }

    protected function qualifyDeskQueryColumn($q, string $column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }
        $table = method_exists($q, 'getModel') ? $q->getModel()->getTable() : null;

        return $table ? $table.'.'.$column : $column;
    }

    protected function parseDeskQueryDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function deskQueryCriterionLabel(string $field, string $op, string $value): string
    {
        $fields = $this->deskQueryFields();
        $fieldLabel = $fields[$field]['label'] ?? $field;
        $opLabel = $this->deskQueryOperatorOptions()[$op] ?? $op;
        $right = in_array($op, ['empty', 'not_empty'], true) ? '' : $value;

        return '( '.$fieldLabel.' | '.$opLabel.($right !== '' ? ' | '.$right : '').' )';
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function builtInDeskQueries(): array
    {
        return [];
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function loadSavedDeskQueries(): array
    {
        return array_merge($this->builtInDeskQueries(), $this->userSavedDeskQueries());
    }

    /** @return array<string, list<array<string, mixed>>> */
    protected function userSavedDeskQueries(): array
    {
        $data = Session::get($this->deskQuerySessionKey(), []);

        return is_array($data) ? $data : [];
    }

    /** @param  array<string, list<array<string, mixed>>>  $data */
    protected function storeSavedDeskQueries(array $data): void
    {
        Session::put($this->deskQuerySessionKey(), $data);
    }
}
