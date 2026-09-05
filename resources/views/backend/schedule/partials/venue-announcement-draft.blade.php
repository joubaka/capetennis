<p>Dear Player,</p>

<p>The courts and venues for <strong>{{ $event->name }}</strong> have now been assigned.</p>

<p><strong>The following venues and courts will be used:</strong></p>

<ul>
  @foreach($assignments as $assignment)
    <li>
      <strong>{{ $assignment['name'] }}</strong>
      <ul>
        @foreach($assignment['venues'] as $venue)
          <li>
            {{ $venue['name'] }} —
            @foreach($venue['courts'] as $court)
              {{ preg_match('/^court\b/i', $court) ? $court : 'Court '.$court }}{{ !$loop->last ? ', ' : '' }}
            @endforeach
          </li>
        @endforeach
      </ul>
    </li>
  @endforeach
</ul>

<p>Please check the event page regularly for any further updates.</p>

<p>Kind regards,<br>Cape Tennis</p>
