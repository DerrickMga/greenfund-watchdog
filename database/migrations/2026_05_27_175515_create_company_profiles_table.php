<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->unique();
            $table->string('name')->nullable();
            $table->string('logo')->nullable();
            $table->string('exchange')->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('industry')->nullable();
            $table->string('sector')->nullable();
            $table->decimal('market_cap', 20, 4)->nullable();
            $table->bigInteger('shares_outstanding')->nullable();
            $table->date('ipo_date')->nullable();
            $table->string('weburl')->nullable();
            $table->string('phone')->nullable();
            // Financials
            $table->decimal('pe_ttm', 12, 4)->nullable();
            $table->decimal('eps_ttm', 12, 4)->nullable();
            $table->decimal('revenue_ttm', 20, 4)->nullable();
            $table->decimal('beta', 8, 4)->nullable();
            $table->decimal('div_yield', 8, 4)->nullable();
            $table->decimal('pb_ratio', 10, 4)->nullable();
            $table->decimal('ps_ratio', 10, 4)->nullable();
            $table->decimal('roe', 10, 4)->nullable();
            $table->decimal('roi', 10, 4)->nullable();
            $table->decimal('current_ratio', 10, 4)->nullable();
            $table->decimal('debt_equity', 10, 4)->nullable();
            $table->decimal('week52_high', 12, 4)->nullable();
            $table->decimal('week52_low', 12, 4)->nullable();
            $table->decimal('avg_volume_10d', 20, 2)->nullable();
            $table->decimal('avg_volume_3m', 20, 2)->nullable();
            // Analyst consensus
            $table->integer('analyst_buy')->nullable();
            $table->integer('analyst_hold')->nullable();
            $table->integer('analyst_sell')->nullable();
            $table->decimal('price_target_high', 12, 4)->nullable();
            $table->decimal('price_target_low', 12, 4)->nullable();
            $table->decimal('price_target_mean', 12, 4)->nullable();
            $table->decimal('price_target_median', 12, 4)->nullable();
            // Next earnings
            $table->date('next_earnings_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
