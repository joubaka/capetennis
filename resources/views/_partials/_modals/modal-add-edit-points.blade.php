<!-- Add New Address Modal -->
<div class="modal fade" id="add-edit-points" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <form id="modalForm">
                    <div class="col-12">
                        <h5 class="card-header">Please assign points per position</h5>
                        <div class="col-md-12 mb-4">
                            <div class="row">
                                @foreach($series->points($series->id) as $key => $position)
                                <div class="col-6">
                                    <div class="row mb-3">
                                        <label class="col-sm-4 col-form-label" for="basic-default-name">Position {{$key+1}}</label>
                                        <div class="col-sm-8">
                                            <input type="text" value="{{$position->score}}" class="form-control" name="position[]" id="basic-default-name" placeholder="Points">
                                        </div>

                                    </div>
                                </div>
                                @endforeach
                               
                            </div>
                        </div>
                    </div>
                    <div class="btn btn-primary btn-sm mt-4" id="submit-points-table-button"  data-dismiss="modal">Submit</div>
                    <input type="hidden" id="event_id" value="{{$event->id}}">
                    <!-- /Full Editor -->

                </form>
            </div>
        </div>
    </div>
</div>
<!--/ Add New Address Modal -->