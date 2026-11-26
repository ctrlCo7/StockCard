<tr>
    <td>
        <label class="visually-hidden" for="ris-item-{{ $index }}">Item</label>
        <select id="ris-item-{{ $index }}" name="lines[{{ $index }}][item_id]" class="form-select" data-item-select required>
            <option value="">Select an item…</option>
            @foreach($items as $it)
                <option value="{{ $it->id }}" data-unit="{{ $it->unit }}" data-balance="{{ $it->current_balance }}" @selected(($line['item_id'] ?? null) == $it->id)>{{ $it->stock_number }} — {{ $it->name }}{{ $it->description ? ' ('.\Illuminate\Support\Str::limit($it->description, 40).')' : '' }}</option>
            @endforeach
        </select>
    </td>
    <td data-unit>—</td>
    <td>
        <label class="visually-hidden" for="ris-qty-{{ $index }}">Quantity</label>
        <input id="ris-qty-{{ $index }}" type="number" min="1" step="1" name="lines[{{ $index }}][quantity]" value="{{ $line['quantity'] ?? '' }}" class="form-control" required data-qty>
    </td>
    <td data-available><span class="text-muted">—</span></td>
    <td>
        <label class="visually-hidden" for="ris-remarks-{{ $index }}">Remarks</label>
        <input id="ris-remarks-{{ $index }}" name="lines[{{ $index }}][remarks]" value="{{ $line['remarks'] ?? '' }}" class="form-control" maxlength="255">
    </td>
    <td class="text-end"><button type="button" class="btn btn-sm btn-light border" data-remove-line aria-label="Remove line"><i class="bi bi-trash"></i></button></td>
</tr>
