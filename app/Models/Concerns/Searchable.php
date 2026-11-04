<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Case-insensitive "contains" search over the given columns. A purely
     * numeric term also matches the primary key.
     *
     * @param  list<string>  $columns
     */
    public function scopeSearch(Builder $query, string $term, array $columns): Builder
    {
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($columns, $like, $term) {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }

            if (ctype_digit($term)) {
                $inner->orWhere($inner->getModel()->getQualifiedKeyName(), (int) $term);
            }
        });
    }
}
