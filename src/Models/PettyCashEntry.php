<?php

namespace Rutatiina\PettyCash\Models;

use Illuminate\Database\Eloquent\Model;
use Rutatiina\Tenant\Scopes\TenantIdScope;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashEntry extends Model
{
    use SoftDeletes;
    
    protected $connection = 'tenant';

    protected $table = 'rg_petty_cash_entries';

    protected $primaryKey = 'id';

    protected $guarded = [];

    protected $casts = [
        'canceled' => 'integer',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
    protected $appends = [
        'number_string',
        'total_in_words',
        'ledgers'
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new TenantIdScope);

        self::deleted(function($txn) { // before delete() method call this
             $txn->ledgers()->each(function($row) {
                $row->delete();
             });
        });

        self::restored(function($txn) {
             $txn->ledgers()->each(function($row) {
                $row->restore();
             });
        });

    }

    public function rgGetAttributes()
    {
        $attributes = [];
        $describeTable =  \DB::connection('tenant')->select('describe ' . $this->getTable());

        foreach ($describeTable  as $row) {

            if (in_array($row->Field, ['id', 'created_at', 'updated_at', 'deleted_at', 'tenant_id', 'user_id'])) continue;

            if (in_array($row->Field, ['currencies', 'taxes'])) {
                $attributes[$row->Field] = [];
                continue;
            }

            if ($row->Default == '[]') {
                $attributes[$row->Field] = [];
            } else {
                $attributes[$row->Field] = ''; //$row->Default; //null affects laravel validation
            }
        }

        //add the relationships
        $attributes['type'] = [];
        $attributes['debit_account'] = [];
        $attributes['credit_account'] = [];
        $attributes['ledgers'] = [];
        $attributes['contact'] = [];

        return $attributes;
    }

    public function getContactAddressArrayAttribute()
    {
        return preg_split("/\r\n|\n|\r/", $this->contact_address);
    }

    public function getNumberStringAttribute()
    {
        return $this->number_prefix.(str_pad(($this->number), $this->number_length, "0", STR_PAD_LEFT)).$this->number_postfix;
    }

    public function getTotalInWordsAttribute()
    {
        $f = new \NumberFormatter( locale_get_default(), \NumberFormatter::SPELLOUT );
        return ucfirst($f->format($this->total));
    }

    public function debit_account()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'debit_financial_account_code');
    }

    public function credit_account()
    {
        return $this->hasOne('Rutatiina\FinancialAccounting\Models\Account', 'code', 'credit_financial_account_code');
    }

    public function getLedgersAttribute($txn = null)
    {
        // if (!$txn) $this->items;

        $txn = $txn ?? $this;

        $txn = (is_object($txn)) ? $txn : collect($txn);
        
        $ledgers = [];

        //DR ledger
        $ledgers[] = [
            'financial_account_code' => $txn->debit_financial_account_code,
            'effect' => 'debit',
            'total' => $txn->amount,
            'contact_id' => $txn->contact_id
        ];

        //CR ledger
        $ledgers[] = [
            'financial_account_code' => $txn->credit_financial_account_code,
            'effect' => 'credit',
            'total' => $txn->amount,
            'contact_id' => $txn->contact_id
        ];

        foreach ($ledgers as &$ledger)
        {
            $ledger['tenant_id'] = $txn->tenant_id;
            $ledger['date'] = $txn->date;
            $ledger['base_currency'] = $txn->base_currency;
            $ledger['quote_currency'] = $txn->quote_currency;
            $ledger['exchange_rate'] = $txn->exchange_rate;
        }
        unset($ledger);

        return collect($ledgers);
    }

    public function contact()
    {
        return $this->hasOne('Rutatiina\Contact\Models\Contact', 'id', 'contact_id');
    }

}
