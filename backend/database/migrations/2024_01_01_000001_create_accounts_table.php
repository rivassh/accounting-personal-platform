<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20)->default('bank'); // bank, cash, credit_card, wallet, investment
            $table->string('currency', 3)->default('IRR'); // IRR, USD, EUR, GBP
            $table->bigInteger('initial_balance')->default(0); // minor units
            $table->bigInteger('current_balance')->default(0); // minor units
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('card_number', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'currency']);
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::dropTable('accounts');
        Schema::enableForeignKeyConstraints();
    }
};