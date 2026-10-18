<section aria-labelledby="playoff-preview-heading">
  <h6 id="playoff-preview-heading">Provisional playoff preview</h6>
  @if($svgData)
    <p class="small text-muted">This shows qualifying positions from your saved groups and playoff settings. Players are confirmed after group results. No playoff matches have been created.</p>
    @include('backend.draw.roundrobin.dynamic-bracket-svg', ['emptyBracket' => true])
  @else
    <p>{{ $previewMessage }}</p>
    <a class="btn btn-outline-primary" href="{{ route('backend.draw.roundrobin.show', $draw) }}#{{ $previewTarget ?? ($draw->groups()->exists() ? 'settings' : 'groups') }}">{{ $previewAction ?? ($draw->groups()->exists() ? 'Open playoff settings' : 'Open Players & Groups') }}</a>
  @endif
</section>
