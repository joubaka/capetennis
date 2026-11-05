<div class="border rounded p-3">
  <h6 class="mb-1">Withdraw from this team</h6>
  <p class="small text-muted mb-2">Free this roster spot and review what happens to the original payment before you confirm.</p>
  <a class="btn btn-outline-primary" data-roster-withdrawal-link href="{{ route('backend.team.withdrawal.show', [$event, $team, $slot]) }}">Review withdrawal / refund</a>
</div>
