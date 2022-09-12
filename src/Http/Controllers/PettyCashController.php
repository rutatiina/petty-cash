<?php

namespace Rutatiina\PettyCash\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Rutatiina\PettyCash\Models\PettyCashEntry;
use Rutatiina\PettyCash\Models\PettyCashSetting;
use Rutatiina\FinancialAccounting\Models\Account;
use Rutatiina\PettyCash\Services\PettyCashService;
use Illuminate\Support\Facades\Request as FacadesRequest;

class PettyCashController extends Controller
{

    // >> get the item attributes template << !!important

    public function __construct()
    {
        // $this->middleware('permission:expenses.view');
        // $this->middleware('permission:expenses.create', ['only' => ['create', 'store']]);
        // $this->middleware('permission:expenses.update', ['only' => ['edit', 'update']]);
        // $this->middleware('permission:expenses.delete', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $query = PettyCashEntry::query();

        if ($request->contact)
        {
            $query->where(function ($q) use ($request)
            {
                $q->where('contact_id', $request->contact);
            });
        }

        $txns = $query->latest()->paginate($request->input('per_page', 20));

        $txns->load('debit_account', 'credit_account');

        return [
            'tableData' => $txns
        ];
    }

    public function create()
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }


        $debitAccount = PettyCashService::pettyCashAccount();

        $tenant = Auth::user()->tenant;

        $txnAttributes = (new PettyCashEntry())->rgGetAttributes();

        $txnAttributes['number'] = PettyCashService::nextNumber();
        $txnAttributes['status'] = 'approved';
        $txnAttributes['contact_id'] = '';
        $txnAttributes['contact'] = json_decode('{"currencies":[]}'); #required
        $txnAttributes['date'] = date('Y-m-d');
        $txnAttributes['base_currency'] = $tenant->base_currency;
        $txnAttributes['quote_currency'] = $tenant->base_currency;
        $txnAttributes['debit_financial_account_code'] = $debitAccount->code;
        $txnAttributes['credit_financial_account_code'] = null;

        return [
            'pageTitle' => 'Add Petty cash', #required
            'pageAction' => 'Record', #required
            'txnUrlStore' => '/petty-cash', #required
            'txnAttributes' => $txnAttributes, #required
        ];
    }

    public function store(Request $request)
    {
        // return $request;

        $storeService = PettyCashService::store($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => PettyCashService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Petty cash debited'],
            'number' => 0,
            'callback' => '/petty-cash' //URL::route('petty-cash.show', [$storeService->id], false)
        ];

    }

    public function show($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txn = PettyCashEntry::findOrFail($id);
        $txn->load('contact');
        $txn->setAppends([
            'taxes',
            'number_string',
            'total_in_words',
        ]);

        return $txn->toArray();
    }

    public function edit($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = PettyCashService::edit($id);

        $data = [
            'pageTitle' => 'Edit petty cash record', #required
            'pageAction' => 'Edit', #required
            'txnUrlStore' => '/petty-cash/' . $id, #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    public function update(Request $request)
    {
        //editing an expense is not currently allowed
        //return redirect()->back();

        $storeService = PettyCashService::update($request);

        if ($storeService == false)
        {
            return [
                'status' => false,
                'messages' => PettyCashService::$errors
            ];
        }

        return [
            'status' => true,
            'messages' => ['Petty cash updated'],
            'callback' => URL::route('petty-cash.show', [$storeService->id], false)
        ];
    }

    public function destroy($id)
    {
        $destroy = PettyCashService::destroy($id);

        if ($destroy)
        {
            return [
                'status' => true,
                'messages' => ['Petty cash record deleted'],
                'callback' => URL::route('petty-cash.index', [], false)
            ];
        }
        else
        {
            return [
                'status' => false,
                'messages' => PettyCashService::$errors
            ];
        }
    }

    #-----------------------------------------------------------------------------------

    public function approve($id)
    {
        $approve = PettyCashService::approve($id);

        if ($approve == false)
        {
            return [
                'status' => false,
                'messages' => PettyCashService::$errors,
            ];
        }

        return [
            'status' => true,
            'messages' => ['Petty cash record approved'],
        ];

    }

    public function copy($id)
    {
        //load the vue version of the app
        if (!FacadesRequest::wantsJson())
        {
            return view('ui.limitless::layout_2-ltr-default.appVue');
        }

        $txnAttributes = PettyCashService::copy($id);

        $data = [
            'pageTitle' => 'Copy PettyCash', #required
            'pageAction' => 'Copy', #required
            'txnUrlStore' => '/petty-cash', #required
            'txnAttributes' => $txnAttributes, #required
        ];

        return $data;
    }

    //CR account / Account which is the source of the petty cash
    public function creditAccounts()
    {
        $debitAccount = PettyCashService::pettyCashAccount();

        return Account::select(['code', 'name', 'type'])
        ->whereIn('type', ['asset', 'equity'])
        ->where('code', '!=', $debitAccount->code)
        ->orderBy('name', 'asc')
        ->limit(100)
        ->get()
        ->each->setAppends([])
        ->groupBy('type');
    }

}
