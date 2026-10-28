<nav class="rr-workspace-nav mb-3" aria-label="Draw workspace">
  @php($choosingFormat = request()->routeIs('draw.setup.show'))
  @unless($draw->isTeamDraw())
    <a class="btn {{ $choosingFormat ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('draw.setup.show', $draw) }}" @if($choosingFormat) aria-current="page" @endif>1 · Format</a>
  @endunless
  @foreach(array_merge(['groups' => ($draw->isTeamDraw() ? '' : '2 · ').'Players & Positions', 'matrix' => ($draw->isTeamDraw() ? '' : '3 · ').'Draw & Results', 'schedule' => ($draw->isTeamDraw() ? '' : '4 · ').'Schedule'], $draw->usesFlexibleMonrad() ? ['settings' => 'Setup & Rules'] : ['settings' => 'Draw settings', 'notes' => 'Rules & notes']) as $hash => $label)
    @php($activeWorkspaceLink = !$choosingFormat && ($workspaceTab ?? '') === $hash)
    <a class="btn {{ $activeWorkspaceLink ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('backend.draw.roundrobin.show', $draw) }}#{{ $hash }}" @if($activeWorkspaceLink) aria-current="page" @endif>{{ $label }}</a>
    @if($hash === 'schedule' && !$draw->isTeamDraw())
      <a class="btn btn-label-secondary" data-workspace-review href="{{ route('backend.draw.roundrobin.show', $draw) }}?review=publication#matrix">5 · Review &amp; publish</a>
    @endif
  @endforeach
</nav>
