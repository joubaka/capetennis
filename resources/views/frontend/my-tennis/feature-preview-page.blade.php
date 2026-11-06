@extends('layouts/contentNavbarLayout')
@section('title', 'Preview Plus')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y ct-player-workspace">
  @include('frontend.my-tennis.profile-header', [
    'canViewRating' => false,
    'ratingPreviewPlayer' => $ratingPlayer,
    'workspaceTab' => $previewFeature === 'events' ? 'rating' : $previewFeature,
  ])
  <script defer src="{{ asset('assets/js/my-tennis-player-switch.js') }}?v={{ filemtime(public_path('assets/js/my-tennis-player-switch.js')) }}"></script>
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
