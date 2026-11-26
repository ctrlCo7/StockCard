@extends('layouts.app')
@section('title', 'Receipt '.$receipt->receipt_no)

@section('content')
<x-page-header :title="'Stock Receipt '.$receipt->receipt_no" :subtitle="'Posted '.$receipt->posted_at->format('M d, Y g:i A').' by '.$receipt->poster?->name" :crumbs="['Stock Receipts' => route('receipts.index')]">
    <button onclick="window.print()" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i>Print</button>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Receipt details</h2><span class="badge text-bg-success">Posted</span></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Receipt no.</dt><dd>{{ $receipt->receipt_no }}</dd>
                    <dt>Date received</dt><dd>{{ $receipt->date_received->format('M d, Y') }}</dd>
                    <dt>Supplier / source</dt><dd>{{ $receipt->supplier }}</dd>
                    <dt>Reference</dt><dd>{{ \App\Models\StockReceipt::REFERENCE_TYPES[$receipt->reference_type] ?? $receipt->reference_type }} — {{ $receipt->reference_no }}</dd>
                    <dt>Received by</dt><dd>{{ $receipt->received_by_name }}</dd>
                    <dt>Posted by</dt><dd>{{ $receipt->poster?->name }}</dd>
                    <dt>Remarks</dt><dd>{{ $receipt->remarks ?: '—' }}</dd>
                    <dt>Attachment</dt><dd>
                        @if($receipt->attachment_path)
                            <a href="{{ route('receipts.attachment', $receipt) }}"><i class="bi bi-paperclip"></i> {{ $receipt->attachment_name }}</a>
                        @else — @endif
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><h2 class="card-title">Items received</h2></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Stock No.</th><th>Item</th><th>Unit</th><th class="num">Qty. received</th><th class="num">Balance after</th><th>Remarks</th><th></th></tr></thead>
                    <tbody>
                    @foreach($receipt->items as $line)
                        @php $tx = $receipt->transactions->firstWhere(fn ($t) => $t->item_id === $line->item_id && $t->type === \App\Enums\TransactionType::Receipt); @endphp
                        <tr>
                            <td>{{ $line->item->stock_number }}</td>
                            <td>{{ $line->item->name }}</td>
                            <td>{{ $line->unit }}</td>
                            <td class="num fw-semibold">{{ number_format($line->quantity) }}</td>
                            <td class="num">{{ $tx?->balance_after }}</td>
                            <td class="small">{{ $line->remarks }}</td>
                            <td class="text-end"><a href="{{ route('stock-cards.show', $line->item) }}" class="btn btn-sm btn-light border">Stock Card</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if($receipt->transactions->contains(fn ($t) => $t->type === \App\Enums\TransactionType::Reversal))
            <div class="alert alert-warning mt-3 small"><i class="bi bi-arrow-counterclockwise me-1"></i>One or more lines of this receipt have been reversed. See the item Stock Cards for details.</div>
        @endif
    </div>
</div>
@endsection
