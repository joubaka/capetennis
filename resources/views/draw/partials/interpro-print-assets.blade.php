{{-- Trusted application modules only: print popups must not depend on fetching assets. --}}
<style>{!! file_get_contents(public_path('css/flexible-monrad.css')) !!}
{!! file_get_contents(public_path('css/tennis-bracket.css')) !!}</style>
@foreach(['js/tennis-bracket-layout.js', 'js/tennis-bracket.js', 'js/interpro-bracket.js'] as $module)
<script>{!! str_ireplace('</script', '<\/script', file_get_contents(public_path($module))) !!}</script>
@endforeach
