<?php

namespace Rutatiina\PettyCash\Services;

use Rutatiina\Tax\Models\Tax;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Rutatiina\PettyCash\Models\PettyCashEntry;
use Rutatiina\PettyCash\Models\PettyCashLedger;
use Rutatiina\FinancialAccounting\Models\Account;
use Rutatiina\FinancialAccounting\Services\AccountBalanceUpdateService;
use Rutatiina\FinancialAccounting\Services\ContactBalanceUpdateService;

class PettyCashService
{
    public static $errors = [];

    public function __construct()
    {
        //
    }

    public static function pettyCashAccount()
    {
        return Account::firstOrCreate([
            'code' => 110500,
            'tenant_id' => Auth::user()->tenant->id,
        ], 
        [
            'name' => 'Petty cash',
            'type' => 'asset',
            'financial_account_category_code' => 110000,
        ]);
    }

    public static function nextNumber()
    {
        $count = PettyCashEntry::count();
        return (str_pad(($count + 1), 5, "0", STR_PAD_LEFT));
    }

    public static function edit($id)
    {
        $taxes = Tax::all()->keyBy('code');

        $txn = PettyCashEntry::findOrFail($id);
        $txn->load('contact');

        $attributes = $txn->toArray();

        //print_r($attributes); exit;

        $attributes['_method'] = 'PATCH';

        $attributes['contact']['currency'] = $txn->contact->currency_and_exchange_rate;
        $attributes['contact']['currencies'] = $txn->contact->currencies_and_exchange_rates;

        $attributes['taxes'] = json_decode('{}');

        $attributes['amount'] = floatval($attributes['amount']); #required

        return $attributes;
    }

    public static function store($requestInstance)
    {
        $data = PettyCashValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = PettyCashValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = new PettyCashEntry;
            $Txn->tenant_id = $data['tenant_id'];
            $Txn->created_by = Auth::id();
            $Txn->number = $data['number'];
            $Txn->date = $data['date'];
            $Txn->debit_financial_account_code = $data['debit_financial_account_code'];
            $Txn->credit_financial_account_code = $data['credit_financial_account_code'];
            $Txn->contact_id = $data['contact_id'];
            $Txn->contact_name = $data['contact_name'];
            $Txn->contact_address = $data['contact_address'];
            $Txn->reference = $data['reference'];
            $Txn->base_currency = $data['base_currency'];
            $Txn->quote_currency = $data['quote_currency'];
            $Txn->exchange_rate = $data['exchange_rate'];
            $Txn->amount = $data['amount'];
            // $Txn->payment_mode = $data['payment_mode'];
            $Txn->branch_id = $data['branch_id'];
            $Txn->store_id = $data['store_id'];
            $Txn->status = $data['status'];
            $Txn->description = $data['description'];

            $Txn->save();

            $data['id'] = $Txn->id;


            //Save the ledgers >> $data['ledgers']; and update the balances
            $Txn->ledgers()->createMany($data['ledgers']);

            //$Txn->refresh(); //make the ledgers relationship infor available

            //update financial account and contact balances accordingly
            PettyCashApprovalService::run($Txn);

            DB::connection('tenant')->commit();

            return $Txn;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to save petty cash entries to database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to save petty cash entries to database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to save petty cash entries to database. Please contact Admin';
            }

            return false;
        }
        //*/

    }

    public static function update($requestInstance)
    {
        $data = PettyCashValidateService::run($requestInstance);
        //print_r($data); exit;
        if ($data === false)
        {
            self::$errors = PettyCashValidateService::$errors;
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = PettyCashEntry::with('ledgers')->findOrFail($data['id']);

            if ($Txn->status == 'approved')
            {
                self::$errors[] = 'Approved expense cannot be not be edited';
                return false;
            }

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn->toArray(), true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn->toArray(), true);

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->delete();

            $txnStore = self::store($requestInstance);

            DB::connection('tenant')->commit();

            return $txnStore;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to petty cash entries estimate in database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to update petty cash entries in database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to update petty cash entries in database. Please contact Admin';
            }

            return false;
        }

    }

    public static function destroy($id)
    {
        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn = PettyCashEntry::with('ledgers')->findOrFail($id);

            //reverse the account balances
            AccountBalanceUpdateService::doubleEntry($Txn, true);

            //reverse the contact balances
            ContactBalanceUpdateService::doubleEntry($Txn, true);

            //Delete affected relations
            $Txn->ledgers()->delete();
            $Txn->delete();

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Throwable $e)
        {
            DB::connection('tenant')->rollBack();

            Log::critical('Fatal Internal Error: Failed to delete petty cash entries from database');
            Log::critical($e);

            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'Error: Failed to delete petty cash entries from database.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to delete petty cash entries from database. Please contact Admin';
            }

            return false;
        }
    }

    public static function approve($id)
    {
        $Txn = PettyCashEntry::with(['ledgers'])->findOrFail($id);

        if (strtolower($Txn->status) != 'draft')
        {
            self::$errors[] = $Txn->status . ' transaction cannot be approved';
            return false;
        }

        //start database transaction
        DB::connection('tenant')->beginTransaction();

        try
        {
            $Txn->status = 'approved';
            PettyCashApprovalService::run($Txn);

            DB::connection('tenant')->commit();

            return true;

        }
        catch (\Exception $e)
        {
            DB::connection('tenant')->rollBack();
            //print_r($e); exit;
            if (App::environment('local'))
            {
                self::$errors[] = 'DB Error: Failed to approve petty cash entries.';
                self::$errors[] = 'File: ' . $e->getFile();
                self::$errors[] = 'Line: ' . $e->getLine();
                self::$errors[] = 'Message: ' . $e->getMessage();
            }
            else
            {
                self::$errors[] = 'Fatal Internal Error: Failed to approve petty cash entries.';
            }

            return false;
        }
    }

}
