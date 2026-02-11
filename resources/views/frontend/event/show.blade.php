@extends('layouts/layoutMaster')

@section('title', 'Event Details')

{{-- ================= VENDOR CSS ================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

{{-- ================= PAGE CSS ================= --}}
@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}">
@endsection

{{-- ================= VENDOR JS ================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('content')

<input type="hidden" id="event_id" value="{{ $event->id }}">

{{-- ================= HEADER ================= --}}
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="user-profile-header-banner">
        <img src="{{ asset('assets/img/pages/profile-banner.png') }}" class="rounded-top">
      </div>

      <div class="user-profile-header d-flex flex-column flex-sm-row mb-4">
        <div class="flex-shrink-0 mt-n2 mx-sm-0 mx-auto">
          <img
            src="{{ $event->logo ? asset('assets/img/logos/'.$event->logo) : asset('assets/img/misc/placeholder-logo.png') }}"
            class="rounded user-profile-img"
            alt="{{ $event->name }} logo">
        </div>

        <div class="flex-grow-1 mt-3 mt-sm-5">
          <div class="mx-4">
            <h4>{{ $event->name }}</h4>

            <ul class="list-inline d-flex gap-2 flex-wrap">
              @if($event->isIndividual())
                <li class="badge bg-label-success">
                  <i class="ti ti-users"></i>
                  Total Entries: {{ $event->registrations->count() }}
                </li>
              @endif
              <li class="list-inline-item">
                <i class="ti ti-map-pin"></i> {{ $event->venues }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ================= NAVBAR ================= --}}
<div class="row">
  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row mb-4 align-items-center">

@if($signUp === 'open' && $event->isIndividual())
  <a class="btn btn-success btn-sm m-2" href="{{ route('register.register',$event->id) }}">
    <i class="ti ti-user-check"></i> Sign Up
  </a>
@endif
@if( $event->isIndividual())
@foreach($userRegistrations as $registration)
  @php
    $canWithdraw = auth()->check()
      ? $registration->canWithdraw(auth()->user())
      : ['ok' => false];
  @endphp

@if($canWithdraw['ok'])
  <form method="POST"
        action="{{ route('registrations.withdraw', $registration) }}"
        class="d-inline">
    @csrf
    <button class="btn btn-danger btn-sm m-1">
      <i class="ti ti-x"></i>
      Withdraw {{ $registration->display_name }}
    </button>
  </form>
@endif
@if($registration->status === 'withdrawn')
  <div class="mt-1">

    @if($registration->refund_status === 'pending' && $registration->refund_method === 'bank')
      <span class="btn btn-outline-warning btn-sm disabled">
        <i class="ti ti-clock"></i>
        {{ $registration->display_name }} – Bank refund pending
      </span>

    @elseif($registration->refund_status === 'completed' && $registration->refund_method === 'bank')
      <span class="btn btn-outline-success btn-sm disabled">
        <i class="ti ti-check"></i>
        {{ $registration->display_name }} – Bank refund completed
      </span>

    @elseif($registration->refund_status === 'completed' && $registration->refund_method === 'wallet')
      <span class="btn btn-outline-primary btn-sm disabled">
        <i class="ti ti-wallet"></i>
        {{ $registration->display_name }} – Refunded to wallet
      </span>

    @else
      <span class="btn btn-outline-secondary btn-sm disabled">
        <i class="ti ti-minus"></i>
        {{ $registration->display_name }} – No refund
      </span>
    @endif

  </div>
@endif


@endforeach
@endif
@if(auth()->check() && (
  auth()->user()->hasRole('super-user') ||
  $event->admins->contains(auth()->id())
))
  <a class="btn btn-secondary m-2" href="{{ route('admin.events.overview',$event) }}">
    <i class="ti ti-shield ti-xs"></i> Administrator
  </a>


@endif

    </ul>
  </div>
</div>

{{-- ================= EVENT CONTENT ================= --}}
@switch($event->eventType)
  @case(5)  @include('frontend.event.eventTypes.cavaliers_trials') @break
  @case(6)  @include('frontend.event.eventTypes.individual') @break
  @case(9)  @include('frontend.event.eventTypes.parentChildDoubles') @break
  @case(3)
  @case(7)  @include('frontend.event.eventTypes.team') @break
  @case(13) @include('frontend.event.eventTypes.interpro') @break
@endswitch

@endsection

{{-- ================= PAGE JS (ONLY PLACE FOR JS) ================= --}}
@section('page-script')

<script>
'use strict';

window.APP_URL  = @json(url('/'));
window.EVENT_ID = @json($event->id);
window.auth = {
  loggedIn: @json(auth()->check()),
  loginUrl: @json(route('login'))
};
/* ===============================
   DEBUG: EVENT DATE DATA
=============================== */
window.EVENT_DATA = {
  id: {{ $event->id }},
  start_date: @json(optional($event->start_date)?->toDateTimeString()),
  end_date: @json(optional($event->end_date)?->toDateTimeString()),
  entry_deadline: @json(optional($event->entry_deadline)?->toDateTimeString()),
  withdrawal_deadline: @json(optional($event->withdrawal_deadline)?->toDateTimeString()),
};

console.log('[EVENT_DATA]', window.EVENT_DATA);


</script>

<script src="{{ asset('assets/js/pages-profile.js') }}"></script>
<script src="{{ asset('assets/js/forms-editors.js') }}"></script>
<script src="{{ asset('assets/js/select2-search-addon.js') }}"></script>
<script src="{{ asset(mix('js/event-show.js')) }}"></script>



@if(session('success'))
<script>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: @json(session('success')),
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true
  });
});
</script>
@endif

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: @json($errors->first()),
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true
  });
});
</script>

