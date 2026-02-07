@extends('layouts/layoutMaster')

@section('title', 'Ranking Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/app-dataTables.js')}}"></script>

@endsection

@section('content')

<div class="card">
    <h5 class="card-header pb-1">Wallet Transactions</h5>

    <div class="card-body">
        <table class="table" id="transactionTable">
            <thead>
                <th>ID</th>
                <th>Date</th>
                <th>User</th>
                <th>Type</th>
                <th>Amount</th>


            </thead>
            <tbody>
                @foreach($transactions as $transaction)
                <tr>
                    <td>{{$transaction->id}}</td>
                    <td>{{$transaction->created_at}}</td>
                    <td>{{$transaction->user->name}}</td>
                    <td>{{$transaction->type}}</td>
                    <td>{{$transaction->amount}}</td>
                </tr>
                @endforeach
            </tbody>
        </table>


    </div>

</div>



@endsection