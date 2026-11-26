<tr>
    <td>
        <label class="visually-hidden" for="line-item-{{ $index }}">Item</label>
        <select id="line-item-{{ $index }}" name="lines[{{ $index }}][item_id]" class="form-select" data-item-select required>
            <option value="">Select an item…</option>
            @foreach($items as $it)
                <option value="{{ $it->id }}" data-unit="{{ $it->unit }}" data-balance="{{ $it->current_balance }}" @selected(($line['item_id'] ?? null) == $it->id)>{{ $it->stock_number }} — {{ $it->name }}</option>
            @endforeach
        </select>
    </td>
    <td data-unit>—</td>
    <td class="small text-muted" data-onhand>—</td>
    <td>
        <label class="visually-hidden" for="line-qty-{{ $index }}">Quantity received</label>
        <input id="line-qty-{{ $index }}" type="number" min="1" step="1" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control" required data-qty>
    </td>
    <td>
        <label class="visually-hidden" for="line-remarks-{{ $index }}">Remarks</label>
        <input id="line-remarks-{{ $index }}" name="lines[{{ $index }}][remarks]" value="{{ $line['remarks'] ?? '' }}" class="form-control" maxlength="255">
    </td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light border" data-remove-line aria-label="Remove line"><i class="bi bi-trash"></i></button></td>
</tr>
