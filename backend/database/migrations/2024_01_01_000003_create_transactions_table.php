<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->nullOnDelete();
            $table->string('type', 20); // income, expense, transfer
            $table->bigInteger('amount'); // minor units
            $table->string('currency', 3)->default('IRR');
            $table->date('transaction_date')->index();
            $table->string('description', 500)->nullable();
            $table->string('reference', 50)->nullable()->unique();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'account_id', 'transaction_date']);
            $table->index(['account_id', 'transaction_date']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['account_id']);
            $table->dropForeign(['category_id']);
        });
        Schema::dropTable('transactions');
        Schema::enableForeignKeyConstraints();
    }
};
