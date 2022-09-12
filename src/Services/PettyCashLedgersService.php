<?php

namespace Rutatiina\PettyCash\Services;

use Rutatiina\PettyCash\Models\PettyCashLedger;

class PettyCashLedgersService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function store($data)
    {
        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['petty_cash_id'] = $data['id'];
            PettyCashLedger::create($ledger);
        }
        unset($ledger);

    }

}
