@props(['icon' => 'inbox', 'message' => 'No records found.'])
<div class="empty-state">
    <i class="bi bi-{{ $icon }}"></i>
    <div>{{ $message }}</div>
    @if($slot->isNotEmpty())<div class="mt-2">{{ $slot }}</div>@endif
</div>
