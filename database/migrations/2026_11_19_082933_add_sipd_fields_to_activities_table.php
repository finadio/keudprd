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
        Schema::table('activities', function (Blueprint $table) {
            $table->string('program_code')->nullable()->after('name');
            $table->string('program_name')->nullable()->after('program_code');
            $table->decimal('spj_ls_gaji', 15, 2)->default(0)->after('pagu_budget');
            $table->decimal('spj_ls_barjas', 15, 2)->default(0)->after('spj_ls_gaji');
            $table->decimal('spj_up_gu_tu', 15, 2)->default(0)->after('spj_ls_barjas');
            $table->decimal('spj_sipd_total', 15, 2)->default(0)->after('spj_up_gu_tu');
            $table->decimal('sisa_pagu_sipd', 15, 2)->default(0)->after('spj_sipd_total');
            $table->unsignedSmallInteger('period_year')->default(2026)->after('sisa_pagu_sipd');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'program_code',
                'program_name',
                'spj_ls_gaji',
                'spj_ls_barjas',
                'spj_up_gu_tu',
                'spj_sipd_total',
                'sisa_pagu_sipd',
                'period_year',
            ]);
        });
    }
};
