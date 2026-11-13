<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\Setting;
use App\Models\User;
use App\Support\TableQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as FileResponse;

/**
 * Payslips the user may see (their own from completed runs, unless they manage all payslips).
 */
class PayslipController extends Controller
{
    /**
     * @return LengthAwarePaginator<int, Payslip>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        return TableQuery::paginate($this->query($request), $request, [], ['created_at']);
    }

    public function show(Request $request, int $payslip): Payslip
    {
        return $this->query($request)->findOrFail($payslip);
    }

    /**
     * The payslip as a PDF. The employee downloading their own marks it downloaded, as in the demo.
     */
    public function pdf(Request $request, int $payslip): FileResponse
    {
        $record = $this->query($request)->with('employee.user:id,name', 'employee.designation:id,name')->findOrFail($payslip);

        if ($record->employee->user_id === $request->user()?->id && $record->status !== 'downloaded') {
            $record->update(['status' => 'downloaded']);
        }

        $settings = Setting::values();

        return Pdf::loadView('payroll.payslip', [
            'payslip' => $record,
            'employer' => $settings,
            'currency' => $settings['defaultCurrency'],
        ])->download('payslip-'.$record->payslip_number.'.pdf');
    }

    /**
     * @return Builder<Payslip>
     */
    private function query(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return Payslip::query()->visibleTo($user)->with('payrollRun:id,title,pay_period_start,pay_period_end,pay_date');
    }
}
