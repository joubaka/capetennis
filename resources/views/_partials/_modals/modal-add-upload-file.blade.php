<!-- Add New Address Modal -->
<div class="modal fade" id="addFileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-add-file">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <div class="col-12">


                    <h1>Upload PDF</h1>

                    <form name="upoadFileForm" method="POST" enctype="multipart/form-data" action="{{route('file.store')}}">
                        {{ csrf_field()}}
                        <input type="file" name="myFile"><br><br>
                        <input type="hidden" value="{{$event->id}}" name="event_id">
                        <input type="submit" name="submit" value="upload">

                    </form>

                    @if(Session()->has('msg'))
                    <h2>{{Session()->get('msg')}}</h2>
                    @endif
                    <h2 class="mt-3">Files added to event</h2>
                    @foreach($event->files as $key=> $file)
                    <h3 class="mt-3">{{($key+1).'. '.$file->name}} </h3>
                    @endforeach

                    <!-- /Full Editor -->
                </div>
            </div>
        </div>
    </div>
    <!--/ Add New Address Modal -->
