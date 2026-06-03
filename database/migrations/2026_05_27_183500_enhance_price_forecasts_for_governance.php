<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_forecasts', function (Blueprint $table) {
            $table->string('model_version', 24)->default('v1')->after('model_name');
            $table->string('model_role', 24)->default('champion')->after('model_version');
            $table->unsignedTinyInteger('quality_score')->nullable()->after('confidence');
            $table->string('volatility_regime', 16)->nullable()->after('quality_score');

            $table->decimal('realized_price', 12, 4)->nullable()->after('stop_loss_price');
            $table->decimal('realized_return_pct', 8, 4)->nullable()->after('realized_price');
            $table->decimal('abs_error_pct', 8, 4)->nullable()->after('realized_return_pct');
            $table->boolean('direction_hit')->nullable()->after('abs_error_pct');
            $table->timestamp('evaluated_at')->nullable()->after('generated_at')->index();

            $table->index(['ticker', 'horizon_days', 'model_role', 'is_active'], 'pf_ticker_horizon_role_active_idx');
            $table->index(['model_version', 'evaluated_at'], 'pf_model_evaluated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('price_forecasts', function (Blueprint $table) {
            $table->dropIndex('pf_ticker_horizon_role_active_idx');
            $table->dropIndex('pf_model_evaluated_idx');

            $table->dropColumn([
                'model_version',
                'model_role',
                'quality_score',
                'volatility_regime',
                'realized_price',
                'realized_return_pct',
                'abs_error_pct',
                'direction_hit',
                'evaluated_at',
            ]);
        });
    }
};
