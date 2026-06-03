<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->index();
            $table->enum('action', ['BUY', 'HOLD', 'SELL', 'WATCH']);
            $table->enum('strength', ['STRONG', 'MODERATE', 'WEAK'])->default('MODERATE');
            $table->unsignedTinyInteger('confidence')->default(50); // 0-100
            $table->decimal('price_at_signal', 12, 4);
            $table->decimal('target_price', 12, 4)->nullable();
            $table->decimal('stop_loss', 12, 4)->nullable();
            $table->text('reasoning');
            $table->json('indicators_snapshot')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signals');
    }
};
