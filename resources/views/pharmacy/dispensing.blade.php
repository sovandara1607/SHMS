@extends('layouts.app')
@section('content')
<h2 class="page-title">Medication Dispensing</h2>
@can('dispensing.create')
<div class="card">
    <h3>Dispense medicine</h3>
    <form method="post" action="/dispensing">
        @csrf
        <div class="row">
            <div><label>Prescription *</label><select name="prescription_id" required onchange="this.form.patient_id.value=this.options[this.selectedIndex].dataset.pat||''">
                <option value="">— select —</option>
                @foreach($prescriptions as $pr)<option value="{{ $pr->prescription_id }}" data-pat="{{ $pr->patient_id }}">{{ $pr->prescription_id }} — {{ $pr->patient_name }}</option>@endforeach
            </select></div>
            <div><label>Medicine *</label><select name="medicine_id" required>
                <option value="">— select —</option>
                @foreach($medicines as $m)<option value="{{ $m->medicine_id }}">{{ $m->medicine_name }} (stock {{ $m->stock_quantity }})</option>@endforeach
            </select></div>
        </div>
        <input type="hidden" name="patient_id" value="">
        <label>Quantity *</label><input class="input" type="number" name="quantity" min="1" value="1" required>
        <button class="btn" type="submit">Dispense</button>
    </form>
</div>
@endcan
<div class="card">
    <table>
        <thead><tr><th>ID</th><th>Patient</th><th>Prescription</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($records as $d)
            <tr><td>{{ $d->dispensing_id }}</td><td>{{ $d->patient_name }}</td><td>{{ $d->prescription_id }}</td>
            <td>{{ $d->dispensing_date }}</td><td>{{ $d->status }}</td></tr>
        @empty<tr><td colspan="5" class="muted">No dispensing records.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
