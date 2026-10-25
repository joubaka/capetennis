@php
  $changes = collect($preview['matches'])->map(function ($match) use ($originalRows) {
      $before = $originalRows->get($match['fixture_key']);
      $same = $before && collect(['scheduled_at','venue_id','court','duration'])->every(fn ($key) => (string) ($before[$key] ?? '') === (string) ($match[$key] ?? ''));
      return ['match'=>$match, 'before'=>$before, 'state'=>!$before ? 'New booking' : ($same ? 'Unchanged' : 'Changed')];
  });
@endphp
<section class="card card-body mb-3" aria-label="Review schedule changes">
  <h4 class="h5">Review before saving privately</h4>
  <p>{{ $changes->where('state','New booking')->count() }} new bookings / {{ $changes->where('state','Changed')->count() }} changed / {{ $changes->where('state','Unchanged')->count() }} unchanged proposals / {{ count($preview['existing_matches'] ?? []) }} existing bookings kept fixed in this planning window.</p>
  <p class="small text-muted">Played matches, unselected rounds and bookings on other days stay fixed. Fixed-booking count covers this preview's venue and time window. Publishing remains a separate step.</p>
  <details><summary style="min-height:44px">Before and after times and courts</summary>
    <div class="table-responsive"><table class="table"><thead><tr><th>Draw / match</th><th>Change</th><th>Before</th><th>After</th></tr></thead><tbody>
      @foreach($changes as $change)
        @php($match = $change['match'])
        @php($before = $change['before'])
        <tr><td>{{ $match['draw_name'] ?? '' }} / Match {{ $match['match'] ?? $match['fixture_id'] }}</td><td>{{ $change['state'] }}</td><td>@if($before){{ substr($before['scheduled_at'],0,16) }} / {{ $before['venue_name'] }} / Court {{ $before['court'] }} / {{ $before['duration'] }} min @else Unscheduled @endif</td><td>{{ substr($match['scheduled_at'],0,16) }} / {{ $venues->firstWhere('id',(int)$match['venue_id'])?->name }} / Court {{ $match['court'] }} / {{ $match['duration'] }} min</td></tr>
      @endforeach
    </tbody></table></div>
  </details>
</section>
