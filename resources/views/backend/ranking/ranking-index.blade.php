@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')

@endsection

@section('page-style')

@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/app-dashboard.js')}}"></script>

@endsection

@section('content')

<div class=" mb-4">
    <h5 class="card-header">Series List</h5>
    <div class="table-responsive mb-3">
        <table class="table datatable-series border-top">
            <thead>
                <tr>
                    <th>Id</th>
                    <th>Series</th>
                    <th>Setup</th>
                    <th>Publish</th>
                    <th>Action</th>
                </tr>
            </thead>
        <tbody>
            @foreach($series as $serie)
            <tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            @endforeach
        </tbody>
        </table>
    </div>
</div>



@endsection