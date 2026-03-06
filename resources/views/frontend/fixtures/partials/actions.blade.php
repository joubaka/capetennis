

<a href="javascript:void(0);"
   class="btn btn-sm btn-outline-primary edit-score-btn"
   data-id="{{ $fixture->id }}"
   data-home="{{ e($homeLabel) }}"
   data-away="{{ e($awayLabel) }}"
   data-action="{{ route('frontend.fixtures.score.store', $fixture->id) }}"
   @foreach($fixture->fixtureResults as $r)
       data-set{{ $r->set_nr }}_home="{{ $r->team1_score }}"
       data-set{{ $r->set_nr }}_away="{{ $r->team2_score }}"
   @endforeach
>
    <i class="bi bi-clipboard-data"></i> Insert Score
</a>
@if($fixture->fixtureResults->count())
    <a href="javascript:void(0);"
       class="btn btn-sm btn-outline-danger delete-result-btn"
       data-id="{{ $fixture->id }}"
       data-action="{{ route('frontend.fixtures.score.delete', $fixture->id) }}">
        <i class="bi bi-trash"></i> Delete Result
    </a>
@endif
