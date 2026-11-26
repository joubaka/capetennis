@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
$configData = Helper::appClasses();

/* Display elements */
$customizerHidden = ($customizerHidden ?? '');

@endphp

@extends('layouts/commonMaster' )

@section('layoutContent')

<!-- Content -->
<div style="display:contents" @if(auth()->user()?->hasRole('super-user') && app(\App\Support\SuperAdminControls::class)->exclusive(request()->route())) data-super-admin-page @endif>
@yield('content')
</div>
<!--/ Content -->

@endsection
