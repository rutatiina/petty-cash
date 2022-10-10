<?php

namespace Rutatiina\PettyCash\Models;

use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashEntryLedger extends Model
{
    use SoftDeletes;
    
    protected $connection = 'tenant';

    protected $table = 'rg_petty_cash_entry_ledgers';

    protected $primaryKey = 'id';

    protected $guarded = ['id'];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);
    }

    public function expense()
    {
        return $this->belongsTo('Rutatiina\PettyCash\Models\PettyCash', 'petty_cash_entry_id');
    }

}
