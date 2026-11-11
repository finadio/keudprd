<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\MasterSetting;
use App\Models\Transaction;
use Illuminate\Http\Request;

class FiscalYearContext
{
    /**
     * Resolve the currently active fiscal year for a request.
     */
    public static function resolve(?Request $request = null): int
    {
        if ($request !== null) {
            $inputYear = $request->input('year');
            if (is_numeric($inputYear)) {
                $parsed = (int) $inputYear;
                if ($parsed >= 2020 && $parsed <= 2100) {
                    return $parsed;
                }
            }
        }

        $sessionYear = session('active_fiscal_year');
        if (is_numeric($sessionYear)) {
            $parsed = (int) $sessionYear;
            if ($parsed >= 2020 && $parsed <= 2100) {
                return $parsed;
            }
        }

        return self::defaultYear();
    }

    /**
     * Get the default active fiscal year from MasterSetting.
     */
    public static function defaultYear(): int
    {
        try {
            return (int) (MasterSetting::current()?->fiscal_year ?? 2026);
        } catch (\Throwable) {
            return 2026;
        }
    }

    /**
     * Get all distinct fiscal years available across transactions, periods, and settings.
     *
     * @return array<int, int>
     */
    public static function availableYears(): array
    {
        $years = [2026, self::defaultYear()];

        try {
            $txYears = Transaction::query()->distinct()->pluck('period_year')->toArray();
            $periodYears = AccountingPeriod::query()->distinct()->pluck('fiscal_year')->toArray();
            $settingYears = MasterSetting::query()->distinct()->pluck('fiscal_year')->toArray();
            $years = array_merge($years, $txYears, $periodYears, $settingYears);
        } catch (\Throwable) {
            // Fallback gracefully if tables are unavailable during setup
        }

        $sessionYear = session('active_fiscal_year');
        if (is_numeric($sessionYear)) {
            $years[] = (int) $sessionYear;
        }

        $clean = array_values(array_unique(array_filter(
            array_map('intval', $years),
            fn (int $y): bool => $y >= 2020 && $y <= 2100
        )));

        rsort($clean);

        return $clean;
    }

    /**
     * Ensure all 12 monthly accounting periods exist for the given fiscal year without altering existing records.
     */
    public static function ensurePeriodsExist(int $year): void
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        foreach ($monthNames as $monthNum => $monthLabel) {
            AccountingPeriod::firstOrCreate(
                ['fiscal_year' => $year, 'month' => $monthNum],
                ['name' => "{$monthLabel} {$year}", 'is_closed' => false]
            );
        }
    }
}
