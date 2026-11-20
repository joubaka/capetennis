@extends('layouts.backend')
@section('title', 'Stage One financial calculator')
@section('content')
@include('backend.marketing.header')
@php($rand = fn($cents) => 'R'.number_format($cents / 100, 2))
<div class="card mb-4"><div class="card-body">
<h2 class="h5">Stage One event projection</h2>
<p>Plan a small Stage One event using the default category limits and fees. Change paid entries to explore attendance.</p>
<p class="small text-muted">The Stage One small-event template has 52 places across four categories.</p>
<form method="post" action="{{ route('superadmin.marketing.calculate') }}">@csrf
<div class="table-responsive" style="max-width:100%;min-width:0"><table class="table"><thead><tr><th>Category</th><th>Entry limit</th><th>Paid entries</th><th>Entry fee (R)</th></tr></thead><tbody>
@foreach(\App\Services\Marketing\PanarottisCalculator::CATEGORIES as $i => $category)<tr><th scope="row">{{ $category }}</th>@foreach(['limits' => 'Entry limit', 'entries' => 'Paid entries', 'fees' => 'Entry fee'] as $field => $label)<td><input style="min-width:6rem" class="form-control" type="number" min="0" max="{{ $field === 'fees' ? 100000 : 1600 }}" step="{{ $field === 'fees' ? '0.01' : '1' }}" required name="{{ $field }}[{{ $i }}]" id="{{ $field }}-{{ $i }}" aria-label="{{ $category }} {{ $label }}" value="{{ old($field.'.'.$i, $input[$field][$i]) }}"></td>@endforeach</tr>@endforeach
</tbody></table></div>
<div class="row g-3 mt-1">
@foreach(['processing_percent' => ['Processing allowance (%)', 100, '0.01'], 'platform_fee' => ['Cape Tennis per entry (R)', 100000, '0.01'], 'convenor' => ['Convenor for morning (R)', 100000, '0.01'], 'courts' => ['Courts rented', 100, '1'], 'hours' => ['Rental hours per court', 24, '0.01'], 'rental' => ['Rental per court/hour (R)', 100000, '0.01'], 'other_costs' => ['Other expenses (R)', 100000, '0.01']] as $field => $settings)
<div class="col-12 col-sm-6 col-lg-4"><label class="form-label" for="{{ $field }}">{{ $settings[0] }}</label><input class="form-control" type="number" min="0" max="{{ $settings[1] }}" step="{{ $settings[2] }}" required name="{{ $field }}" id="{{ $field }}" value="{{ old($field, $input[$field]) }}"></div>
@endforeach
</div><div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary">Calculate projection</button><a class="btn btn-outline-secondary" href="{{ route('superadmin.marketing.calculator') }}">Reset to full Stage One example</a></div>
</form></div></div>
@if(!$errors->any())
<div class="card"><div class="card-body">
<h2 class="h5">Projected academy finances</h2>
<div class="table-responsive" style="max-width:100%;min-width:0"><table class="table"><thead><tr><th>Category</th><th>Paid entries</th><th>Entry fee</th><th>Collected</th><th>Processing</th><th>Cape Tennis</th><th>Retained before costs</th></tr></thead><tbody>
@foreach($result['rows'] as $row)<tr><th scope="row">{{ $row['name'] }}</th><td>{{ $row['entries'] }}</td>@foreach(['fee', 'gross', 'processing', 'platform', 'retained'] as $field)<td class="text-nowrap">{{ $rand($row[$field]) }}</td>@endforeach</tr>@endforeach
<tr class="fw-bold"><th>Total</th><td>{{ $result['totals']['entries'] }}</td><td>—</td>@foreach(['gross', 'processing', 'platform', 'retained'] as $field)<td class="text-nowrap">{{ $rand($result['totals'][$field]) }}</td>@endforeach</tr>
</tbody></table></div>
<dl class="row mt-3">@foreach(['convenor' => 'Convenor', 'rental' => 'Court rental', 'other_costs' => 'Other expenses'] as $field => $label)<dt class="col-7">{{ $label }}</dt><dd class="col-5 text-end">{{ $rand($result['totals'][$field]) }}</dd>@endforeach<dt class="col-7 fs-5">Academy has left</dt><dd class="col-5 text-end fs-5 fw-bold" data-academy-remaining>{{ $rand($result['totals']['remaining']) }}</dd></dl>
<p class="small text-muted mb-0">Projection only, not a settlement or payment. Other expenses default to zero: allow for balls, prizes and any further costs. Processing is estimated per paid entry, rounded to cents. The 5% default is a planning allowance, not a verified provider tariff. Ten courts for four hours is a budget assumption; confirm scoring, rest and court allocation in an actual schedule. No event, fee setting or financial record is changed.</p>
</div></div>
@else
<p class="text-muted">Correct the inputs and calculate again to see an updated projection.</p>
@endif
@endsection
