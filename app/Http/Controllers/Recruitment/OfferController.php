<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Currency;
use App\Models\Offer;
use App\Models\OfferTemplate;
use App\Models\Setting;
use App\Models\User;
use App\Support\TableQuery;
use App\Support\TemplateRenderer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * As in the demo: edit-offers edits open offers; approve-offers moves them through their statuses.
 * Accepting hires the candidate; declined or expired offers reject them.
 */
class OfferController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Offer::query()
            ->visibleTo($request->user())
            ->with([
                'candidate:id,first_name,last_name,email,status,job_id',
                'job:id,title,job_code',
                'template:id,name',
                'approver:id,name',
            ])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id))
            ->when($request->input('search'), fn ($q, $search) => $q->whereHas('candidate', fn (Builder $c) => $c
                ->whereAny(['first_name', 'last_name', 'email'], 'like', '%'.addcslashes($search, '%_\\').'%')));

        $counts = Offer::query()->visibleTo($request->user())->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('hr/recruitment/offers/index', [
            // Search matches the candidate (whereHas above), so TableQuery gets no columns of its own.
            'offers' => TableQuery::paginate($query, $request, [], ['salary', 'start_date', 'expiration_date', 'created_at']),
            'candidates' => Candidate::query()->visibleTo($request->user())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'job_id']),
            'offerTemplates' => OfferTemplate::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'employees' => User::query()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => ['all' => (int) $counts->sum()] + collect(Offer::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all(),
            'filters' => TableQuery::filters($request, ['status', 'candidate_id']),
        ]);
    }

    /**
     * Every visible offer in one board, one column per status; cards move through updateStatus().
     */
    public function kanban(Request $request): Response
    {
        return Inertia::render('hr/recruitment/offers/kanban', [
            'offers' => Offer::query()
                ->visibleTo($request->user())
                ->with(['candidate:id,first_name,last_name,email', 'job:id,title', 'approver:id,name'])
                ->when($request->integer('candidate_id'), fn ($q, $id) => $q->where('candidate_id', $id))
                ->when($request->input('search'), fn ($q, $search) => $q->whereHas('candidate', fn (Builder $c) => $c
                    ->whereAny(['first_name', 'last_name', 'email'], 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->latest('offer_date')->latest('id')
                ->get(),
            'candidates' => Candidate::query()->visibleTo($request->user())->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'filters' => $request->only(['search', 'candidate_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Offer::create($data + ['status' => 'Draft', 'job_id' => Candidate::query()->whereKey($data['candidate_id'])->value('job_id')]);

        return $this->done(__('Offer created successfully.'));
    }

    public function update(Request $request, Offer $offer): RedirectResponse
    {
        abort_unless($offer->isVisibleTo($request->user()), 403);
        abort_if(in_array($offer->status, Offer::CLOSED, true), 403, __('Accepted or declined offers can no longer be edited.'));

        $data = $this->validated($request);
        $offer->update($data + ['job_id' => Candidate::query()->whereKey($data['candidate_id'])->value('job_id')]);

        return $this->done(__('Offer updated successfully.'));
    }

    public function updateStatus(Request $request, Offer $offer): RedirectResponse
    {
        abort_unless($offer->isVisibleTo($request->user()), 403);
        abort_if(in_array($offer->status, Offer::CLOSED, true), 403, __('Accepted or declined offers can no longer change status.'));

        $data = $request->validate([
            'status' => ['required', Rule::in(Offer::STATUSES)],
            'decline_reason' => ['nullable', 'required_if:status,Declined', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($offer, $data, $request) {
            $offer->update([
                'status' => $data['status'],
                'decline_reason' => $data['status'] === 'Declined' ? $data['decline_reason'] : null,
                'response_date' => in_array($data['status'], Offer::CLOSED, true) ? now()->toDateString() : null,
                'approved_by' => $offer->approved_by ?? ($data['status'] === 'Draft' ? null : $request->user()->id),
            ]);

            if ($candidateStatus = Offer::CANDIDATE_STATUS[$data['status']] ?? null) {
                $offer->candidate()->update(array_filter([
                    'status' => $candidateStatus,
                    'final_salary' => $data['status'] === 'Accepted' ? $offer->salary : null,
                ]));
            }
        });

        return $this->done(__('Offer status updated successfully.'));
    }

    public function destroy(Request $request, Offer $offer): RedirectResponse
    {
        abort_unless($offer->isVisibleTo($request->user()), 403);
        $offer->delete();

        return $this->done(__('Offer deleted successfully.'));
    }

    /**
     * The offer with its letter: the template with this offer's placeholders filled in.
     */
    public function show(Request $request, Offer $offer): Response
    {
        abort_unless($offer->isVisibleTo($request->user()), 404);
        $offer->load([
            'candidate:id,first_name,last_name,email,phone,gender,status',
            'job:id,title,job_code,department_id,location_id,job_type_id', 'job.department:id,name', 'job.location:id,name', 'job.jobType:id,name',
            'template:id,name,template_content',
            'approver:id,name,email,avatar_path',
        ]);

        return Inertia::render('hr/recruitment/offers/show', [
            'offer' => $offer,
            'letter' => $offer->template ? TemplateRenderer::render($offer->template->template_content, $this->letterValues($offer)) : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function letterValues(Offer $offer): array
    {
        $date = fn (?CarbonInterface $value) => $value?->format(Setting::get('dateFormat')) ?? '';
        $expiry = $date($offer->expiration_date);

        $salary = $this->money($offer->salary);
        $department = (string) $offer->job->department?->name;
        // The approving manager signs for the team; whoever raised the offer is the HR contact.
        $manager = (string) $offer->approver?->name;
        $hr = (string) (User::query()->whereKey($offer->created_by)->value('name') ?? $manager);

        return [
            'candidate_name' => trim($offer->candidate->first_name.' '.$offer->candidate->last_name),
            'job_title' => $offer->job->title,
            'internship_title' => $offer->job->title,
            'company_name' => (string) Setting::get('titleText'),
            'department' => $department,
            'division' => $department,
            'team_name' => $department,
            'work_location' => (string) $offer->job->location?->name,
            'contract_type' => (string) $offer->job->jobType?->name,
            'work_model' => (string) $offer->job->jobType?->name,
            'salary' => $salary,
            'base_salary' => $salary,
            'annual_salary' => $salary,
            'monthly_salary' => $this->money((string) round((float) $offer->salary / 12, 2)),
            'bonus' => $offer->bonus === null ? '' : $this->money($offer->bonus),
            'variable_pay' => $offer->bonus === null ? '' : $this->money($offer->bonus),
            'benefits' => (string) $offer->benefits,
            'start_date' => $date($offer->start_date),
            'offer_date' => $date($offer->offer_date),
            'offer_expiry_date' => $expiry,
            'expiration_date' => $expiry,
            'acceptance_deadline' => $expiry,
            'acceptance_date' => $expiry,
            'response_deadline' => $expiry,
            'response_date' => $expiry,
            'confirmation_date' => $expiry,
            'manager_name' => $manager,
            'hiring_manager_name' => $manager,
            'supervisor_name' => $manager,
            'hr_manager_name' => $hr,
            'hr_contact_name' => $hr,
            'hr_representative' => $hr,
            'internship_coordinator' => $hr,
            'time_zone' => (string) Setting::get('defaultTimezone'),
        ];
    }

    /**
     * Format an amount the way Settings → Currency says (mirrors useFormat().money()).
     */
    private function money(string $amount): string
    {
        $s = Setting::values();
        $symbol = Currency::query()->where('code', $s['defaultCurrency'])->value('symbol') ?? $s['defaultCurrency'];
        $number = number_format((float) $amount, (int) $s['decimalFormat'], $s['decimalSeparator'], $s['thousandsSeparator']);
        $space = $s['currencySymbolSpace'] ? ' ' : '';

        return $s['currencySymbolPosition'] === 'before' ? $symbol.$space.$number : $number.$space.$symbol;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'candidate_id' => ['required', 'integer', Rule::exists('candidates', 'id')],
            'offer_template_id' => ['nullable', 'integer', Rule::exists('offer_templates', 'id')],
            'offer_date' => ['required', 'date_format:Y-m-d'],
            'salary' => ['required', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'bonus' => ['nullable', 'numeric', 'min:0', 'max:9999999999999', 'decimal:0,2'],
            'benefits' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'expiration_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:offer_date'],
            'approved_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);
    }
}
