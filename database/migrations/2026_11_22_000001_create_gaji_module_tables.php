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
        // 1. Tabel Konfigurasi & Tarif Acuan Gaji/Tunjangan
        Schema::create('gaji_configs', function (Blueprint $table) {
            $table->id();
            $table->string('config_key', 100)->unique();
            $table->string('category', 50); // asn, dprd, umum
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('config_value'); // nominal, persentase, atau tabel matriks (PP/Perpres/Perbup)
            $table->string('legal_reference', 200)->nullable(); // dasar hukum (mis. PP 5/2024, Perbup 104/2025)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Tabel Alokasi Kontrol Anggaran (Pagu Gaji & Tunjangan)
        Schema::create('gaji_budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->integer('fiscal_year');
            $table->string('category', 50); // asn, dprd, all
            $table->string('budget_name', 200); // mis. Belanja Gaji dan Tunjangan DPRD
            $table->string('account_code', 50)->nullable(); // kode rekening anggaran
            $table->decimal('annual_pagu', 18, 2)->default(0);
            $table->decimal('monthly_allocation', 18, 2)->default(0);
            $table->decimal('realized_amount', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['fiscal_year', 'category']);
        });

        // 3. Tabel Master Penerima (ASN & DPRD)
        Schema::create('gaji_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('identifier_number', 50)->nullable(); // NIP (PNS/PPPK) atau No Registrasi
            $table->enum('category', ['asn', 'dprd']);
            $table->enum('sub_category', [
                'pns',
                'pppk_penuh_waktu',
                'pppk_paruh_waktu',
                'ketua_dprd',
                'wakil_ketua_dprd',
                'anggota_dprd',
            ]);
            $table->string('position_name', 150); // Jabatan: mis. Sekretaris DPRD, Anggota Komisi
            $table->string('grade', 20)->nullable(); // Golongan: IV/c, III/d, IX, V, dll
            $table->integer('mkg_years')->default(0); // Masa Kerja Golongan (Tahun)
            $table->integer('mkg_months')->default(0); // Masa Kerja Golongan (Bulan)
            $table->integer('job_class')->nullable(); // Kelas Jabatan (1 - 14) untuk TPP PNS
            $table->string('political_party', 100)->nullable(); // Partai Politik (khusus DPRD)
            $table->enum('marital_status', ['single', 'married', 'widowed'])->default('married');
            $table->integer('children_count')->default(0); // Jumlah anak tertanggung (max 2)
            $table->enum('akd_role', ['none', 'ketua', 'wakil_ketua', 'sekretaris', 'anggota'])->default('none');
            $table->string('akd_name', 100)->nullable(); // Nama AKD (Komisi 1, Bamus, Banggar, dll)
            $table->boolean('has_official_residence')->default(false); // Dapat rumah dinas (true = tidak dapat tunj perumahan)
            $table->boolean('has_official_car')->default(false); // Dapat mobil dinas (true = tidak dapat tunj transportasi)
            $table->decimal('tpp_base_amount', 15, 2)->default(0); // TPP dasar sesuai kelas jabatan
            $table->decimal('fixed_salary', 15, 2)->nullable(); // Gaji tetap (misal PPPK paruh waktu bila disepakati)
            $table->json('custom_allowances')->nullable(); // Komponen tunjangan tambahan custom
            $table->json('custom_deductions')->nullable(); // Potongan pinjaman / koperasi
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_name', 50)->default('Bank Jateng');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'sub_category', 'is_active']);
        });

        // 4. Tabel Periode Penggajian Bulanan
        Schema::create('gaji_payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->integer('period_year');
            $table->integer('period_month');
            $table->string('period_title', 150); // mis. Penggajian ASN & DPRD Bulan November 2026
            $table->enum('status', ['draft', 'simulated', 'approved', 'paid'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->decimal('total_asn_gross', 18, 2)->default(0);
            $table->decimal('total_dprd_gross', 18, 2)->default(0);
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_netto', 18, 2)->default(0);
            $table->decimal('available_budget', 18, 2)->default(0);
            $table->decimal('budget_difference', 18, 2)->default(0); // Sisa atau Defisit Anggaran
            $table->boolean('is_budget_sufficient')->default(true);
            $table->json('budget_diagnostic')->nullable(); // Rekomendasi & rincian penyebab selisih
            $table->dateTime('simulated_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_year', 'period_month']);
        });

        // 5. Tabel Item Rincian Gaji & Tunjangan per Orang per Periode
        Schema::create('gaji_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('gaji_payroll_periods')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('gaji_recipients')->cascadeOnDelete();
            $table->enum('category', ['asn', 'dprd']);
            $table->string('sub_category', 50);

            // Komponen Dasar
            $table->decimal('basic_salary_or_rep', 15, 2)->default(0); // Gaji Pokok (ASN) atau Uang Representasi (DPRD)
            $table->decimal('package_allowance', 15, 2)->default(0); // Uang Paket (DPRD 10%)
            $table->decimal('position_allowance', 15, 2)->default(0); // Tunjangan Jabatan (ASN / DPRD 145%)
            $table->decimal('family_allowance', 15, 2)->default(0); // Tunjangan Keluarga (Istri/Suami & Anak)
            $table->decimal('rice_allowance', 15, 2)->default(0); // Tunjangan Pangan / Beras

            // Komponen Khusus DPRD
            $table->decimal('akd_allowance', 15, 2)->default(0); // Tunjangan AKD
            $table->decimal('tki_allowance', 15, 2)->default(0); // Tunjangan Komunikasi Intensif
            $table->decimal('housing_allowance', 15, 2)->default(0); // Tunjangan Perumahan
            $table->decimal('transport_allowance', 15, 2)->default(0); // Tunjangan Transportasi
            $table->decimal('reses_allowance', 15, 2)->default(0); // Tunjangan Reses (jika periode reses)

            // Komponen Khusus ASN
            $table->decimal('tpp_amount', 15, 2)->default(0); // TPP Hasil Kinerja & Disiplin
            $table->decimal('productivity_pct', 5, 2)->default(100); // % Produktivitas
            $table->decimal('discipline_pct', 5, 2)->default(100); // % Disiplin
            $table->decimal('other_allowance', 15, 2)->default(0); // Tunjangan Lainnya

            // Subtotal Penghasilan Bruto
            $table->decimal('gross_amount', 15, 2)->default(0);

            // Komponen Potongan
            $table->decimal('deduction_iwp', 15, 2)->default(0); // IWP 1%
            $table->decimal('deduction_bpjs', 15, 2)->default(0); // BPJS Kes / JKK / JKM
            $table->decimal('deduction_pph21', 15, 2)->default(0); // Pajak PPh 21
            $table->decimal('deduction_other', 15, 2)->default(0); // Potongan pinjaman / lainnya
            $table->decimal('total_deductions', 15, 2)->default(0);

            // Penghasilan Bersih (Take Home Pay)
            $table->decimal('net_amount', 15, 2)->default(0);

            $table->json('calculation_meta')->nullable(); // Rincian formula dan audit trail perhitungan
            $table->timestamps();

            $table->index(['period_id', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gaji_payroll_items');
        Schema::dropIfExists('gaji_payroll_periods');
        Schema::dropIfExists('gaji_recipients');
        Schema::dropIfExists('gaji_budget_allocations');
        Schema::dropIfExists('gaji_configs');
    }
};
