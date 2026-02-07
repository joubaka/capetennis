@php
$configData = Helper::appClasses();
@endphp

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
<script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js" integrity="sha512-EmfT33UCuNEdtd9zuhgQClh7gidfPpkp93WO8GEfAP3cLD++UM1AG9jsTUitCI9DH5nF72XaFePME92r767dHA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endsection

@section('page-script')

<script src="{{asset('assets/js/draw-team-show.js')}}"></script>
<script src="{{asset('assets/js/app-email.js')}}"></script>
<script src="{{asset('assets/js/ui-toasts.js')}}"></script>
<script src="{{asset('assets/js/extended-ui-drag-and-drop.js')}}"></script>
<script src="{{asset('assets/vendor/js/menu.js')}}"></script>
@endsection



@section('content')




@include('backend.fixture.fixturesPerVenue')

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
                <input type="hidden" name="type" value="team">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="result-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content col-12">
            <form action="#" id="submit-result-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel1">Edit Score</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="table-responsive text-nowrap"> @if(isset($draw->settings))
                        <table class="table table-bordered table-sm">
                            <thead class=" thead-colored thead-light">
                                <tr class="info" style="border-top: 2px solid #000 !important;">
                                    <th class="text-right">Set:</th>



                                    @for($i = 0 ; $i < $draw->settings->num_sets; $i++)
                                        <th class="text-center ">{{($i+1)}}</th>


                                        @endfor

                                </tr>
                            </thead>

                            <tbody id="scoreBody">
                                <tr>
                                    <td>
                                        <div id="reg1name"></div>
                                    </td>

                                    @for($i = 0 ; $i < $draw->settings->num_sets; $i++)

                                        <td><input type="text" name="reg1Set[]" id="reg1ScoreSet{{$i+1}}" class="score form-control"></td>

                                        @endfor
                                </tr>
                                <tr>
                                    <td>
                                        <div id="reg2name"></div>
                                    </td>
                                    @for($i = 0 ; $i < $draw->settings->num_sets; $i++)

                                        <td><input type="text" name="reg2Set[]" id="reg2ScoreSet{{$i+1}}" class="score form-control"></td>

                                        @endfor
                                </tr>
                            </tbody>
                        </table> @endif
                    </div>

                </div>
                <input type="hidden" name="fixture_id" id="fixture_id">
                <input type="hidden" name="type" value="team">
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="submit-result-button" class="btn btn-primary">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection