<nav class="rr-workspace-nav mb-3" aria-label="Draw workspace">
  @foreach(array_merge(['groups' => 'Players & Positions', 'matrix' => 'Draw & Results', 'schedule' => 'Schedule'], $draw->usesFlexibleMonrad() ? ['settings' => 'Setup & Rules'] : ['settings' => 'Draw settings', 'notes' => 'Rules & notes']) as $hash => $label)
    <a class="btn {{ ($workspaceTab ?? '') === $hash ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('backend.draw.roundrobin.show', $draw) }}#{{ $hash }}">{{ $label }}</a>
  @endforeach
</nav>
