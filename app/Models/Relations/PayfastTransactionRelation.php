<?php

namespace App\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A wallet-only entry has no PayFast transaction, even if a legacy ID remains.
 */
class PayfastTransactionRelation extends BelongsTo
{
    protected function getForeignKeyFrom(Model $model)
    {
        return $model->payment_method === 'wallet' ? null : parent::getForeignKeyFrom($model);
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->where(fn ($query) => $query
                ->whereNull($parentQuery->getModel()->qualifyColumn('payment_method'))
                ->orWhere($parentQuery->getModel()->qualifyColumn('payment_method'), '!=', 'wallet'));
    }
}
