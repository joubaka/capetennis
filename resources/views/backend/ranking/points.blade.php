@extends('layouts/layoutMaster')

@section('title', $series->name . ' – Points Allocation')

@section('page-style')
  {{-- Toastr CSS --}}
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

  <style>
    .point-input {
      max-width: 160px;
    }
  </style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-4">
    <div class="card-body d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Points Allocation</h4>
        <div class="text-muted">{{ $series->name }}</div>
      </div>

      <button id="save-points" class="btn btn-success">
        <i class="ti ti-device-floppy me-1"></i>
        Save Points
      </button>
    </div>
  </div>

  {{-- POINTS TABLE --}}
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th style="width:120px;">Position</th>
            <th>Points</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $row)
            <tr>
              <td><strong>#{{ $row['position'] }}</strong></td>
              <td>
                <input type="number"
                       class="form-control point-input"
                       data-position="{{ $row['position'] }}"
                       value="{{ $row['score'] }}"
                       min="0">
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection

@section('page-script')
  {{-- jQuery (required by Toastr) --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

  {{-- Toastr JS --}}
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

  <script>
    // ------------------------------
    // Toastr defaults
    // ------------------------------
    toastr.options = {
      closeButton: true,
      progressBar: true,
      positionClass: 'toast-top-right',
      timeOut: 3000
    };

    // ------------------------------
    // Save points
    // ------------------------------
    document.getElementById('save-points').addEventListener('click', () => {
      const points = [];

      document.querySelectorAll('.point-input').forEach(input => {
        points.push({
          position: parseInt(input.dataset.position),
          score: parseInt(input.value || 0)
        });
      });

      fetch('{{ route('ranking.points.update', $series) }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ points })
      })
      .then(res => {
        if (!res.ok) throw new Error('Request failed');
        return res.json();
      })
      .then(res => {
        toastr.success(res.message || 'Points saved successfully');
      })
      .catch(err => {
        console.error(err);
        toastr.error('Failed to save points');
      });
    });
  </script>
@endsection
