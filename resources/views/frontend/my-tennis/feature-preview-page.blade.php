@extends('layouts/contentNavbarLayout')
@section('title', 'Preview Plus')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace">
  <x-player-workspace-header compact :title="$ratingPlayer->full_name" eyebrow="My Tennis · Preview Plus" data-player-profile-header>
    <x-slot:navigation>
      @include('frontend.my-tennis.dashboard-tabs', [
        'headerPlayer' => $ratingPlayer,
        'headerRelated' => true,
        'headerCanViewRating' => false,
        'headerQuery' => ['player' => $ratingPlayer->id],
        'workspaceTab' => $previewFeature === 'events' ? 'rating' : $previewFeature,
        'workspace' => ['related' => true],
      ])
    </x-slot:navigation>
  </x-player-workspace-header>
  <div class="card p-3 p-sm-4" data-player-plus-preview-page>
    <style>
      .plus-sample{background:#fff;border:1px solid #cbd9e9;border-radius:.75rem;margin:1rem 0;padding:1rem}
      .plus-sample dl{display:grid;grid-template-columns:repeat(auto-fit,minmax(8rem,1fr));gap:.75rem;margin:.75rem 0 0}
      .plus-sample dl>div{background:#f1f5fa;border-radius:.5rem;padding:.75rem;min-width:0}
      .plus-sample dd[aria-hidden]{filter:blur(4px);font-size:1.15rem;font-weight:700;margin:0;user-select:none;overflow-wrap:anywhere}
    </style>
    @include('frontend.my-tennis.feature-preview', ['showSample' => true])
  </div>
</div>
@endsection
