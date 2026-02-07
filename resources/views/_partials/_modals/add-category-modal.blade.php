<!-- Modal -->
<div class="modal fade" id="add-category-modal" tabindex="-1" aria-labelledby="add-category-modal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <form class="formPlayer" method="post">
                    @csrf
                    <div class="card">


                        <h5 class="card-header">Add/remove Categories - {{$event->name}} </h5>


                        <div class="card-body">
                            <div class="row row-bordered g-0">
                                <div class="col-md p-6">


                                    <select id="categories" class="categories" multiple="multiple" style="width: 50%;">
                                        @foreach($categories as $category)
                                        <option value="{{$category->id}}">{{$category->name}}</option>

                                        @endforeach
                                    </select>
                                    
                                </div>

                            </div>




                        </div>

                    </div>
                    <div type="button" class="btn btn-primary btn-sm mt-4" id="save-category-button">Save Categories</div>
                </form>
                <!-- /Full Editor -->



                <input type="hidden" id="event_id" value="{{$event->id}}">

                <!-- /Full Editor -->
            </div>
        </div>
    </div>
</div>
<script>
  var eventCategories = @json($event->eventCategories);  
  
</script>
