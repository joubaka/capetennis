<!-- Modal -->
<div class="modal fade" id="folder-modal-edit" tabindex="-1" aria-labelledby="folder-modal-edit" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <form id="edit-folder-form" action="{{route('photoFolder.update',1)}}" method="POST">
            @method('PATCH')
                <input type="hidden" name="_token" value="{{ csrf_token() }}" />
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="defaultFormControlInput" class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" id="folder-name" placeholder="John Doe" aria-describedby="defaultFormControlHelp" />
                    </div>
                    <input type="hidden" name="event_id" value="{{$event->id}}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>

            </form>
        </div>


    </div>
</div>