<?php

namespace App\Http\Controllers;

use App\Models\Office;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficeController extends Controller
{
    public function index(): View
    {
        return view('offices.index', ['offices' => Office::withCount(['items', 'users', 'requisitions'])->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $office = Office::create($this->validated($request));
        AuditLogger::log('office.created', "Office {$office->code} created", $office);

        return back()->with('success', "Office \"{$office->name}\" added.");
    }

    public function update(Request $request, Office $office): RedirectResponse
    {
        $old = $office->only($office->getFillable());
        $office->update($this->validated($request, $office));
        AuditLogger::log('office.updated', "Office {$office->code} updated", $office, $old, $office->only($office->getFillable()));

        return back()->with('success', 'Office updated.');
    }

    /** Offices referenced by records are deactivated instead of deleted. */
    public function destroy(Office $office): RedirectResponse
    {
        if ($office->items()->exists() || $office->users()->exists() || $office->requisitions()->exists()) {
            $office->update(['is_active' => false]);
            AuditLogger::log('office.deactivated', "Office {$office->code} deactivated", $office);

            return back()->with('success', 'The office is referenced by existing records, so it was deactivated instead of deleted.');
        }
        AuditLogger::log('office.deleted', "Office {$office->code} deleted", $office);
        $office->delete();

        return back()->with('success', 'Office deleted.');
    }

    private function validated(Request $request, ?Office $office = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('offices', 'code')->ignore($office?->id)],
            'name' => ['required', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:150'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]) + ['is_active' => false];
    }
}
