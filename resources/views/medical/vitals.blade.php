@extends('layouts.app')
@section('content')
<h2 class="page-title">Vital Signs</h2>
@can('vital_signs.create')
<div class="card">
    <h3>Record vital signs</h3>
    <form method="post" action="/vital-signs">
        @csrf
        <label>Patient *</label><select name="patient_id" required>
            <option value="">— select —</option>
            @foreach($patients as $p)<option value="{{ $p->patient_id }}">{{ $p->fullName() }}</option>@endforeach
        </select>
        <div class="row">
            <div><label>Temperature (°C)</label><input class="input" type="number" step="0.1" name="temperature"></div>
            <div><label>Blood pressure</label><input class="input" name="blood_pressure" placeholder="120/80"></div>
        </div>
        <div class="row">
            <div><label>Heart rate (bpm)</label><input class="input" type="number" name="heart_rate"></div>
            <div><label>Height (cm)</label><input class="input" type="number" step="0.1" name="height"></div>
        </div>
        <label>Weight (kg)</label><input class="input" type="number" step="0.1" name="weight">
        <button class="btn" type="submit">Save</button>
    </form>
</div>
@endcan
<div class="card">
    <table>
        <thead><tr><th>Patient</th><th>Temp</th><th>BP</th><th>HR</th><th>Height</th><th>Weight</th><th>Recorded</th></tr></thead>
        <tbody>
        @forelse($vitals as $v)
            <tr><td>{{ $v->patient_name }}</td><td>{{ $v->temperature }}</td><td>{{ $v->blood_pressure }}</td>
            <td>{{ $v->heart_rate }}</td><td>{{ $v->height }}</td><td>{{ $v->weight }}</td><td>{{ $v->recorded_at }}</td></tr>
        @empty<tr><td colspan="7" class="muted">No vital signs.</td></tr>@endforelse
        </tbody>
    </table>
</div>
@endsection
