<!-- Modal -->
<div class="modal fade photo-admin" id="folder-modal-edit" tabindex="-1" aria-labelledby="folder-edit-title" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <form id="edit-folder-form" action="{{route('photoFolder.update',1)}}" method="POST">
            @method('PATCH')
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <div class="modal-header">
                    <h5 class="modal-title" id="folder-edit-title">Rename photo folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folder-name" class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="folder-name" required />
                    </div>
                    <input type="hidden" name="event_id" value="{{$event->id}}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save folder name</button>
                </div>

            </form>
        </div>


    </div>
</div>