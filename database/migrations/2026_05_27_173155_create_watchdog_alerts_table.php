<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchdog_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->index();
            $table->enum('type', [
                'PRICE_SPIKE', 'PRICE_DROP', 'RSI_OVERBOUGHT', 'RSI_OVERSOLD',
                'MACD_CROSSOVER', 'BREAKOUT', 'VOLUME_SURGE', 'SIGNAL_CHANGE'
            ]);
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL'])->default('INFO');
            $table->string('title');
            $table->text('message');
            $table->decimal('trigger_value', 12, 4)->nullable();
            $table->decimal('threshold_value', 12, 4)->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('alerted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchdog_alerts');
    }
};
