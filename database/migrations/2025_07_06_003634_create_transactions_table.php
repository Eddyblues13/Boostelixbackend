<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('trx_type', 10);
            $table->decimal('amount', 18, 8)->nullable();
            $table->decimal('charge', 18, 8)->nullable();
            $table->string('remarks', 191);
            $table->string('trx_id', 191);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('trx_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
