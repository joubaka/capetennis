@extends('layouts.layoutMaster')

@php
$breadcrumbs = [['link' => 'home', 'name' => 'Home'], ['link' => 'javascript:void(0)', 'name' => 'User'], ['name' => 'Profile']];
@endphp

@section('title', 'Profile')


@section('content')

  @if (Laravel\Fortify\Features::canUpdateProfileInformation())
   <div class="mb-4">

   </div>
  @endif

  @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
    <div class="mb-4">

    </div>
  @endif

  @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
   <div class="mb-4">

   </div>
  @endif

  <div class="mb-4">

  </div>

  @if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
  
  @endif

@endsection
