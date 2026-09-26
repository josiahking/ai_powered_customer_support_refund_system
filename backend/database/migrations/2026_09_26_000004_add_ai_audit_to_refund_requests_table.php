<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->string('ai_status', 24)->default('NOT_ANALYZED');
            $table->json('ai_analysis')->nullable();
            $table->string('ai_provider', 64)->nullable();
            $table->string('ai_model', 128)->nullable();
            $table->string('ai_error_code', 64)->nullable();
            $table->string('policy_outcome', 16)->nullable();
            $table->string('resolution_reason_code', 64)->nullable();
            $table->text('resolution_explanation')->nullable();
        });

        DB::table('refund_requests')->update([
            'policy_outcome' => DB::raw('status'),
            'resolution_reason_code' => DB::raw('policy_reason_code'),
            'resolution_explanation' => DB::raw('policy_explanation'),
        ]);
    }

    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropColumn([
                'ai_status',
                'ai_analysis',
                'ai_provider',
                'ai_model',
                'ai_error_code',
                'policy_outcome',
                'resolution_reason_code',
                'resolution_explanation',
            ]);
        });
    }
};
