{{-- Reusable: health item table --}}
<table class="table table-sm table-borderless mb-0">
  <tbody>
    @foreach($items as $item)
    <tr class="health-row">
      <td style="width:2rem; text-align:center; vertical-align:middle">
        @if($item['status'] === 'ok')     <span class="badge-ok"     style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        @elseif($item['status'] === 'warn')    <span class="badge-warn"    style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        @else                             <span class="badge-critical" style="font-size:.65rem; padding:.2em .4em; border-radius:3px">●</span>
        @endif
      </td>
      <td>
        <div class="health-value">{{ $item['label'] }}</div>
        @if(!empty($item['detail']))
          <div class="health-detail">{{ $item['detail'] }}</div>
        @endif
      </td>
      <td class="text-end pe-3">
        <span class="health-value">{{ $item['value'] }}</span>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>
