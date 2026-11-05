@foreach($matchPage['rows'] as $match)
<article class="record-row"><time datetime="{{ $match['date'] }}">{{ $match['date'] }}</time><div><p><strong>{{ $match['opponent'] }}</strong></p><p>{{ $match['event'] }}</p>@if($match['date_basis'] === 'event start proxy')<small class="record-note">Estimated date</small>@endif</div><div class="record-score">{{ $match['won'] ? 'Win' : 'Loss' }}<br>{{ $match['score'] }}</div></article>
@endforeach
@include('frontend.my-tennis.record-pagination')
