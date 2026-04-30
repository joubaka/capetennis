@extends('layouts/layoutMaster')

@section('title', 'Rankings')

@section('content')

<div class="row">
    <div class="col-12">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Rankings</h5>
            </div>
            <div class="card-body">
                @if($series->isEmpty())
                    <p class="text-muted">No rankings are published yet.</p>
                @else
                    <div class="row g-3">
                        @foreach($series as $s)
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <a href="{{ route('frontend.ranking.show', $s->id) }}"
                               class="card text-decoration-none h-100 border-0 shadow-sm">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="avatar flex-shrink-0">
                                        <span class="avatar-initial rounded-circle bg-label-primary">
                                            <i class="ti ti-trophy"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-body">{{ $s->name }}</div>
                                        @if($s->year)
                                            <small class="text-muted">{{ $s->year }}</small>
                                        @endif
                                    </div>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection
