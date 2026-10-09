

<div class="modal fade photo-admin" id="uploadModal" tabindex="-1"  aria-labelledby="photo-upload-title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 id="photo-upload-title">Upload photos</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

            </div>
            <div class="modal-body">
                <div class="card-header">
                  
                    <p>Folder: {{$folder->name}}</p>
                </div>
                <div class="card-body">
                    <form class="p-3 p-md-3" action="{{ route('photo.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row mb-3">
                            <label for="selectImage" class="col-sm-3 col-form-label">Images</label>
                            <div class="col-sm-9">
                                <input type="file" class="form-control" multiple accept="image/*" required name="images[]" @error('image') is-invalid @enderror id="selectImage">
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
                                <button type="submit" class="btn btn-success btn-block">Upload to this folder</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>