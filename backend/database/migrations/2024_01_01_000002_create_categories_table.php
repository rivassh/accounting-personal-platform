<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20); // income, expense
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->integer('level')->default(0);
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable(); // hex color
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'is_active']);
            $table->index('parent_id');
            $table->index(['user_id', 'type']);
        });
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropTable('categories');
        Schema::enableForeignKeyConstraints();
    }
};