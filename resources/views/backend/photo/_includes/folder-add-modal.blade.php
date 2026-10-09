<!-- Modal -->
<div class="modal fade photo-admin" id="folder-modal-add" tabindex="-1" aria-labelledby="folder-add-title" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <form action="{{route('photoFolder.store')}}" method="POST">
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <div class="modal-header">
                    <h5 class="modal-title" id="folder-add-title">Add photo folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-folder-name" class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="new-folder-name" placeholder="e.g. Finals" required />
                    </div>
                    <input type="hidden" name="event_id" value="{{$event->id}}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add folder</button>
                </div>

            </form>
        </div>


    </div>
</div>