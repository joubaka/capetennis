<nav class="rr-workspace-nav mb-3" aria-label="Draw workspace">
  @foreach(['groups' => 'Players & Positions', 'matrix' => 'Draw & Results', 'schedule' => 'Schedule', 'settings' => 'Setup & Rules'] as $hash => $label)
    <a class="btn {{ ($workspaceTab ?? '') === $hash ? 'btn-primary' : 'btn-label-secondary' }}" href="{{ route('backend.draw.roundrobin.show', $draw) }}#{{ $hash }}">{{ $label }}</a>
  @endforeach
</nav>
