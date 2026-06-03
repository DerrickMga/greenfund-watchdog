<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->index();
            $table->decimal('price', 12, 4);
            $table->decimal('open', 12, 4)->nullable();
            $table->decimal('high', 12, 4)->nullable();
            $table->decimal('low', 12, 4)->nullable();
            $table->decimal('prev_close', 12, 4)->nullable();
            $table->decimal('change', 10, 4)->nullable();
            $table->decimal('change_percent', 8, 4)->nullable();
            $table->bigInteger('volume')->default(0);
            $table->decimal('rsi_14', 8, 4)->nullable();
            $table->decimal('macd', 10, 6)->nullable();
            $table->decimal('macd_signal', 10, 6)->nullable();
            $table->decimal('macd_hist', 10, 6)->nullable();
            $table->decimal('ema_9', 12, 4)->nullable();
            $table->decimal('ema_21', 12, 4)->nullable();
            $table->decimal('sma_50', 12, 4)->nullable();
            $table->decimal('bb_upper', 12, 4)->nullable();
            $table->decimal('bb_lower', 12, 4)->nullable();
            $table->decimal('atr', 10, 4)->nullable();
            $table->decimal('stoch_k', 8, 4)->nullable();
            $table->decimal('vwap', 12, 4)->nullable();
            $table->decimal('momentum_1m', 8, 4)->nullable();
            $table->decimal('momentum_5m', 8, 4)->nullable();
            $table->string('source', 20)->default('finnhub');
            $table->timestamp('captured_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
    }
};
