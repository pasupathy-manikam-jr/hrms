<?php

namespace App\Support\Statutory;

use Carbon\CarbonInterface;

/**
 * SOCSO (Employees' Social Security Act 1969, Act 4) and EIS (Employment Insurance System Act 2017, Act 800)
 * contributions, from PERKESO's official wage-band tables (wage ceiling RM6,000 since 1 October 2024).
 * Amounts are in sen.
 */
class Socso
{
    /** Wages above this are contributed on as if they were exactly this (sen). */
    public const WAGE_CEILING = 600000;

    /** Lindung 24 Jam (SKBBK, non-employment injury) employee contributions start with June 2026 wages. */
    public const SKBBK_START = '2026-06-01';

    /**
     * PERKESO "New contribution rate including SKBBK": band upper limit, First Category employer share,
     * First Category employee share (invalidity), employee SKBBK share, Second Category employer share.
     * Wages above the last band pay the last band's amounts.
     */
    private const TABLE = [
        [3000, 40, 10, 20, 30],
        [5000, 70, 20, 30, 50],
        [7000, 110, 30, 50, 80],
        [10000, 150, 40, 65, 110],
        [14000, 210, 60, 90, 150],
        [20000, 295, 85, 125, 210],
        [30000, 435, 125, 185, 310],
        [40000, 615, 175, 265, 440],
        [50000, 785, 225, 335, 560],
        [60000, 965, 275, 415, 690],
        [70000, 1135, 325, 485, 810],
        [80000, 1315, 375, 565, 940],
        [90000, 1485, 425, 635, 1060],
        [100000, 1665, 475, 715, 1190],
        [110000, 1835, 525, 785, 1310],
        [120000, 2015, 575, 865, 1440],
        [130000, 2185, 625, 935, 1560],
        [140000, 2365, 675, 1015, 1690],
        [150000, 2535, 725, 1085, 1810],
        [160000, 2715, 775, 1165, 1940],
        [170000, 2885, 825, 1235, 2060],
        [180000, 3065, 875, 1315, 2190],
        [190000, 3235, 925, 1385, 2310],
        [200000, 3415, 975, 1465, 2440],
        [210000, 3585, 1025, 1535, 2560],
        [220000, 3765, 1075, 1615, 2690],
        [230000, 3935, 1125, 1685, 2810],
        [240000, 4115, 1175, 1765, 2940],
        [250000, 4285, 1225, 1835, 3060],
        [260000, 4465, 1275, 1915, 3190],
        [270000, 4635, 1325, 1985, 3310],
        [280000, 4815, 1375, 2065, 3440],
        [290000, 4985, 1425, 2135, 3560],
        [300000, 5165, 1475, 2215, 3690],
        [310000, 5335, 1525, 2285, 3810],
        [320000, 5515, 1575, 2365, 3940],
        [330000, 5685, 1625, 2435, 4060],
        [340000, 5865, 1675, 2515, 4190],
        [350000, 6035, 1725, 2585, 4310],
        [360000, 6215, 1775, 2665, 4440],
        [370000, 6385, 1825, 2735, 4560],
        [380000, 6565, 1875, 2815, 4690],
        [390000, 6735, 1925, 2885, 4810],
        [400000, 6915, 1975, 2965, 4940],
        [410000, 7085, 2025, 3035, 5060],
        [420000, 7265, 2075, 3115, 5190],
        [430000, 7435, 2125, 3185, 5310],
        [440000, 7615, 2175, 3265, 5440],
        [450000, 7785, 2225, 3335, 5560],
        [460000, 7965, 2275, 3415, 5690],
        [470000, 8135, 2325, 3485, 5810],
        [480000, 8315, 2375, 3565, 5940],
        [490000, 8485, 2425, 3635, 6060],
        [500000, 8665, 2475, 3715, 6190],
        [510000, 8835, 2525, 3785, 6310],
        [520000, 9015, 2575, 3865, 6440],
        [530000, 9185, 2625, 3935, 6560],
        [540000, 9365, 2675, 4015, 6690],
        [550000, 9535, 2725, 4085, 6810],
        [560000, 9715, 2775, 4165, 6940],
        [570000, 9885, 2825, 4235, 7060],
        [580000, 10065, 2875, 4315, 7190],
        [590000, 10235, 2925, 4385, 7310],
        [600000, 10415, 2975, 4465, 7440],
    ];

    /** EIS bands up to RM500 (upper limit, each share); above that each RM100 band pays 0.2% of its midpoint. */
    private const EIS_LOW_BANDS = [[3000, 5], [5000, 10], [7000, 15], [10000, 20], [14000, 25], [20000, 35], [30000, 50], [40000, 70], [50000, 90]];

    /**
     * SOCSO for one month's wages. First Category (employment injury + invalidity) applies below age 60;
     * Second Category (employment injury only, employer pays) from 60 and for foreign workers.
     * The SKBBK employee share applies from June 2026 unless the employee opted out (foreign workers cannot).
     *
     * @return array{category: int, employer: int, employee: int, skbbk: int}
     */
    public static function contribution(int $wages, bool $secondCategory, bool $withSkbbk, CarbonInterface $month): array
    {
        if ($wages <= 0) {
            return ['category' => $secondCategory ? 2 : 1, 'employer' => 0, 'employee' => 0, 'skbbk' => 0];
        }

        [, $employer, $invalidity, $skbbk, $employerSecond] = self::band($wages);
        $skbbk = $withSkbbk && $month->greaterThanOrEqualTo(self::SKBBK_START) ? $skbbk : 0;

        return $secondCategory
            ? ['category' => 2, 'employer' => $employerSecond, 'employee' => 0, 'skbbk' => $skbbk]
            : ['category' => 1, 'employer' => $employer, 'employee' => $invalidity, 'skbbk' => $skbbk];
    }

    /**
     * EIS for one month's wages: the same amount from employer and employee.
     */
    public static function eis(int $wages): int
    {
        if ($wages <= 0) {
            return 0;
        }

        foreach (self::EIS_LOW_BANDS as [$upper, $share]) {
            if ($wages <= $upper) {
                return $share;
            }
        }

        $upper = (int) min(self::WAGE_CEILING, (int) ceil($wages / 10000) * 10000);

        return intdiv(($upper - 5000) * 2, 1000);
    }

    /**
     * @return array{int, int, int, int, int}
     */
    private static function band(int $wages): array
    {
        foreach (self::TABLE as $row) {
            if ($wages <= $row[0]) {
                return $row;
            }
        }

        return self::TABLE[array_key_last(self::TABLE)];
    }
}
