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
        Schema::create('pagu_realizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('period_year')->default(2026)->index();
            $table->foreignId('pptk_id')->constrained('pptks')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->date('transaction_date')->index();
            $table->enum('type', ['pencairan', 'pengembalian'])->default('pencairan')->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('recipient')->nullable();
            $table->text('description');
            $table->string('source_fund')->default('up_gu'); // up_gu, tu, ls, transfer, tunai
            $table->string('document_number')->nullable();
            $table->boolean('is_spj_submitted')->default(false)->index();
            $table->date('spj_submission_date')->nullable();
            $table->string('spj_document_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagu_realizations');
    }
};
