@extends('layouts/layoutMaster')

@section('title', 'Selects and tags - Forms')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/tagify/tagify.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/typeahead-js/typeahead.css')}}" />
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/tagify/tagify.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bootstrap-select/bootstrap-select.js')}}"></script>
<script src="{{asset('assets/vendor/libs/typeahead-js/typeahead.js')}}"></script>
<script src="{{asset('assets/vendor/libs/bloodhound/bloodhound.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/forms-selects.js')}}"></script>
<script src="{{asset('assets/js/app-settings.js')}}"></script>

@endsection

@section('content')


<div class="row">

    <!-- Select2 -->
    <div class="col-12">
        <div class="card mb-4">
            <h5 class="card-header">Setting for {{$series->name}} <a href="{{route('series.index')}}" class="btn btn-info btn-sm m-2">Dashboard</a></h5>
            <div class="card-body">
                <form id="event-settings">
                    <input type="hidden" name="series_id" value="{{$series->id}}">
                    <div class="col-8">

                        <div class="col-md-12 mb-4">
                          <label for="select2Multiple" class="form-label">Select Events for Series</label>
                          <select id="select2Multiple" name="events[]" class="form-select select2" multiple>
                            @foreach($events as $event)
                              <option value="{{ $event->id }}"
                                {{ in_array($event->id, $series->events->pluck('id')->toArray()) ? 'selected' : '' }}>
                                {{ $event->name }}
                              </option>
                            @endforeach
                          </select>
                        </div>



                   
                        <div class="col-md-12 mb-4">
                            <!-- Basic -->

                            <label for="select2Basic" class="form-label">Please select how many events count toward series ranking</label>
                            <select id="select2Basic" class="select2 form-select form-select-lg" data-allow-clear="true">
                                <option value="1" {{$series->best_num_of_scores == 1 ? 'selected':''}}>1</option>
                                <option value="2" {{$series->best_num_of_scores == 2 ? 'selected':''}}>2</option>
                                <option value="3" {{$series->best_num_of_scores == 3 ? 'selected':''}}>3</option>
                                <option value="4" {{$series->best_num_of_scores == 4 ? 'selected':''}}>4</option>
                                <option value="5" {{$series->best_num_of_scores == 5 ? 'selected':''}}>5</option>
                                <option value="6" {{$series->best_num_of_scores == 6 ? 'selected':''}}>6</option>

                            </select>

                        </div>


                    </div>


                    <div class="col-6 rank-placeholder">


                    </div>



                    <div>
                        <button type="button" class="btn btn-primary" id="submitSettingsButton">Submit Settings</button>
                    </div>

                </form>
                <div class="rank-block d-none">
                    <label for="defaultFormControlInput" class="form-label">Ranking List Category</label>
                    <input type="text" name="rankingList[]" class="form-control" id="defaultFormControlInput" placeholder="Ranking List name" aria-describedby="defaultFormControlHelp">
                    <span class="minusButton m-2 btn btn-danger btn-sm">Remove</span>
                </div>



            </div>
        </div>
        <!-- /Select2 -->


    </div>
    @include('_partials._modals.modal-add-edit-points')
    @endsection
