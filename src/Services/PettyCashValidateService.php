<?php

namespace Rutatiina\PettyCash\Services;

use Illuminate\Support\Facades\Validator;
use Rutatiina\Contact\Models\Contact;
use Rutatiina\PettyCash\Models\PettyCashSetting;

class PettyCashValidateService
{
    public static $errors = [];

    public static function run($requestInstance)
    {
        //$request = request(); //used for the flash when validation fails
        $user = auth()->user();


        // >> data validation >>------------------------------------------------------------

        //validate the data
        $customMessages = [
            'credit_financial_account_code.required' => "The petty cash source account field is required.",
        ];

        $rules = [
            'contact_id' => 'numeric|nullable',
            'date' => 'required|date',
            'debit_financial_account_code' => 'required',
            'credit_financial_account_code' => 'required',
            'base_currency' => 'required',
        ];

        $validator = Validator::make($requestInstance->all(), $rules, $customMessages);

        if ($validator->fails())
        {
            self::$errors = $validator->errors()->all();
            return false;
        }

        // << data validation <<-----------------------------------------------------------

        $debitAccount = PettyCashService::pettyCashAccount();


        $contact = Contact::find($requestInstance->contact_id);


        $data['id'] = $requestInstance->input('id', null); //for updating the id will always be posted
        $data['user_id'] = $user->id;
        $data['tenant_id'] = $user->tenant->id;
        $data['created_by'] = $user->name;
        $data['app'] = 'web';
        $data['number'] = $requestInstance->input('number');
        $data['date'] = $requestInstance->input('date');
        $data['debit_financial_account_code'] = $debitAccount->code; //$settings->financial_account_to_debit->code
        $data['credit_financial_account_code'] = $requestInstance->input('credit_financial_account_code'); //$settings->financial_account_to_credit->code
        $data['contact_id'] = $requestInstance->contact_id;
        $data['contact_name'] = optional($contact)->name;
        $data['contact_address'] = trim(optional($contact)->shipping_address_street1 . ' ' . optional($contact)->shipping_address_street2);
        $data['reference'] = $requestInstance->input('reference', null);
        $data['base_currency'] =  $requestInstance->input('base_currency');
        $data['quote_currency'] =  $requestInstance->input('quote_currency', $data['base_currency']);
        $data['exchange_rate'] = 1;//$requestInstance->input('exchange_rate', 1);
        $data['branch_id'] = $requestInstance->input('branch_id', null);
        $data['store_id'] = $requestInstance->input('store_id', null);
        $data['status'] = $requestInstance->input('status', null);
        $data['balances_where_updated'] = $requestInstance->input('balances_where_updated', null);
        $data['description'] = $requestInstance->input('description', null);
        $data['amount'] = $requestInstance->input('amount', 0);


        //DR ledger
        $data['ledgers'][] = [
            'financial_account_code' => $data['debit_financial_account_code'],
            'effect' => 'debit',
            'total' => $data['amount'],
            'contact_id' => $data['contact_id']
        ];

        //CR ledger
        $data['ledgers'][] = [
            'financial_account_code' => $data['credit_financial_account_code'],
            'effect' => 'credit',
            'total' => $data['amount'],
            'contact_id' => $data['contact_id']
        ];

        //print_r($data); exit;

        //Now add the default values to items and ledgers

        foreach ($data['ledgers'] as &$ledger)
        {
            $ledger['tenant_id'] = $data['tenant_id'];
            $ledger['date'] = date('Y-m-d', strtotime($data['date']));
            $ledger['base_currency'] = $data['base_currency'];
            $ledger['quote_currency'] = $data['quote_currency'];
            $ledger['exchange_rate'] = $data['exchange_rate'];
        }
        unset($ledger);

        //Return the array of txns
        //print_r($data); exit;

        return $data;

    }

}
