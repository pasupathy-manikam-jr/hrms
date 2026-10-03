<?php

namespace App\Support\Statutory;

/**
 * PCB / Monthly Tax Deduction (MTD) by LHDN's "Specification for MTD Calculations Using Computerised Calculation
 * for 2026", formula 1 (normal remuneration):
 *
 *   MTD = [ (P − M) × R + B − (Z + X) ] / (n + 1)
 *   P   = Σ(Y − K) + (Y1 − K1) + (Y2 − K2) × n − (D + S + Q × C + ΣLP + LP1)
 *
 * Calculations keep two decimals (further digits are dropped); the result is rounded up to the next 5 sen and is
 * not deducted when below RM10. Non-residents pay a flat 30%. Amounts in and out are in sen.
 *
 * ponytail: no zakat, disability reliefs or the additional-remuneration (bonus) formula yet; add them with the
 * payroll inputs that would carry them.
 */
class Pcb
{
    /** Category 1: single. Category 2: married, spouse not working. Category 3: married with working spouse, divorced or widowed. */
    public const CATEGORIES = [1, 2, 3];

    private const INDIVIDUAL_RELIEF = 9000;

    private const SPOUSE_RELIEF = 4000;

    private const CHILD_RELIEF = 2000;

    /** Yearly EPF contributions that qualify for relief (the "total qualifying amount per year"). */
    private const EPF_RELIEF_LIMIT = 4000;

    /** Table 1: lower bound of P, M, R (%), B for categories 1 & 3, B for category 2. */
    private const TABLE = [
        [2000001, 2000000, 30, 528400, 528400],
        [600001, 600000, 28, 136400, 136400],
        [400001, 400000, 26, 84400, 84400],
        [100001, 100000, 25, 9400, 9400],
        [70001, 70000, 19, 3700, 3700],
        [50001, 50000, 11, 1500, 1500],
        [35001, 35000, 6, 600, 600],
        [20001, 20000, 3, -250, -650],
        [5001, 5000, 1, -400, -800],
    ];

    /**
     * MTD for the current month.
     *
     * @param  int  $month  1-12
     * @param  int  $grossToDate  Y: gross remuneration paid earlier this year (sen)
     * @param  int  $epfToDate  K: employee EPF paid earlier this year (sen)
     * @param  int  $pcbToDate  X: MTD paid earlier this year (sen)
     * @param  int  $gross  Y1: this month's gross remuneration (sen)
     * @param  int  $epf  K1: this month's employee EPF (sen)
     * @param  int  $children  C: child relief units
     * @param  int  $otherReliefs  ΣLP + LP1: other allowable deductions claimed this year, e.g. on Form TP1 (sen)
     */
    public static function monthly(
        int $month, int $category, int $children, bool $resident,
        int $grossToDate, int $epfToDate, int $pcbToDate, int $gross, int $epf, int $otherReliefs = 0,
    ): int {
        if (! $resident) {
            return self::finalise($gross * 0.30 / 100);
        }

        $n = 12 - $month;
        $limit = self::EPF_RELIEF_LIMIT;
        $k = min($epfToDate / 100, $limit);
        $k1 = min($epf / 100, $limit - $k);
        $k2 = $n > 0 ? min($k1, self::truncate(($limit - ($k + $k1)) / $n)) : 0;
        $y1 = $gross / 100;

        $reliefs = self::INDIVIDUAL_RELIEF
            + ($category === 2 ? self::SPOUSE_RELIEF : 0)
            + ($category === 1 ? 0 : self::CHILD_RELIEF * $children)
            + $otherReliefs / 100;

        $chargeable = self::truncate(($grossToDate / 100 - $k) + ($y1 - $k1) + self::truncate(($y1 - $k2) * $n) - $reliefs);

        return self::finalise((self::tax($chargeable, $category) - $pcbToDate / 100) / ($n + 1));
    }

    /**
     * (P − M) × R + B for the chargeable income's band; nil up to RM5,000.
     */
    private static function tax(float $chargeable, int $category): float
    {
        foreach (self::TABLE as [$from, $m, $rate, $b, $bSpouse]) {
            if ($chargeable >= $from) {
                return self::truncate(($chargeable - $m) * $rate / 100) + ($category === 2 ? $bSpouse : $b);
            }
        }

        return 0;
    }

    /**
     * Two decimals, rounded up to the next 5 sen; nothing is deducted below RM10. Returns sen.
     */
    private static function finalise(float $amount): int
    {
        $sen = (int) floor(round($amount * 100, 6));
        $sen = (int) ceil($sen / 5) * 5;

        return $sen < 1000 ? 0 : $sen;
    }

    private static function truncate(float $value): float
    {
        return floor(round($value * 100, 6)) / 100;
    }
}
