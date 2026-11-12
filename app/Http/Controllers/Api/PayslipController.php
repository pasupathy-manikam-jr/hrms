<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\User;
use App\Support\TableQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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
     * @return Builder<Payslip>
     */
    private function query(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return Payslip::query()->visibleTo($user)->with('payrollRun:id,title,pay_period_start,pay_period_end,pay_date');
    }
}
