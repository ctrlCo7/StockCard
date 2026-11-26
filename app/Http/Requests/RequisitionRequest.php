<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $requisition = $this->route('requisition');

        return $requisition ? $this->user()->can('update', $requisition) : $this->user()->can('requisitions.create');
    }

    public function rules(): array
    {
        return [
            'office_id' => ['required', 'integer', Rule::exists('offices', 'id')->where('is_active', true)],
            'requisition_date' => ['required', 'date', 'before_or_equal:today'],
            'fund_cluster' => ['nullable', 'string', 'max:50'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'purpose' => ['required', 'string', 'min:5', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where('status', InventoryItem::STATUS_ACTIVE)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
            'action' => ['nullable', 'in:draft,submit'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one item to the requisition.',
            'lines.*.item_id.distinct' => 'Each item may appear only once. Combine the quantities into one line.',
            'lines.*.item_id.exists' => 'One of the selected items is no longer available for requisition.',
            'lines.*.quantity.min' => 'Requested quantities must be at least 1.',
        ];
    }

    public function headerData(): array
    {
        return $this->safe()->only(['office_id', 'requisition_date', 'fund_cluster', 'responsibility_center_code', 'purpose']);
    }

    public function lineItems(): array
    {
        return array_values($this->validated('lines'));
    }
}
