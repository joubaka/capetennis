<details class="mb-3 border rounded p-3" data-rating-connections>
    <summary class="py-2" style="min-height:44px">Connect rating groups · {{ $plan['component_count'] }} separate groups</summary>
    <p class="mt-2">Minimum connections needed to join the current network: {{ $plan['minimum_links'] }}. These are evidence priorities, not scheduled matches or confirmed links.</p>
    <p class="small text-muted">First check existing results for these connections. Where results are missing, record suitable completed standard-singles matches through the normal scoring workflow. Coaches should select suitable opponents: age categories alone do not establish equal ability. Repeated results against different opponents are needed to assess reliability.</p>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Group needing a connection</th><th>Potential connection category</th><th>Players in group</th></tr></thead><tbody>
    @foreach($plan['links'] as $link)
        <tr><td>{{ $link['from_category'] ?? 'Category needs review' }}<br><a class="d-inline-flex align-items-center small" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['comparison_group' => $link['from']]) }}">View group {{ $link['from'] }}</a></td>
            <td>{{ $link['to_category'] ?? 'Category needs review' }}<br><a class="d-inline-flex align-items-center small" style="min-height:44px" href="{{ route('backend.player-performance.ratings', ['comparison_group' => $link['to']]) }}">View group {{ $link['to'] }}</a>@if($link['cross_gender'])<br><small>Needs a verified boys/girls singles connection.</small>@endif</td>
            <td>{{ $link['players_in_group'] }}</td></tr>
    @endforeach
    </tbody></table></div>
    <p class="small text-muted">Connections form a plan through existing groups; a proposed target may itself still need its connection. Recording a suggestion does not change a rating. Publication, identity, score, recency and playing-format checks still apply.</p>
    @if($plan['truncated'])<p class="small text-muted">Showing the first 25 groups and connection priorities.</p>@endif
    @if($plan['excluded'])<details><summary class="py-2" style="min-height:44px">Evidence currently excluded from the shared model</summary><ul>@foreach($plan['excluded'] as $reason => $count)<li>{{ $reason }}: {{ $count }} matches</li>@endforeach</ul></details>@endif
</details>
