@props(['current' => 1])
<style>
  .pp-stepper{margin:0 0 1.5rem;color:#172e45;overflow-wrap:anywhere}.pp-stepper ol{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;list-style:none;padding:0;margin:0}.pp-stepper li{border-top:3px solid #dce5ef;padding-top:.75rem;color:#617482;font-size:.875rem;line-height:1.4}.pp-stepper li[aria-current=step]{border-color:#004177;color:#172e45;font-weight:700}.pp-stepper li[data-complete=true]{border-color:#004177}.pp-stepper a{color:#004177;display:inline-flex;align-items:center;min-height:44px;gap:.5rem}.pp-stepper span{display:inline-flex;align-items:center;gap:.5rem;min-height:44px}.pp-stepper b{display:inline-grid;place-items:center;border:1px solid #c7d7e6;border-radius:50%;width:1.7rem;height:1.7rem;flex-shrink:0;font-size:.8rem}.pp-stepper [aria-current=step] b{background:#004177;border-color:#004177;color:#fff}.pp-stepper a:focus-visible{outline:3px solid #ff9f43;outline-offset:3px}@media(max-width:575.98px){.pp-stepper ol{grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem 1rem}.pp-stepper li{padding-top:.3rem}}
.pp-stepper [hidden]{display:none!important}.pp-stepper button{display:inline-flex;align-items:center;gap:.5rem;background:none;border:0;padding:0;min-height:44px;color:#004177;font:inherit;text-align:left}.pp-stepper button:focus-visible{outline:3px solid #ff9f43;outline-offset:3px}
</style>
<nav class="pp-stepper" aria-label="Player Plus steps" data-plus-stepper>
  <ol>
    @foreach([1 => 'Discover Player Plus', 2 => 'Choose a player', 3 => 'Choose a plan', 4 => 'Review & pay'] as $number => $label)
      <li data-plus-step="{{ $number }}" data-complete="{{ $number < $current ? 'true' : 'false' }}" @if($number === (int) $current) aria-current="step" @endif>
        @if($number < $current && $number <= 2)
          <a href="{{ $number === 1 ? route('ctr.index') : route('player-plus.index') }}"><b aria-hidden="true">{{ $number }}</b>{{ $label }}</a>
        @else
          @if($number === 3 && (int) $current === 3)<button type="button" data-plus-step-back hidden><b aria-hidden="true">3</b>{{ $label }}</button>@endif
          <span><b aria-hidden="true">{{ $number }}</b>{{ $label }}</span>
        @endif
      </li>
    @endforeach
  </ol>
</nav>
