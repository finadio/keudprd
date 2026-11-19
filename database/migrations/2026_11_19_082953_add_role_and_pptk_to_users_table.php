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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('operator')->after('email'); // superadmin, bendahara, kasubag, pptk, operator
            $table->foreignId('pptk_id')->nullable()->after('role')->constrained('pptks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['pptk_id']);
            $table->dropColumn(['role', 'pptk_id']);
        });
    }
};
