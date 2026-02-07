@php
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'WC' => 'bg-label-primary',
            'CT' => 'bg-label-info',
            'OB' => 'bg-label-success',
            'SW' => 'bg-label-warning',
            'BO' => 'bg-label-danger',
            'WP' => 'bg-label-dark',
        ];

        $palette = [
            'bg-label-primary','bg-label-success','bg-label-warning',
            'bg-label-danger','bg-label-info','bg-label-dark','bg-label-secondary'
        ];

        return $map[$short] ?? $palette[abs(crc32($short)) % count($palette)];
    }
}
@endphp<td class="away-cell">
  ({{ $team_fixture->away_rank_nr }})
  {{ $team_fixture->team2->pluck('full_name')->implode(' + ') ?: 'TBD' }}
  @if($team_fixture->region2Name?->short_name)
    <span class="badge rounded-pill {{ region_badge_class($team_fixture->region2Name->short_name) }} ms-1">
      {{ $team_fixture->region2Name->short_name }}
    </span>
  @endif
</td>
