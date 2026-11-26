<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus as Status;
use App\Enums\Role;
use App\Http\Requests\RequisitionRequest;
use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\Office;
use App\Models\Requisition;
use App\Models\User;
use App\Services\RequisitionService;
use App\Support\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RequisitionController extends Controller
{
    public function __construct(private RequisitionService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(Status::class)],
            'scope' => ['nullable', 'in:mine,all,queue'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'office' => ['nullable', 'integer'],
        ]);
        $canViewAll = $user->can('requisitions.view-all');
        $scope = $canViewAll ? ($filters['scope'] ?? 'all') : 'mine';

        $queueStatuses = collect([
            $user->can('requisitions.forward') ? Status::Submitted : null,
            $user->can('requisitions.approve') ? Status::PendingApproval : null,
            $user->can('requisitions.issue') ? Status::Approved : null,
            $user->can('requisitions.issue') ? Status::PartiallyIssued : null,
        ])->filter()->values();

        $requisitions = Requisition::query()
            ->with(['requester', 'office'])->withCount('items')
            ->search($filters['q'] ?? null)
            ->when($scope === 'mine', fn ($q) => $q->where('requested_by', $user->id))
            ->when($scope === 'queue', fn ($q) => $q->whereIn('status', $queueStatuses))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['office'] ?? null, fn ($q, $v) => $q->where('office_id', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('requisition_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('requisition_date', '<=', $v))
            ->latest('id')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('requisitions.index', [
            'requisitions' => $requisitions,
            'filters' => $filters,
            'scope' => $scope,
            'canViewAll' => $canViewAll,
            'statuses' => Status::options(),
            'offices' => Office::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $requisition = new Requisition([
            'office_id' => $request->user()->office_id,
            'requisition_date' => today(),
            'fund_cluster' => ApplicationSetting::get('fund_cluster'),
            'responsibility_center_code' => $request->user()->office?->responsibility_center_code ?: ApplicationSetting::get('responsibility_center_code'),
        ]);

        return view('requisitions.form', $this->formData($requisition));
    }

    public function store(RequisitionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $requisition = $this->service->createDraft($request->headerData(), $request->lineItems(), $user);

        if ($request->input('action') === 'submit') {
            $this->service->submit($requisition, $user);

            return redirect()->route('requisitions.show', $requisition)->with('success', "RIS {$requisition->ris_no} submitted for processing.");
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', "RIS {$requisition->ris_no} saved as a draft.");
    }

    public function show(Requisition $requisition): View
    {
        Gate::authorize('view', $requisition);

        return view('requisitions.show', [
            'requisition' => $requisition->load(['items.item', 'office', 'requester', 'approver', 'issuer', 'receiver', 'approvals.user', 'issuances.items.item', 'issuances.issuer']),
        ]);
    }

    public function edit(Requisition $requisition): View
    {
        Gate::authorize('update', $requisition);

        return view('requisitions.form', $this->formData($requisition->load('items.item')));
    }

    public function update(RequisitionRequest $request, Requisition $requisition): RedirectResponse
    {
        $user = $request->user();
        $this->service->updateDraft($requisition, $request->headerData(), $request->lineItems(), $user);

        if ($request->input('action') === 'submit') {
            $this->service->submit($requisition, $user);

            return redirect()->route('requisitions.show', $requisition)->with('success', "RIS {$requisition->ris_no} submitted for processing.");
        }

        return redirect()->route('requisitions.show', $requisition)->with('success', 'Changes saved.');
    }

    public function submit(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('submit', $requisition);
        $this->service->submit($requisition, $request->user());

        return back()->with('success', "RIS {$requisition->ris_no} submitted.");
    }

    public function forward(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('forward', $requisition);
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'line_remarks' => ['nullable', 'array'],
            'line_remarks.*' => ['nullable', 'string', 'max:255'],
        ]);
        $this->service->forward($requisition, $request->user(), $data['line_remarks'] ?? [], $data['remarks'] ?? null);

        return back()->with('success', 'Stock availability recorded and requisition forwarded for approval.');
    }

    public function approve(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('approve', $requisition);
        $data = $request->validate([
            'approved' => ['required', 'array'],
            'approved.*' => ['required', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->service->approve($requisition, $request->user(), $data['approved'], $data['remarks'] ?? null);

        return back()->with('success', "RIS {$requisition->ris_no} approved. Stock will be deducted only when issued.");
    }

    public function reject(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('approve', $requisition);
        $data = $request->validate(['remarks' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->reject($requisition, $request->user(), $data['remarks']);

        return back()->with('success', "RIS {$requisition->ris_no} rejected.");
    }

    public function returnForCorrection(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('returnForCorrection', $requisition);
        $data = $request->validate(['remarks' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->returnForCorrection($requisition, $request->user(), $data['remarks']);

        return back()->with('success', "RIS {$requisition->ris_no} returned to the requester for correction.");
    }

    public function cancel(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('cancel', $requisition);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $this->service->cancel($requisition, $request->user(), $data['remarks'] ?? null);

        return back()->with('success', "RIS {$requisition->ris_no} cancelled.");
    }

    public function issueForm(Request $request, Requisition $requisition): View
    {
        Gate::authorize('issue', $requisition);

        $requisition->load(['items.item', 'requester', 'office']);

        return view('requisitions.issue', [
            'requisition' => $requisition,
            'idempotencyKey' => (string) Str::uuid(),
            // Requesting personnel only, with people from the requesting office listed first.
            'recipients' => User::query()->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->where('name', Role::Requester->value))
                ->with('office')->orderBy('name')->get()
                ->sortBy(fn (User $u) => $u->office_id === $requisition->office_id ? 0 : 1)->values(),
        ]);
    }

    public function issue(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('issue', $requisition);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'issue_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$requisition->requisition_date->toDateString()],
            'received_by_user_id' => ['required', 'integer', 'exists:users,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0'],
        ]);

        [$issuance, $created] = $this->service->issue($requisition, $request->user(), $data['quantities'], $data);

        return redirect()->route('requisitions.show', $requisition)->with(
            $created ? 'success' : 'warning',
            $created ? "Issuance {$issuance->issuance_no} posted. Stock Cards updated with reference {$requisition->ris_no}." : "This issuance was already posted as {$issuance->issuance_no}; it was not posted again."
        );
    }

    public function acknowledge(Request $request, Requisition $requisition): RedirectResponse
    {
        Gate::authorize('acknowledge', $requisition);
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $this->service->acknowledgeReceipt($requisition, $request->user(), $data['remarks'] ?? null);

        return back()->with('success', 'Receipt acknowledged. The requisition is now complete.');
    }

    public function print(Request $request, Requisition $requisition): View
    {
        Gate::authorize('view', $requisition);

        return view('requisitions.print', $this->printData($request, $requisition));
    }

    public function pdf(Request $request, Requisition $requisition): Response
    {
        Gate::authorize('view', $requisition);

        return PdfRenderer::make('requisitions.pdf', $this->printData($request, $requisition))
            ->download("{$requisition->ris_no}.pdf");
    }

    private function printData(Request $request, Requisition $requisition): array
    {
        return [
            'requisition' => $requisition->load(['items.item', 'office', 'requester', 'approver', 'issuer', 'receiver']),
            'generatedBy' => $request->user(),
        ];
    }

    private function formData(Requisition $requisition): array
    {
        $lines = old('lines', $requisition->exists
            ? $requisition->items->map(fn ($l) => ['item_id' => $l->item_id, 'quantity' => $l->quantity_requested, 'remarks' => $l->remarks])->all()
            : [['item_id' => '', 'quantity' => '', 'remarks' => '']]);

        return [
            'requisition' => $requisition,
            'lines' => array_values($lines),
            'offices' => Office::where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'stock_number', 'name', 'description', 'unit', 'current_balance', 'reorder_point']),
            'settings' => ApplicationSetting::allWithDefaults(),
        ];
    }
}
