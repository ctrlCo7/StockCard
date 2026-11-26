<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('items.manage');
    }

    public function rules(): array
    {
        $item = $this->route('item');
        $creating = $item === null;

        return [
            'stock_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-\/\.]+$/', Rule::unique('inventory_items', 'stock_number')->ignore($item?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'unit' => ['required', 'string', 'max:30'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'location' => ['nullable', 'string', 'max:150'],
            'reorder_point' => ['required', 'integer', 'min:0', 'max:1000000'],
            // Opening balance is captured once, at creation, and becomes the first ledger row.
            'opening_quantity' => [Rule::requiredIf($creating), 'prohibited_unless:_creating,1', 'integer', 'min:0', 'max:1000000'],
            'opening_date' => [Rule::requiredIf($creating), 'prohibited_unless:_creating,1', 'date', 'before_or_equal:today'],
            'opening_reference' => ['nullable', 'prohibited_unless:_creating,1', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['_creating' => $this->route('item') === null ? '1' : '0']);
    }

    public function messages(): array
    {
        return [
            'stock_number.unique' => 'This stock number is already assigned to another item.',
            'stock_number.regex' => 'Stock numbers may contain letters, numbers, dashes, slashes and dots only.',
        ];
    }

    /** @return array<string, mixed> */
    public function itemAttributes(): array
    {
        return $this->safe()->only(['stock_number', 'name', 'description', 'category_id', 'unit', 'office_id', 'location', 'reorder_point']);
    }
}
