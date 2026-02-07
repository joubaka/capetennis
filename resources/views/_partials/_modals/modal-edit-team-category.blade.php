<!-- Modal -->
<div class="modal fade" id="edit-team-category-modal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="edit-team-category-title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="col-sm">
                    @foreach($event->eventCategories as $eventCategory)
                   
                    <div class="form-check mt-3">
                        <input name="category" class="form-check-input" type="radio" value="{{$eventCategory->id}}" id="category-radio" />
                        <label class="form-check-label" for="defaultRadio1">
                           {{$eventCategory->category->name}}
                        </label>
                    </div>
                    @endforeach
                   
                    <input type="hidden" name="team" value="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="change-team-category-button" class="btn btn-primary" data-bs-dismiss="modal">Save changes</button>
            </div>
        </div>
    </div>
</div>