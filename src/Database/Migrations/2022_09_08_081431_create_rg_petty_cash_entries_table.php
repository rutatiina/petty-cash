<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRgPettyCashEntriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('tenant')->create('rg_petty_cash_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();

            //>> default columns
            $table->softDeletes();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            //<< default columns

            //>> table columns
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('app_id');
            $table->string('document_name', 50)->default('Expense');
            $table->string('number', 250);
            $table->date('date');
            $table->time('time');
            $table->unsignedBigInteger('debit_financial_account_code')->nullable();
            $table->unsignedBigInteger('credit_financial_account_code')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('contact_name', 50)->nullable();
            $table->string('contact_address', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('base_currency', 3);
            $table->string('quote_currency', 3);
            $table->unsignedDecimal('exchange_rate', 20,10)->default(1);
            $table->unsignedDecimal('amount', 20, 5);
            $table->string('description', 250)->nullable();
            $table->string('status', 20)->nullable();
            $table->unsignedTinyInteger('sent')->nullable();
            $table->boolean('balances_where_updated')->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('tenant')->dropIfExists('rg_petty_cash_entries');
    }
}
