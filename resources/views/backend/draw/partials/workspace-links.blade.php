<nav class="rr-workspace-nav mb-3" aria-label="Draw workspace">
  @php($choosingFormat = request()->routeIs('draw.setup.show'))
  @unless($draw->isTeamDraw())
    <a class="btn {{ $choosingFormat ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('draw.setup.show', $draw) }}" @if($choosingFormat) aria-current="page" @endif>Format</a>
  @endunless
  @foreach(array_merge(['settings' => $draw->usesFlexibleMonrad() ? 'Setup & Rules' : 'Draw settings', 'groups' => 'Players & Positions', 'matrix' => 'Draw & Results'], $draw->isTeamDraw() ? ['schedule' => 'Schedule'] : [], $draw->usesFlexibleMonrad() ? [] : ['notes' => 'Rules & notes']) as $hash => $label)
    @php($activeWorkspaceLink = !$choosingFormat && ($workspaceTab ?? '') === $hash)
    <a class="btn {{ $activeWorkspaceLink ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('backend.draw.roundrobin.show', $draw) }}#{{ $hash }}" @if($activeWorkspaceLink) aria-current="page" @endif>{{ $label }}</a>
  @endforeach
</nav>
