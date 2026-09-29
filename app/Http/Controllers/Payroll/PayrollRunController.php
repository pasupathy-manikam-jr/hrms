<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Support\Csv;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Draft runs can be processed (and reprocessed) any number of times; completing one locks it.
 */
class PayrollRunController extends Controller
{
    /** CSV header => form field, shared by the export, the template and the import. */
    private const CSV_COLUMNS = [
        'Title' => 'title',
        'Payroll Frequency' => 'payroll_frequency',
        'Pay Period Start' => 'pay_period_start',
        'Pay Period End' => 'pay_period_end',
        'Pay Date' => 'pay_date',
        'Notes' => 'notes',
    ];

    public function index(Request $request): Response
    {
        $query = $this->filteredQuery($request);

        $counts = TableQuery::countBy($query, 'status');

        return Inertia::render('hr/payroll-runs/index', [
            'payrollRuns' => TableQuery::paginate(
                $query->when($request->input('status'), fn ($q, $status) => $q->where('status', $status)),
                $request,
                ['title', 'notes'],
                ['title', 'pay_period_start', 'pay_date', 'total_net_pay', 'created_at'],
            ),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(PayrollRun::STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($counts[$status] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'date_from', 'date_to']),
        ]);
    }

    public function show(PayrollRun $payrollRun): Response
    {
        return Inertia::render('hr/payroll-runs/show', [
            'payrollRun' => $payrollRun,
            'payslips' => $payrollRun->payslips()
                ->with(['employee:id,user_id,employee_id,gender,designation_id', 'employee.user:id,name,email,avatar_path', 'employee.designation:id,name'])
                ->orderBy('id')
                ->get(['id', 'payroll_run_id', 'employee_id', 'payslip_number', 'basic_salary', 'total_earnings', 'gross_pay', 'total_deductions', 'net_pay', 'status']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $runs = $this->filteredQuery($request)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(trim($request->string('search')->toString()), fn ($q, $search) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('notes', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->orderBy('pay_period_start')->lazy();

        return Csv::download('payroll-runs-'.now()->format('Y-m-d').'.csv', [...array_keys(self::CSV_COLUMNS), 'Employees', 'Gross Pay', 'Deductions', 'Net Pay', 'Status'], $runs->map(fn (PayrollRun $run) => [
            $run->title, $run->payroll_frequency, $run->pay_period_start->toDateString(), $run->pay_period_end->toDateString(), $run->pay_date->toDateString(), $run->notes,
            $run->employee_count, $run->total_gross_pay, $run->total_deductions, $run->total_net_pay, $run->status,
        ]));
    }

    public function template(): StreamedResponse
    {
        $start = now()->startOfMonth();

        return Csv::download('payroll-runs-import-template.csv', array_keys(self::CSV_COLUMNS), [[
            $start->format('F Y').' Payroll', 'monthly', $start->toDateString(), $start->copy()->endOfMonth()->toDateString(), $start->copy()->addMonth()->addDays(4)->toDateString(), '',
        ]]);
    }

    /**
     * Import payroll runs from the template's CSV. Each row goes through the form's rules and
     * becomes a draft run (it is not processed); bad rows are skipped and reported by row number.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => Csv::uploadRules()]);

        $imported = 0;
        $skipped = [];

        foreach (Csv::read($request->file('file')) as $line => $row) {
            $input = collect(self::CSV_COLUMNS)->mapWithKeys(fn ($field, $header) => [$field => ($row[Str::snake($header)] ?? '') ?: null])->all();
            $input['payroll_frequency'] = str_replace(['-', ' '], '', mb_strtolower((string) $input['payroll_frequency'])) ?: 'monthly';

            $validator = Validator::make($input, $this->rules());

            if ($validator->fails()) {
                $skipped[] = ['row' => $line, 'errors' => $validator->errors()->all()];

                continue;
            }

            PayrollRun::create($validator->validated());
            $imported++;
        }

        Inertia::flash('import', ['imported' => $imported, 'skipped' => $skipped]);

        return $skipped === []
            ? $this->done(__(':count payroll runs imported.', ['count' => $imported]))
            : $this->toast('error', __(':imported imported, :skipped skipped. See the import report.', ['imported' => $imported, 'skipped' => count($skipped)]));
    }

    public function store(Request $request): RedirectResponse
    {
        PayrollRun::create($this->validated($request));

        return $this->done(__('Payroll run created successfully.'));
    }

    public function update(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isLocked()) {
            return $this->locked();
        }

        $payrollRun->update($this->validated($request));

        return $this->done(__('Payroll run updated successfully.'));
    }

    public function process(PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isLocked()) {
            return $this->locked();
        }

        $payrollRun->process();

        return $this->done(__('Payroll processed: :count payslips generated.', ['count' => $payrollRun->employee_count]));
    }

    public function complete(PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isLocked()) {
            return $this->locked();
        }

        if (! $payrollRun->payslips()->exists()) {
            return $this->toast('error', __('Process the payroll run before completing it.'));
        }

        $payrollRun->forceFill(['status' => 'completed'])->save();

        return $this->done(__('Payroll run completed.'));
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        if ($payrollRun->isLocked()) {
            return $this->locked();
        }

        $payrollRun->delete();

        return $this->done(__('Payroll run deleted successfully.'));
    }

    private function locked(): RedirectResponse
    {
        return $this->toast('error', __('This payroll run is locked.'));
    }

    /**
     * @return Builder<PayrollRun>
     */
    private function filteredQuery(Request $request): Builder
    {
        return PayrollRun::query()
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('pay_period_start', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('pay_period_end', '<=', $date));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate($this->rules());
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'payroll_frequency' => ['required', Rule::in(PayrollRun::FREQUENCIES)],
            'pay_period_start' => ['required', 'date'],
            'pay_period_end' => ['required', 'date', 'after_or_equal:pay_period_start'],
            'pay_date' => ['required', 'date', 'after_or_equal:pay_period_start'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
