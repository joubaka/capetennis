<!-- Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="addCategoryForm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="defaultFormControlInput" class="form-label">Category Name</label>
                    <input name="category" type="text" class="form-control"  />
                         </div>
                <input type="hidden" name="leagueRegion" id="leagueRegion">


            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="addCategoryButton btn btn-primary">Save changes</button>
            </div>
        </div>
    </form>
    </div>
</div>