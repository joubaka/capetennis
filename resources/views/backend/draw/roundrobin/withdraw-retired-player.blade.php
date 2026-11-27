@if($fixture->stage === 'RR' && $fixture->isRetired() && !$fixture->isRoundRobinVoided())
  @can('voidRoundRobin', $draw)
    <form method="post" action="{{ route('backend.roundrobin.void-retired-player', [$draw, $fixture]) }}" class="mt-2 p-3 border border-danger rounded">
      @csrf
      <input type="hidden" name="revision" value="{{ app(\App\Services\Draw\RoundRobinWithdrawalService::class)->revision($fixture) }}">
      <p class="small mb-2">{{ $fixture->retiredRegistrationId() === (int) $fixture->registration1_id ? $fixture->registration1?->display_name : $fixture->registration2?->display_name }} retired. Withdraw this player from this round-robin group?</p>
      <label class="d-flex gap-2 small mb-2"><input type="checkbox" name="confirm_void" value="1" required>Void all this player's matches in this group, including earlier results. Keep scores as history and exclude them from standings.</label>
      <button class="btn btn-sm btn-outline-danger" type="submit">Withdraw player and void matches</button>
    </form>
  @endcan
@endif
