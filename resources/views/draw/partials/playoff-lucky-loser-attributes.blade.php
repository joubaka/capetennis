@if(isset($fixture, $slot) && $fixture && $fixture->stage !== 'RR' && $fixture->stage !== 'FM'
    && (int) $fixture->match_status === 0 && !$fixture->winner_registration
    && $fixture->getAttribute('registration'.$slot.'_id') && request()->routeIs('backend.*'))
  @can('replacePlayoffLuckyLoser', $fixture->draw)
    role="button" tabindex="0" aria-haspopup="menu" aria-expanded="false"
    data-ll-fixture="{{ $fixture->id }}" data-ll-slot="{{ $slot }}" data-ll-registration="{{ $fixture->getAttribute('registration'.$slot.'_id') }}"
    data-ll-name="{{ $fixture->{'registration'.$slot}?->display_name }}" aria-label="Actions for {{ $fixture->{'registration'.$slot}?->display_name }}"
  @endcan
@endif
