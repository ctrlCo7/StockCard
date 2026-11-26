<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\InventoryCategory;
use App\Models\Office;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => ApplicationSetting::allWithDefaults(),
            'categories' => InventoryCategory::withCount('items')->orderBy('name')->get(),
            'offices' => Office::withCount(['items', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity_name' => ['required', 'string', 'max:150'],
            'division' => ['required', 'string', 'max:150'],
            'fund_cluster' => ['nullable', 'string', 'max:50'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'stock_card_form_code' => ['nullable', 'string', 'max:50'],
            'system_title' => ['required', 'string', 'max:100'],
            'items_per_page' => ['required', 'integer', 'min:5', 'max:100'],
            'notify_requisition_emails' => ['boolean'],
            'notify_low_stock_emails' => ['boolean'],
            'notification_extra_emails' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) {
                $invalid = collect(preg_split('/[\s,;]+/', (string) $value))->filter()
                    ->reject(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
                if ($invalid->isNotEmpty()) {
                    $fail('These are not valid email addresses: '.$invalid->implode(', '));
                }
            }],
        ]);
        $data['notify_requisition_emails'] = $request->boolean('notify_requisition_emails') ? '1' : '0';
        $data['notify_low_stock_emails'] = $request->boolean('notify_low_stock_emails') ? '1' : '0';

        $old = ApplicationSetting::allWithDefaults();
        foreach ($data as $key => $value) {
            ApplicationSetting::put($key, (string) $value);
        }
        AuditLogger::log('settings.updated', 'Application settings updated', null, $old, $data);

        return back()->with('success', 'Settings saved.');
    }
}
