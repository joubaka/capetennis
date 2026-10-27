@extends('layouts.layoutMaster')

@php
$breadcrumbs = [['link' => 'home', 'name' => 'Home'], ['link' => 'javascript:void(0)', 'name' => 'User'], ['name' => 'Profile']];
@endphp

@section('title', 'Profile')


@section('content')
  <div class="card mb-4"><div class="card-body"><h2 class="h5">Account statement</h2><p>Download your entry fee and clothing payment history, including recorded refunds.</p><a href="{{ route('account.statement') }}" class="btn btn-outline-primary">View account statement</a></div></div>

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
