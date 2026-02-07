<div class="card-header event-header">
    <h3 class="text-center"> {{$event->name}} </h3>
</div>
<div class="row">

    <div class="col-12 col-md-3">
        @include('backend.adminPage.admin_show.navbar.navbar')
       
    </div>
    <div class="col-12 col-md-9">
     
        <div class="card">
            <div class="row">

            
                <div class="col-12 col-md-12">
                    <div class="list-group m-2">
                        @foreach($event->draws as $draw)

                                @include('backend.draw._includes.draw_tab')


                        @endforeach
                    </div>

                </div>
            </div>


        </div>
    </div>

</div>
