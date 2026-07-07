@extends('layouts.app')
@section('content')
<h2 class="page-title">New Medical Record</h2>
<div class="card">
<form method="post" action="/medical-records">
    @csrf
    <div class="row">
        <div><label>Patient *</label><select name="patient_id" required>
            <option value="">— select —</option>
            @foreach($patients as $p)<option value="{{ $p->patient_id }}">{{ $p->fullName() }}</option>@endforeach
        </select></div>
        <div><label>Doctor *</label><select name="doctor_id" required>
            <option value="">— select —</option>
            @foreach($doctors as $d)<option value="{{ $d->doctor_id }}">{{ optional($d->staff)->first_name }} {{ optional($d->staff)->last_name }}</option>@endforeach
        </select></div>
    </div>
    <label>Symptoms</label><textarea class="input" name="symptoms" rows="2">{{ old('symptoms') }}</textarea>
    <label>Diagnosis *</label><textarea class="input" name="diagnosis" rows="2" required>{{ old('diagnosis') }}</textarea>
    <label>Treatment notes</label><textarea class="input" name="treatment_notes" rows="3">{{ old('treatment_notes') }}</textarea>
    <button class="btn" type="submit">Create record</button>
    <a class="btn gray" href="/medical-records">Cancel</a>
</form>
</div>
@endsection
