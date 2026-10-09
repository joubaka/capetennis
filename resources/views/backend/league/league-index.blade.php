@extends('layouts.backend')

@section('title', 'Leagues')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')


@endsection

@section('page-script')

@endsection

@section('content')
<div class="operational-page">
@include('backend.partials.operational-controls', ['pageSearchLabel' => 'Find a region or league category'])
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
        @forelse($allData as $league)
        <tr data-page-row>
          <td>
            <h5>{{$league->name}}</h5>
          </td>
          <td>
            @foreach($league->categories as $category)
            <div>
              <span class="badge bg-label-primary m-1">{{$category->category_name}}</span>

            </div>


            @endforeach


          </td>

        </tr>
        @empty
        <tr><td colspan="2" class="text-center py-4">No league regions found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<p class="text-muted mt-3">League categories are grouped by region. This directory shows the existing categories.</p>

</div>
@endsection
