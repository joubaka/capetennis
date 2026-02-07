

<div class="modal fade" id="uploadModal" tabindex="-1"  aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">

            </div>
            <div class="modal-body">
                <div class="card-header">
                  
                    <p>Folder: {{$folder->name}}</p>
                </div>
                <div class="card-body">
                    <form class="w-px-500 p-3 p-md-3" action="{{ route('photo.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Image</label>
                            <div class="col-sm-9">
                                <input type="file" class="form-control" multiple name="images[]" @error('image') is-invalid @enderror id="selectImage">
                            </div>
                            @error('image')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                            @enderror
                            <img id="preview" src="#" alt="your image" class="mt-3" style="display:none;" />
                        </div>
                        <input type="hidden" name="folder_id" id="folder_id" value="{{$folder->id}}">
                        <input type="hidden" name="event_id" id="event_id" value="{{$event->id}}">
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label"></label>
                            <div class="col-sm-9">
                                <button type="submit" class="btn btn-success btn-block">Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>