@endif
<script>
$(document).on('click', '.deleteFileButton', function (e) {
  e.preventDefault();

  const fileId = $(this).data('id');
  if (!fileId) return;

  const url = "{{ route('file.destroy', '__ID__') }}".replace('__ID__', fileId);

  Swal.fire({
    title: 'Delete file?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Delete'
  }).then((result) => {
    if (!result.isConfirmed) return;

    $.post(url, {
      _method: 'DELETE',
      _token: $('meta[name="csrf-token"]').attr('content')
    }).done(() => {
      location.reload();
    });
  });
});
</script>
<script>
$(document).ready(function () {
  console.log('loading frontedn now')
  /* ===============================
     OPEN CLOTHING ORDER MODAL
  =============================== */
  $('.clothing-order').on('click', function () {

    const playerId  = $(this).data('playerid');
    const playerName = $(this).data('name');
    const teamId    = $(this).data('team');
    const regionId  = $(this).data('region');
    const eventId   = $(this).data('eventid');

    // Title
    $('#clothingPlayerName').text(playerName);

    // Loader
    const $content = $('#clothing-order-content');
    $content.html('<div class="spinner-border text-primary"></div>');

    // Load order form
    $.ajax({
      url: "{{ route('get.region.clothing.items') }}",
      type: "POST",
      data: JSON.stringify({
        region_id: regionId,
        player_id: playerId,
        team_id: teamId,
        event_id: eventId
      }),
      contentType: "application/json",
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function (html) {
        $content.html(html);
      },
      error: function () {
        $content.html(
          '<div class="alert alert-danger">Failed to load clothing options.</div>'
        );
      }
    });

  });

  /* ===============================
     SAVE CLOTHING ORDER
  =============================== */
  $('#saveClothingOrder').on('click', function () {

    const form = $('#clothing-order-content').find('form');
    if (!form.length) return;

    $.ajax({
      url: form.attr('action'),
      type: 'POST',
      data: new FormData(form[0]),
      processData: false,
      contentType: false,
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'X-Requested-With': 'XMLHttpRequest'
      },
      success: function (data) {
        if (data.success) {
          Swal.fire('Saved', 'Clothing order saved successfully', 'success');

          const modalEl = document.getElementById('clothing-order-modal');
          bootstrap.Modal.getInstance(modalEl).hide();
        } else {
          Swal.fire('Error', data.message || 'Save failed', 'error');
        }
      },
      error: function () {
        Swal.fire('Error', 'Something went wrong', 'error');
      }
    });

  });

  /* ===============================
     TOGGLE CLOTHING OPTIONS
  =============================== */
  $(document).on('change', '.clothing-toggle', function () {

    const itemId = $(this).data('item');
    const $box = $('#options-' + itemId);

    if (this.checked) {
      $box.removeClass('d-none');
    } else {
      $box.addClass('d-none');

      // Reset inputs when unchecked
      $box.find('select').val('');
      $box.find('input[type="number"]').val(1);
    }

  });

});
</script>
<script>
/* ============================================================
   CLOTHING ORDER MODAL – FRONT-END LOGIC (DEBUG)
   ============================================================ */

  (function () {
  
  const modalEl = document.getElementById('clothing-order-modal');
  console.log('[ClothingModal] modal found:', !!modalEl);

  if (!modalEl) return;

  /* -------------------------------
     OPEN MODAL → LOAD ITEMS
  -------------------------------- */
  modalEl.addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    console.log('[ClothingModal] show modal triggered by:', btn);

    if (!btn) return;

    console.log('[ClothingModal] trigger dataset:', btn.dataset);

    $('#order_event_id').val(btn.dataset.eventid || '');
    $('#order_region_id').val(btn.dataset.region || '');
    $('#order_player_id').val(btn.dataset.playerid || '');
    $('#order_team_id').val(btn.dataset.team || '');
    $('#orderPlayerName').text(btn.dataset.name || '');

    $('#orderTotal').text('R0.00');
    $('#clothingOrderList').html(
      '<div class="alert alert-info mb-0">Loading clothing…</div>'
    );

    console.log('[ClothingModal] loading items for region:', btn.dataset.region);

    $.post(window.CLOTHING_ITEMS_URL, { region: btn.dataset.region })
      .done(function (html) {
        console.log('[ClothingModal] AJAX success – HTML length:', html?.length);
        $('#clothingOrderList').html(html);
      })
      .fail(function (xhr) {
        console.error('[ClothingModal] AJAX failed:', xhr);
        $('#clothingOrderList').html(
          '<div class="alert alert-danger mb-0">Failed to load clothing items.</div>'
        );
      });
  });

  /* -------------------------------
     TOGGLE ITEM OPTIONS
  -------------------------------- */
  $(document).on('change', '.item-toggle', function () {
    const card = $(this).closest('.clothing-item');
    console.log('[ClothingModal] item toggle:', card.data('item'), this.checked);

    if (this.checked) {
      card.find('.item-options').removeClass('d-none');
    } else {
      card.find('.item-options')
        .addClass('d-none')
        .find('select').val('').end()
        .find('input[type=number]').val(1);

      card.find('.item-total').text('R0.00');
    }

    updateTotals();
  });

  /* -------------------------------
     SIZE / QTY CHANGE
  -------------------------------- */
  $(document).on('change input', '.size-select, .qty-input', function () {
    console.log('[ClothingModal] option change:', this);
    updateTotals();
  });

  /* -------------------------------
     TOTAL CALCULATION
  -------------------------------- */
  function updateTotals() {
    let total = 0;

    $('.clothing-item').each(function () {
      const card = $(this);
      const itemId = card.data('item');

      if (!card.find('.item-toggle').is(':checked')) return;

      const price = parseFloat(card.data('price'));
      const qty   = parseInt(card.find('.qty-input').val(), 10) || 0;
      const size  = card.find('.size-select').val();

      console.log('[ClothingModal] calc item', {
        itemId, price, qty, size
      });

      if (!size || qty < 1) {
        card.find('.item-total').text('R0.00');
        return;
      }

      const itemTotal = price * qty;
      total += itemTotal;

      card.find('.item-total').text('R' + itemTotal.toFixed(2));
    });

    console.log('[ClothingModal] total updated:', total);
    $('#orderTotal').text('R' + total.toFixed(2));
  }

  /* -------------------------------
     SUBMIT → BUILD PAYLOAD
  -------------------------------- */
