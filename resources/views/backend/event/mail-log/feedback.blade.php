@foreach(['success'=>['#0f5132','#d1e7dd'],'warning'=>['#513c06','#fff3cd'],'error'=>['#842029','#f8d7da']] as $level=>$colours)
@if(session($level))<div class="alert" role="status" style="color:{{ $colours[0] }};background:{{ $colours[1] }}">{{ session($level) }}</div>@endif
@endforeach
