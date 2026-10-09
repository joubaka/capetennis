@extends('layouts.backend')

@section('title', 'Create series')

@section('vendor-style')

@endsection

@section('page-style')

@endsection

@section('vendor-script')

@endsection

@section('page-script')
<script>
document.getElementById('ranking-rule-preset')?.addEventListener('change', event => {
    const selected = event.target.selectedOptions[0];
    if (selected?.dataset.bestCount) {
        document.getElementById('best-scores-count').value = selected.dataset.bestCount;
    }
});
</script>

@endsection

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls')

<div class="card">
    <div class="card-header"><h4 class="mb-1">Create series</h4><p class="small text-muted mb-0">Set up the series first. Add events, configure points and build rankings separately.</p></div>
    @if($errors->any())
      <div class="alert alert-danger m-3">
        <ul class="mb-0">
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    <div class="card-body">
        <form action="{{route('series.store')}}" method="post">
            @csrf
            <div class="col-6">
                <div class="mb-3 row">
                    <label for="html5-text-input" class="col-md-4 col-form-label">Series Name</label>
                    <div class="col-md-8">
                        <input class="form-control" name="name" required maxlength="255" type="text" value="{{ old('name') }}" id="html5-text-input">
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="ranking-rule-preset" class="col-md-4 col-form-label">Ranking Rules Preset</label>
                    <div class="col-md-8">
                        <select name="ranking_rule_preset_id" id="ranking-rule-preset" class="form-select">
                            <option value="">Custom rules</option>
                            @foreach($rankingRulePresets as $preset)
                              <option value="{{ $preset->id }}" data-best-count="{{ $preset->rules['best_num_of_scores'] }}" @selected((string) old('ranking_rule_preset_id') === (string) $preset->id)>
                                {{ $preset->name }}{{ $preset->is_system ? ' (built-in)' : '' }}
                              </option>
                            @endforeach
                        </select>
                        <div class="form-text">The preset supplies the best-results count and ranking tie-break rules.</div>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="best-scores-count" class="col-md-4 col-form-label">Best nr of Scores </label>
                    <div class="col-md-8">
                        <select name="numScores" id="best-scores-count" required class="form-select">
                            <option value="">Please select</option>
                            <option value="1" @selected((string) old('numScores') === '1')>1</option>
                            <option value="2" @selected((string) old('numScores') === '2')>2</option>
                            <option value="3" @selected((string) old('numScores') === '3')>3</option>
                            <option value="4" @selected((string) old('numScores') === '4')>4</option>
                            <option value="5" @selected((string) old('numScores') === '5')>5</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                    <label for="defaultSelect" class="col-md-4 col-form-label">Ranking Type </label>
                    <div class="col-md-8">
                        <select name="rankType" id="defaultSelect" required class="form-select">
                            <option value="">Please select</option>
                            @foreach($rankingTypes as $rankType)
                            <option value="{{$rankType->id}}" @selected((string) old('rankType') === (string) $rankType->id)>{{$rankType->type}}</option>
                            @endforeach

                        </select>
                    </div>
                </div>
                <div class="mb-3 row">
                  <label for="series-year" class="col-md-4 col-form-label">Year</label>
                  <div class="col-md-8">
                    <select name="year" id="series-year" class="form-select" required>
                      <option value="">Select Year</option>
                      @for ($y = now()->year; $y <= 2030; $y++)
                        <option value="{{ $y }}" @selected((string) old('year') === (string) $y)>{{ $y }}</option>
                      @endfor
                    </select>
                  </div>
                </div>

            </div>
            <button class="btn btn-primary btn-sm" type="submit">Save</button>
        </form>

    </div>
</div>



</div>
@endsection
