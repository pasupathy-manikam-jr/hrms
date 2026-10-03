<?php

namespace Tests\Unit;

use App\Support\Statutory\Epf;
use App\Support\Statutory\Pcb;
use App\Support\Statutory\Socso;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checked against the published figures: LHDN's worked examples in the 2026 MTD specification (Exhibit 5),
 * the EPF Third Schedule and PERKESO's Act 4 / Act 800 contribution tables. Amounts are in sen.
 */
class StatutoryContributionsTest extends TestCase
{
    /**
     * LHDN Exhibit 5: married, wife working (category 3), 3 children, RM5,500 a month with RM605 EPF.
     */
    public function test_pcb_matches_lhdn_worked_examples()
    {
        // January: nothing paid before.
        $this->assertSame(11000, Pcb::monthly(1, 3, 3, true, 0, 0, 0, 550000, 60500));
        // February: January's RM110 already deducted.
        $this->assertSame(11000, Pcb::monthly(2, 3, 3, true, 550000, 60500, 11000, 550000, 60500));
        // March: RM300 of TP1 deductions claimed.
        $this->assertSame(10820, Pcb::monthly(3, 3, 3, true, 1100000, 121000, 22000, 550000, 60500, 30000));
        // April normal remuneration (before the bonus): RM600 of TP1 deductions so far.
        $this->assertSame(10620, Pcb::monthly(4, 3, 3, true, 1650000, 181500, 32820, 550000, 60500, 60000));
    }

    public function test_pcb_rules_for_non_residents_low_pay_and_categories()
    {
        // Non-resident: flat 30% (LHDN's example: RM3,000 → RM900).
        $this->assertSame(90000, Pcb::monthly(5, 1, 0, false, 0, 0, 0, 300000, 0));
        // Below RM10 a month nothing is deducted.
        $this->assertSame(0, Pcb::monthly(1, 1, 0, true, 0, 0, 0, 250000, 27500));
        // A non-working spouse (category 2) lowers the deduction against category 3; children are ignored when single.
        $married = Pcb::monthly(1, 2, 0, true, 0, 0, 0, 800000, 88000);
        $single = Pcb::monthly(1, 1, 2, true, 0, 0, 0, 800000, 88000);
        $this->assertLessThan($single, $married);
        $this->assertSame($single, Pcb::monthly(1, 1, 0, true, 0, 0, 0, 800000, 88000));
        $this->assertSame(0, $married % 5, 'rounded to 5 sen');
    }

    /**
     * @return array<string, array{int, string, int, int, int}>
     */
    public static function epfCases(): array
    {
        return [
            'below RM10' => [1000, Epf::CITIZEN, 30, 0, 0],
            'RM10.01-20 band' => [1500, Epf::CITIZEN, 30, 300, 300],
            'RM1,500 exactly' => [150000, Epf::CITIZEN, 30, 16500, 19500],
            'RM1,500.01-1,520 band' => [151000, Epf::CITIZEN, 30, 16800, 19800],
            'RM5,000.01-5,100 band (12%)' => [505000, Epf::CITIZEN, 30, 56100, 61200],
            'above RM20,000 on actual wages' => [2500050, Epf::CITIZEN, 30, 275100, 300100],
            'citizen aged 60+' => [300000, Epf::CITIZEN, 61, 0, 12000],
            'permanent resident aged 60+' => [300000, Epf::PERMANENT_RESIDENT, 61, 16500, 19500],
            'foreign worker 2% rounded up' => [175100, Epf::FOREIGNER, 30, 3600, 3600],
        ];
    }

    #[DataProvider('epfCases')]
    public function test_epf_follows_the_third_schedule(int $wages, string $citizenship, int $age, int $employee, int $employer)
    {
        $this->assertSame(['employee' => $employee, 'employer' => $employer], Epf::contribution($wages, $citizenship, $age));
    }

    public function test_socso_and_eis_follow_perkeso_tables()
    {
        $july = CarbonImmutable::parse('2026-07-01');

        // Lowest band (wages up to RM30) and the RM6,000 ceiling, First Category.
        $this->assertSame(['category' => 1, 'employer' => 40, 'employee' => 10, 'skbbk' => 20], Socso::contribution(2500, false, true, $july));
        $this->assertSame(['category' => 1, 'employer' => 10415, 'employee' => 2975, 'skbbk' => 4465], Socso::contribution(950000, false, true, $july));
        // RM5,500.01-5,600 band, Second Category.
        $this->assertSame(['category' => 2, 'employer' => 6940, 'employee' => 0, 'skbbk' => 4165], Socso::contribution(555000, true, true, $july));
        // No SKBBK before June 2026 wages, or for an employee who opted out.
        $this->assertSame(0, Socso::contribution(300000, false, true, CarbonImmutable::parse('2026-05-01'))['skbbk']);
        $this->assertSame(0, Socso::contribution(300000, false, false, $july)['skbbk']);

        $this->assertSame(5, Socso::eis(2500));
        $this->assertSame(90, Socso::eis(45000));
        $this->assertSame(110, Socso::eis(55000));
        $this->assertSame(970, Socso::eis(485000));
        $this->assertSame(1190, Socso::eis(1200000));
    }
}
