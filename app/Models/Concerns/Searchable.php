<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Case-insensitive "contains" search over the given columns. A purely
     * numeric term also matches the primary key.
     *
     * @param  Builder<static>  $query
     * @param  list<string>  $columns
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term, array $columns): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($columns, $term) {
            foreach ($columns as $column) {
                static::whereContains($inner, $column, $term, 'or');
            }

            if (ctype_digit($term)) {
                $inner->orWhere($inner->getModel()->getQualifiedKeyName(), (int) $term);
            }
        });
    }

    /**
     * `column LIKE %term%` with the term taken literally: "%" and "_" typed by
     * someone are not wildcards. "!" is the escape character because it means
     * the same in MariaDB and SQLite (a backslash does not).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function whereContains(Builder $query, string $column, string $term, string $boolean = 'and'): Builder
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
        $wrapped = $query->getQuery()->getGrammar()->wrap($column);

        return $query->whereRaw("{$wrapped} LIKE ? ESCAPE '!'", [$pattern], $boolean);
    }
}