$('#clothingOrderForm').on('submit', function (e) {

  console.log('[ClothingModal] Building items payload');

  // remove old dynamic inputs
  $(this).find('.order-line').remove();

  let hasItems = false;

  $('.clothing-item').each(function () {
    const card = $(this);

    const checked = card.find('.item-toggle').is(':checked');
    if (!checked) return;

    const itemId = card.data('item');
    const size   = card.find('.size-select').val();
    const qty    = parseInt(card.find('.qty-input').val(), 10);

    console.log('[ClothingModal] item:', { itemId, size, qty });

    if (!size || qty < 1) return;

    hasItems = true;

    $('<input>', {
      type: 'hidden',
      name: `items[${itemId}][size]`,
      value: size,
      class: 'order-line'
    }).appendTo('#clothingOrderForm');

    $('<input>', {
      type: 'hidden',
      name: `items[${itemId}][qty]`,
      value: qty,
      class: 'order-line'
    }).appendTo('#clothingOrderForm');
  });

  if (!hasItems) {
    e.preventDefault();
    alert('Please select at least one item with size and quantity.');
    return false;
  }

  console.log('[ClothingModal] Payload built, submitting form');
  // allow normal submit
});


})();
</script>
<script>
$(document).on('click', '.clothing-order', function (e) {

  const isAuthenticated = {{ auth()->check() ? 'true' : 'false' }};

  if (!isAuthenticated) {
    e.preventDefault();
    e.stopPropagation();

    // redirect to login, then back here
    const redirectUrl = encodeURIComponent(window.location.href);
    window.location.href = "{{ route('login') }}?redirect=" + redirectUrl;
    return false;
  }

  // authenticated → allow modal to open
});
</script>
@endsection
