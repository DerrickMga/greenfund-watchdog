<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->index();
            $table->string('model_name', 64)->default('trend_regression_v1');
            $table->unsignedTinyInteger('horizon_days')->default(7);
            $table->decimal('current_price', 12, 4);
            $table->decimal('predicted_price', 12, 4);
            $table->decimal('expected_return_pct', 8, 4);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->enum('recommendation', ['BUY', 'HOLD', 'WATCH', 'RELEASE'])->default('HOLD');
            $table->decimal('entry_price', 12, 4)->nullable();
            $table->decimal('take_profit_price', 12, 4)->nullable();
            $table->decimal('stop_loss_price', 12, 4)->nullable();
            $table->date('forecast_for');
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->json('features')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['ticker', 'horizon_days', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_forecasts');
    }
};
