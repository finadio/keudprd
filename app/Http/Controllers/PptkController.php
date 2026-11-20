<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\PaguRealization;
use App\Models\Pptk;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PptkController extends Controller
{
    /**
     * Store a newly created PPTK in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:100',
            'code' => 'nullable|string|max:50|unique:pptks,code',
            'nip' => 'nullable|string|max:50',
            'subunit_id' => 'nullable|exists:subunits,id',
            'sheet_name' => 'nullable|string|max:50',
            'color_tag' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        // Auto-generate code if not provided
        if (empty($validated['code'])) {
            $baseCode = strtoupper(Str::slug($validated['display_name'] ?: $validated['name'], '_'));
            $candidate = $baseCode;
            $counter = 1;
            while (Pptk::where('code', $candidate)->exists()) {
                $candidate = "{$baseCode}_{$counter}";
                $counter++;
            }
            $validated['code'] = $candidate;
        } else {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        // Auto-fill sheet_name if empty
        if (empty($validated['sheet_name'])) {
            $validated['sheet_name'] = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $validated['display_name']);
        }

        // Auto sort_order if not provided
        if (! isset($validated['sort_order']) || $validated['sort_order'] === null) {
            $maxOrder = Pptk::max('sort_order') ?? 0;
            $validated['sort_order'] = $maxOrder + 1;
        }

        if (empty($validated['color_tag'])) {
            $palette = ['#04331a', '#0284c7', '#7c3aed', '#ea580c', '#0d9488', '#d97706', '#e11d48', '#0891b2'];
            $validated['color_tag'] = $palette[array_rand($palette)];
        }

        $validated['is_active'] = isset($validated['is_active']) ? (bool) $validated['is_active'] : true;

        $pptk = Pptk::create($validated);

        AuditLog::log('create', 'Pptk', $pptk->id, null, $pptk->toArray());

        return redirect()->back()->with('success', "PPTK baru '{$pptk->display_name}' ({$pptk->name}) berhasil ditambahkan!");
    }

    /**
     * Update the specified PPTK in storage.
     */
    public function update(Request $request, Pptk $pptk): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:pptks,code,'.$pptk->id,
            'nip' => 'nullable|string|max:50',
            'subunit_id' => 'nullable|exists:subunits,id',
            'sheet_name' => 'nullable|string|max:50',
            'color_tag' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = isset($validated['is_active']) ? (bool) $validated['is_active'] : $pptk->is_active;

        if (empty($validated['sheet_name'])) {
            $validated['sheet_name'] = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $validated['display_name']);
        }

        $oldData = $pptk->toArray();
        $pptk->update($validated);

        AuditLog::log('update', 'Pptk', $pptk->id, $oldData, $pptk->toArray());

        return redirect()->back()->with('success', "Data PPTK '{$pptk->display_name}' berhasil diperbarui!");
    }

    /**
     * Remove the specified PPTK from storage or safely deactivate if historical records exist.
     */
    public function destroy(Pptk $pptk): RedirectResponse
    {
        // Check historical accounting associations
        $hasTransactions = Transaction::where('pptk_id', $pptk->id)->exists();
        $hasPaguRealizations = PaguRealization::where('pptk_id', $pptk->id)->exists();
        $hasActivities = Activity::where('pptk_id', $pptk->id)->exists();
        $hasUsers = User::where('pptk_id', $pptk->id)->exists();

        $displayName = $pptk->display_name ?: $pptk->name;

        if ($hasTransactions || $hasPaguRealizations || $hasActivities || $hasUsers) {
            // Safe deactivation to preserve financial audit trail and multi-year reports
            $pptk->update(['is_active' => false]);

            AuditLog::log('deactivate', 'Pptk', $pptk->id, ['is_active' => true], ['is_active' => false]);

            $reasons = [];
            if ($hasTransactions) {
                $reasons[] = 'transaksi BKU';
            }
            if ($hasPaguRealizations) {
                $reasons[] = 'realisasi pagu';
            }
            if ($hasActivities) {
                $reasons[] = 'kegiatan anggaran';
            }
            if ($hasUsers) {
                $reasons[] = 'akun pengguna';
            }
            $reasonStr = implode(', ', $reasons);

            return redirect()->back()->with(
                'success',
                "PPTK '{$displayName}' memiliki riwayat {$reasonStr}. Demi menjaga keutuhan riwayat pembukuan periode lalu, status PPTK berhasil dinonaktifkan (diarsipkan) dan tidak akan muncul di input periode baru."
            );
        }

        $pptkData = $pptk->toArray();
        $pptk->delete();

        AuditLog::log('delete', 'Pptk', $pptk->id, $pptkData, null);

        return redirect()->back()->with('success', "PPTK '{$displayName}' berhasil dihapus permanen!");
    }

    /**
     * Toggle active status of a PPTK.
     */
    public function toggleActive(Pptk $pptk): RedirectResponse
    {
        $newStatus = ! $pptk->is_active;
        $pptk->update(['is_active' => $newStatus]);

        AuditLog::log(
            $newStatus ? 'activate' : 'deactivate',
            'Pptk',
            $pptk->id,
            ['is_active' => ! $newStatus],
            ['is_active' => $newStatus]
        );

        $statusLabel = $newStatus ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->back()->with(
            'success',
            "Status PPTK '{$pptk->display_name}' berhasil {$statusLabel}!"
        );
    }
}
