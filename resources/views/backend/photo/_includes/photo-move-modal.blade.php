<!-- Modal -->
<div class="modal fade" id="move-selected-modal" tabindex="-1" aria-labelledby="move-selected-modal" aria-hidden="true">
    <div class="modal-dialog" role="document">

        <div class="modal-content">
            <div class="modal-header">
                Move Photos to
            </div>
            <div class="modal-body">


                <div>

                    <select id="folder" name="folder"  class="form-select form-select-sm">
                        <option>Please select Folder</option>
                        @foreach($event->photoFolders as $folder)
                        <option value="{{$folder->id}}">{{$folder->name}}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="photos[]" id="photos">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="submit-move-button" class="btn btn-primary">Move</button>

                </div>



            </div>


        </div>
    </div>