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
        Schema::create('master_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->default(2026);
            $table->string('skpd_name')->default('SETWAN KABUPATEN BANYUMAS');
            $table->string('pa_kpa_name')->default('ARIF TRIYANTO, S.Sos. M.Eng.');
            $table->string('pa_kpa_nip')->nullable();
            $table->string('treasurer_name')->default('INDAH SETIYOWATI, A.Md.AB.');
            $table->string('treasurer_nip')->nullable();
            $table->date('effective_start_date')->nullable();
            $table->date('effective_end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subunits', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pptks', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('display_name');
            $table->string('nip')->nullable();
            $table->foreignId('subunit_id')->nullable()->constrained('subunits')->nullOnDelete();
            $table->string('sheet_name')->nullable();
            $table->string('color_tag')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->foreignId('pptk_id')->nullable()->constrained('pptks')->nullOnDelete();
            $table->decimal('pagu_budget', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->default(2026);
            $table->unsignedTinyInteger('month'); // 1 - 12
            $table->string('name'); // e.g. "Januari 2026"
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('pptks');
        Schema::dropIfExists('subunits');
        Schema::dropIfExists('master_settings');
    }
};
