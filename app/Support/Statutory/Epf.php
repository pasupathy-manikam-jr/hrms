<?php

namespace App\Support\Statutory;

/**
 * EPF (KWSP) contributions under the Third Schedule of the EPF Act 1991. Amounts are in sen.
 *
 * Up to RM20,000 the Schedule works in wage bands (RM20 wide up to RM5,000, RM100 wide above): each share is the
 * rate applied to the band's upper limit, rounded up to the next ringgit. Above RM20,000 the rate applies to the
 * actual wages, again rounded up to the next ringgit. Non-citizens (Part F, from October 2025) pay 2% each on the
 * actual wages, rounded up to the next ringgit.
 */
class Epf
{
    public const CITIZEN = 'citizen';

    public const PERMANENT_RESIDENT = 'permanent_resident';

    public const FOREIGNER = 'foreigner';

    public const CITIZENSHIPS = [self::CITIZEN, self::PERMANENT_RESIDENT, self::FOREIGNER];

    /**
     * @return array{employee: int, employer: int}
     */
    public static function contribution(int $wages, string $citizenship, int $age): array
    {
        if ($wages <= 1000) {
            return ['employee' => 0, 'employer' => 0];
        }

        if ($citizenship === self::FOREIGNER) {
            return ['employee' => self::share($wages, 2), 'employer' => self::share($wages, 2)];
        }

        $low = $wages <= 500000;
        // [employee %, employer %]: Part A below 60; from 60, Part E (citizens) or Part C (permanent residents).
        [$employeeRate, $employerRate] = match (true) {
            $age < 60 => [11, $low ? 13 : 12],
            $citizenship === self::PERMANENT_RESIDENT => [5.5, $low ? 6.5 : 6],
            default => [0, 4],
        };

        $base = match (true) {
            $wages > 2000000 => $wages,
            $low => (int) ceil($wages / 2000) * 2000,
            default => (int) ceil($wages / 10000) * 10000,
        };

        return ['employee' => self::share($base, $employeeRate), 'employer' => self::share($base, $employerRate)];
    }

    /**
     * The percentage of the wages, rounded up to the next ringgit.
     */
    private static function share(int $wages, float $percent): int
    {
        return (int) ceil(round($wages * $percent / 100, 6) / 100) * 100;
    }
}
