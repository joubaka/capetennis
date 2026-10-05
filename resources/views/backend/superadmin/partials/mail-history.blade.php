@php
    $mailHistoryUrl = request()->routeIs('backend.superadmin.workspace') ? route('backend.superadmin.workspace') : route('backend.superadmin.mail-history');
    $reportContext = request()->routeIs('backend.superadmin.workspace') ? ['tab'=>'mails'] : [];
@endphp
@include('backend.partials.mail-report',['report'=>$mailReport,'reportUrl'=>$mailHistoryUrl,'reportTitle'=>'All tracked email history','reportEvent'=>null])
