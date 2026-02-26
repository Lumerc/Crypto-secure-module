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
        // create_crypto_balances_table.php
        Schema::create('crypto_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10); // BTC, ETH, USDT
            $table->decimal('balance', 20, 8)->default(0);
            $table->decimal('locked_balance', 20, 8)->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'currency']); // У пользователя может быть только один баланс по валюте
            $table->index('currency');
        });

        // create_crypto_transactions_table.php
        Schema::create('crypto_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('crypto_balance_id')->constrained()->onDelete('cascade');
            $table->uuid('uuid')->unique();
            $table->string('currency', 10);
            $table->string('type'); // credit, debit, fee
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->decimal('amount', 20, 8);
            $table->decimal('fee', 20, 8)->default(0);
            $table->decimal('balance_before', 20, 8);
            $table->decimal('balance_after', 20, 8);
            $table->string('blockchain_tx_hash')->nullable();
            $table->string('from_address')->nullable();
            $table->string('to_address')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('status');
            $table->index('type');
            $table->index('blockchain_tx_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_balances');
        Schema::dropIfExists('crypto_transactions');
    }
};
