@extends('layouts.app')
@section('content')
<h2 class="page-title">Medicine Batches</h2>
@if(count($expiring))
<div class="card" style="border-color:#fcd34d;background:#fffbeb">
    <strong>Expiring within 30 days ({{ count($expiring) }}):</strong>
    {{ collect($expiring)->map(fn($b)=>$b->medicine_name.' — '.$b->expiry_date)->implode(', ') }}
</div>
@endif
<div class="card">
    <table>
        <thead><tr><th>Batch</th><th>Medicine</th><th>Batch #</th><th>Manufactured</th><th>Expiry</th><th>Qty</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($batches as $b)
            <tr><td>{{ $b->batch_id }}</td><td>{{ $b->medicine_name }}</td><td>{{ $b->batch_number }}</td>
            <td>{{ $b->manufacture_date }}</td><td>{{ $b->expiry_date }}</td><td>{{ $b->quantity }}</td><td>{{ $b->status }}</td></tr>
        @empty<tr><td colspan="7" class="muted">No batches.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
