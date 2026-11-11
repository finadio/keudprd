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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->string('import_type'); // paste_text, pdf_upload, manual_entry, excel_historical
            $table->string('original_filename')->nullable();
            $table->longText('raw_content')->nullable();
            $table->unsignedInteger('parsed_count')->default(0);
            $table->string('status')->default('draft'); // draft, reviewed, posted, rejected
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->date('transaction_date');
            $table->string('document_number')->nullable();
            $table->text('description');

            // Transaction category
            $table->string('transaction_type');
            // sp2d_up, sp2d_ls, sp2d_tu, sp2d_gu, panjar_disbursement, panjar_return, debet_rekening, spj_realization, bank_interest, bank_tax, cash_draw, general

            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, cash_tunai, debit_card_giro

            // Exact amounts
            $table->decimal('amount_gross', 15, 2)->default(0);
            $table->decimal('amount_tax', 15, 2)->default(0);
            $table->decimal('amount_net', 15, 2)->default(0);

            // Debit or Credit direction for Cash/Bank
            // 'receipt' (Penerimaan) or 'disbursement' (Pengeluaran)
            $table->enum('flow_direction', ['receipt', 'disbursement'])->default('disbursement');

            $table->foreignId('pptk_id')->nullable()->constrained('pptks')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->string('vendor_name')->nullable();

            // Period fields
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year')->default(2026);

            // Verification flags
            $table->boolean('is_verified')->default(true);
            $table->string('status')->default('posted'); // draft, reviewed, verified, posted, void
            $table->text('notes')->nullable();

            // JSON meta for extra flexible flags (e.g. tag in bank book, contra partner id, source sheet)
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['period_year', 'period_month']);
            $table->index(['pptk_id']);
            $table->index(['transaction_type']);
            $table->index(['payment_method']);
        });

        Schema::create('transaction_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('pptk_id')->constrained('pptks')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('tax_type')->default('pph_23_2pct'); // pph_21, pph_22, pph_23_2pct, ppn_11, lainnya
            $table->decimal('tax_rate', 6, 4)->default(0.0200); // 2%
            $table->decimal('taxable_base', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->string('ntpn_billing')->nullable();
            $table->boolean('is_settled')->default(false);
            $table->timestamps();
        });

        Schema::create('manual_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pptk_id')->constrained('pptks')->cascadeOnDelete();
            $table->date('adjustment_date');
            $table->unsignedTinyInteger('period_month');
            $table->unsignedSmallInteger('period_year')->default(2026);
            $table->string('adjustment_type')->default('debet_reduction'); // debet_reduction, panjar_correction, tax_adjustment, general
            $table->decimal('amount', 15, 2); // can be positive or negative
            $table->text('reason');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('bku_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('reconciliation_status')->default('matched'); // matched, unmatched_bank_only, unmatched_bku_only, amount_mismatch, investigate
            $table->decimal('discrepancy_amount', 15, 2)->default(0);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // create, update, delete, post, void, import
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('manual_adjustments');
        Schema::dropIfExists('tax_deductions');
        Schema::dropIfExists('transaction_allocations');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('import_batches');
    }
};
