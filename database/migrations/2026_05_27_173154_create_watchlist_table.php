<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->unique();
            $table->string('company_name');
            $table->string('exchange', 20)->default('US');
            $table->string('sector')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('active')->default(true);
            $table->decimal('alert_price_above', 12, 4)->nullable();
            $table->decimal('alert_price_below', 12, 4)->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist');
    }
};
