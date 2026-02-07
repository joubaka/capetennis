@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')


@endsection

@section('page-script')
<script src="{{asset('assets/js/league.js')}}"></script>
@endsection

@section('content')
<div class="card">
  <div class="card-header">
    <h5 class="card-title mb-0">Leagues</h5>
  </div>
  <div class="table-responsive">
    <table class="table ">
      <thead>
        <tr>
          <th>Region</th>
          <th>League Category</th>

        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @foreach($allData as $league)
        <tr>
          <td>
            <h5>{{$league->name}}</h5>
          </td>
          <td>
            @foreach($league->categories as $category)
            <div>
              <a href="#" class=" m-1 btn btn-sm btn-primary">{{$category->category_name}}</a>

            </div>


            @endforeach
            <div>
              <button data-bs-toggle="modal" data-bs-target="#addCategoryModal" class="addCategory btn btn-success btn-sm" data-region="{{$league}}">Add Category</button>

            </div>

          </td>

        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@include('backend.league.modals.addCategoryModal')
@endsection