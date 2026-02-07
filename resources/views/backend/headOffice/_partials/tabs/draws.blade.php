<div class="row">



    <div class="col-12 col-sm-9 col-md-9">
        <ul class="nav nav-pills mb-2">
            <li class="nav-item me-2">
                <button id="create-draw-button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#drawModal">Create Draw</button>
            </li>

        </ul>
        <div class="card">
            <div class="row">


                <div class="col-12 col-md-12">
                    <div class="list-group m-2">
                        @foreach($event->draws as $draw)

                        @include('backend.draw._includes.draw_tab_team')

                        @endforeach
                    </div>

                </div>
            </div>


        </div>
    </div>

</div>
<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Create Draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="create-draw-form">


                <div class="modal-body">
                    <div class="col-md-12 col-12 mb-md-0 mb-4">
                        <h5>Regions</h5>
                        <p>example: 1-3;2-4</p>
                        <ul class="list-group list-group-flush" id="pending-tasks">
                            @foreach($event->region_in_events as $region)
                            <li data-id="{{$region->pivot->id}}" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                                <span>{{$region->region_name}}</span>

                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <input type="hidden" name="event_id" value="{{$event->id}}">
                    <div class="mt-4">
                        <h5>Draw Format type</h5>
                        <select name="drawType" id="smallSelect" class="form-select form-select-sm">
                            <option>Select Format</option>
                            @foreach($drawTypes as $drawType)
                            <option value="{{$drawType->id}}">{{$drawType->drawTypeName}}</option>

                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md">
                            <small class="text-light fw-medium d-block">Checkboxes Colors</small>
                            @foreach($event->eventCategories as $eventCategory)
                            <div class="form-check form-check-primary mt-3">
                                <input class="form-check-input" name="category[]" type="checkbox" value="{{$eventCategory->id}}" />
                                <label class="form-check-label" for="customCheckPrimary">{{$eventCategory->category->name}}</label>
                            </div>
                            @endforeach
                        </div>


                    </div>
                    <div class="pt-4">
                        <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light">Create Fixtures</button>
                        <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                    </div>
                </div>
            </form>








        </div>

    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="drawModal" tabindex="-1" aria-labelledby="drawModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Create Draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <form id="create-draw-form">


                <div class="modal-body">
                    <div class="col-md-12 col-12 mb-md-0 mb-4">
                        <h5>Regions</h5>
                        <p>example: 1-3;2-4</p>
                        <ul class="list-group list-group-flush" id="pending-tasks">
                            @foreach($event->region_in_events as $region)
                            <li data-id="{{$region->pivot->id}}" class="list-group-item drag-item cursor-move d-flex justify-content-between align-items-center">
                                <span>{{$region->region_name}}</span>

                            </li>
                            @endforeach
                        </ul>
                    </div>
                    <input type="hidden" name="event_id" value="{{$event->id}}">
                    <div class="mt-4">
                        <h5>Draw Format type</h5>
                        <select name="drawType" id="smallSelect" class="form-select form-select-sm">
                            <option>Select Format</option>
                            @foreach($drawTypes as $drawType)
                            <option value="{{$drawType->id}}">{{$drawType->drawTypeName}}</option>

                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md">
                            <small class="text-light fw-medium d-block">Checkboxes Colors</small>
                            @foreach($event->eventCategories as $eventCategory)
                            <div class="form-check form-check-primary mt-3">
                                <input class="form-check-input" name="category[]" type="checkbox" value="{{$eventCategory->id}}" />
                                <label class="form-check-label" for="customCheckPrimary">{{$eventCategory->category->name}}</label>
                            </div>
                            @endforeach
                        </div>


                    </div>
                    <div class="pt-4">
                        <button type="button" id="create-fixtures-button" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light">Create Fixtures</button>
                        <button type="reset" class="btn btn-label-secondary waves-effect">Cancel</button>
                    </div>
                </div>
            </form>








        </div>

    </div>
</div>

<script>
    var venues = {!! $venues->toJson() !!};
</script>
