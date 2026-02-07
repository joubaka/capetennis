@extends('layouts/layoutMaster')

@section('title', 'draw')

@section('vendor-style')

@endsection

@section('vendor-script')



@endsection

@section('page-style')

@endsection

@section('page-script')
<script src="{{asset('assets/js/draw-show-ver3.js')}}"></script>

@endsection

@section('content')

<div class="col-12">

        <h5 class="card-header"> {{$draw->drawName}} <a href="{{route('frontend.showDraw',$draw->id)}}" class="btn btn-primary btn-sm">Show</a></h5>
      <div class="card-body">

       @include('bracket.partials.fixtures')
      </div>








</div>

<!-- Modal HTML -->
<div class="modal fade" id="tennisResultModal" tabindex="-1" aria-labelledby="tennisResultModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tennisResultModalLabel">Insert Tennis Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="tennisResultForm" >
        @csrf
        <div class="modal-body">
          <!-- Player 1 -->
          <div class="mb-3">
            <label for="player1" class="form-label" >Player 1</label>
            <input type="text"  class="form-control" id="registration-1-name" name="player1" required>
          </div>
          <!-- Player 2 -->
          <div class="mb-3">
            <label for="player2" class="form-label" >Player 2</label>
            <input type="text"  class="form-control"  id="registration-2-name" name="player2" required>
          </div>
          <!-- Set 1 -->
          <div class="row mb-3">
            <div class="col">
              <label for="set1_player1" class="form-label">Set 1 (Player 1)</label>
              <input type="number" class="form-control" id="set1_player1" name="set_player1[]" required min="0">
            </div>
            <div class="col">
              <label for="set1_player2" class="form-label">Set 1 (Player 2)</label>
              <input type="number" class="form-control" id="set1_player2" name="set_player2[]" required min="0">
            </div>
          </div>
          <!-- Set 2 -->
          <div class="row mb-3">
            <div class="col">
              <label for="set2_player1" class="form-label">Set 2 (Player 1)</label>
              <input type="number" class="form-control" id="set2_player1" name="set_player1[]"  min="0">
            </div>
            <div class="col">
              <label for="set2_player2" class="form-label">Set 2 (Player 2)</label>
              <input type="number" class="form-control" id="set2_player2" name="set_player2[]"  min="0">
            </div>
          </div>
          <!-- Set 3 (optional) -->
          <div class="row mb-3">
            <div class="col">
              <label for="set3_player1" class="form-label">Set 3 (Player 1)</label>
              <input type="number" class="form-control" id="set3_player1" name="set_player1[]" min="0">
            </div>
            <div class="col">
              <label for="set3_player2" class="form-label">Set 3 (Player 2)</label>
              <input type="number" class="form-control" id="set3_player2" name="set_player2[]" min="0">
            </div>
          </div>
        </div>
        <input type="hidden" name="fixture_id" id="fixture_id">
        <input type="hidden" name="type" value="individual">
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save Result</button>
        </div>
      </form>
    </div>
  </div>
</div>


@endsection
