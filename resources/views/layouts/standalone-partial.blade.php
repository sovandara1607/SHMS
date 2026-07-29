@extends('layouts.app')
@section('content')
<div x-data="{ show: true }" x-show="show" class="mx-auto max-w-2xl">
    <div class="rounded-xl border border-manila/60 bg-paper-card shadow-sm">
        {!! $content !!}
    </div>
</div>
@endsection
