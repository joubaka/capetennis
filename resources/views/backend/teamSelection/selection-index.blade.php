@php
$configData = Helper::appClasses();
@endphp
<?php

use App\Helpers\Fixtures;


?>
@extends('layouts/layoutMaster')

@section('title', 'Admin - Event Page')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')

<script src="{{asset('assets/js/draw-show.js')}}"></script>
<script src="{{asset('assets/js/head-office.js')}}"></script>
<script src="{{asset('assets/js/my-functions.js')}}"></script>
@endsection



@section('content')
<div class="row">

    <div class="col-12 col-sm-3 col-md-3">
        @include('backend.adminPage.admin_show.navbar.navbar')
    </div>

    <div class="col-12 col-sm-9 col-md-9">

        <div class="card">
            <div class="card-header">
                <h3>{{$event->name}}</h3>
            </div>


            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>test</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @foreach($categories as $keys => $age)
                            <tr>
                                <td>{{$age->category->name}} </td>

                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Player</th>
                                            <th>Matches</th>
                                            <th></th>
                                            <th>won</th>
                                            <th>lost</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                        @foreach($age->teams as $k => $team)
                                        
                                        @foreach($team->players as $key => $player)
                                        <tr>
                                            <td> {{$player->name}}</td>
                                            <td> {{$player->surname}}({{$team->regions->short_name}} - {{$key+1}})</td>

                                            <td>

                                                <table class="table">
                                                    @foreach(Fixtures::getTeamWins($player,$perAge,$age->category->name) as $m)
                                                        @if($m->fixture_players->team2->id == $player->id)
                                                            @if($m->teamResults->last()->team2_score > $m->teamResults->last()->team1_score)
                                                                    <tr  class="bg-label-success ">
                                                            @else
                                                                    <tr  class="bg-label-danger">
                                                            @endif
                                                        @else
                                                            @if($m->teamResults->last()->team1_score > $m->teamResults->last()->team2_score)
                                                                    <tr  class="bg-label-success">
                                                            @else
                                                                    <tr  class="bg-label-danger">
                                                            @endif
                                                        @endif
                                                        
                                                    @if($m->fixture_players->team2->id == $player->id)
                                                        <td>{{$m->fixture_players->team1->name}} {{$m->fixture_players->team1->surname}} </td>
                                                        <td>
                                                            @foreach($m->teamResults as $r)
                                                            {{$r->team2_score}} - {{$r->team1_score}}
                                                            @endforeach
                                                        </td>
                                                     @else
                                                        <td>{{$m->fixture_players->team2->name}} {{$m->fixture_players->team2->surname}}</td>
                                                        <td>
                                                            @foreach($m->teamResults as $r)
                                                            {{$r->team1_score}} - {{$r->team2_score}}

                                                            @endforeach
                                                        </td>
                                                        @endif

                                                    </tr>
                                                    @endforeach
                                                </table>



                                                <br>




                                            </td>
                                            <td></td>
                                            <td></td>
                                        </tr>

                                        @endforeach

                                        @endforeach
                                    </tbody>
                                </table>



                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>






@endsection