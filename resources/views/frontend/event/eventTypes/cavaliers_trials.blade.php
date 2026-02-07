<?php

use App\Models\EventNomination;
use App\Helpers\Fixtures;

$nominations = EventNomination::all();
?>
<div class="row">

    <div class="col-xl-8 col-lg-7 col-md-7 ">
        <!-- Activity Timeline -->

        <!--/ Activity Timeline -->
        <div class="row">
            <!-- Connections -->
            <div class="col-lg-12 col-xl-12 ">
                <div class="card p-4">
                    <h5 class="pb-4  mb-4">Announcements</h5>

                    @foreach($event->announcements as $a)
                    <div class="card shadow-none bg-transparent border border-primary m-4">
                        <div class="card-body">
                            <h5 class="card-title"></h5>
                            <p class="card-text">
                                {!!$a->message!!}
                            </p>
                            <p class="card-text"><small class="text-muted"><mark>Announcement @ {{$a->created_at}}</mark></small></p>
                        </div>
                    </div>






                    @endforeach

                </div>

                <div class="card p-4 mt-4 ">
                    <h5 class="pb-1 mb-4">Information</h5>
                    {!!$event->information!!}
                </div>

                <div class="card p-4 mt-4 ">
                    <h5 class="pb-1 mb-4">Nominations</h5>
                    <div class="row">
                        @foreach($event->eventCategories as $eventCategory)
                        <div class="col-md-6 col-sm-12">
                            <div class="card shadow-none bg-transparent border border-primary m-1 p-2">
                                <div class="card-header">
                                    <h3>{{$eventCategory->category->name}}
                                        @if($eventCategory->nominations_published == 1)
                                        ({{$eventCategory->nominations->count()}})
                                        @endif
                                    </h3>
                                </div>
                                <ul class="list-group list-group-flush">
                                    @if($eventCategory->nominations_published == 1)
                                    @foreach($eventCategory->nominations as $nomination)

                                    <li class="list-group-item">{{$nomination->player->getFullNameAttribute()}}


                                    @if(isset($nomRegisteredLookup[$nomination->player->id]))

                                        <span class=""><i class="text-success fa-solid fa-circle-check"></i></span>
                                      @else
                                                      @endif

                                    </li>
                                    @endforeach
                                    @else
                                    <p class="badge bg-label-warning">Not Published</p>
                                    @endif
                                </ul>

                            </div>


                        </div>
                        @endforeach

                    </div>

                </div>



            </div>
            <!--/ Connections -->
            <!-- Teams -->
            <div class="col-lg-12 col-xl-6">

            </div>
            <!--/ Teams -->
        </div>

    </div>
    <div class="col-xl-4 col-lg-5 col-md-5 ">
        @php
        $wallet = 0;
        @endphp
        @if($wallet == 1)
        @auth
        @if($userRegistrations->count() > 0 )


        <div class="card shadow-none border border-success mb-3">
            <div class="card-header bg-label-success ">You have players entered in this event!</div>
            <div class="card-body mt-5">

                @foreach($userRegistrations as $registration)
                <p>{{$registration->registration->players[0]->name}} {{$registration->registration->players[0]->surname}} - {{$registration->categoryEvent->category->name}}
                    @if( $signUp == 'open' && $event->eventType == 6)
                    <span class="btn btn-label-danger border border-danger btn-sm withDrawPlayer " data-id="{{$registration->id}}">

                        Withdraw player

                    </span>
                    @endif

                </p>
                @endforeach
            </div>
        </div>


        @endif
        @else

        @endAuth
        @endif
        <!-- About User -->
        <div class="card mb-4">
            <div class="card-body">
                <small class="card-text text-uppercase">About</small>
                <ul class="list-unstyled mb-4 mt-3">
                    <li class="d-flex align-items-center mb-3"><i class="fa-regular fa-calendar"></i><span class="fw-bold mx-2">Start Date:</span> <span class="badge bg-label-success">{{$sDate}}</span></li>
                    <li class="d-flex align-items-center mb-3"><i class="fa-regular fa-calendar"></i><span class="fw-bold mx-2">End Date:</span> <span class="badge bg-label-success">{{$eDate}}</span></li>
                    <li class="d-flex align-items-center mb-3"><i class="ti ti-check"></i><span class="fw-bold mx-2">Entry deadline :</span> <span class="badge bg-label-warning">{{$formatDLine}}</span></li>
                    <li class="d-flex align-items-center mb-3"><i class="ti ti-x"></i><span class="fw-bold mx-2">Withdrawal deadline :</span> <span class="badge bg-label-danger">{{$formatDLine}}</span></li>




                    @if($event->entry_fee2 == null)
                    <li class="d-flex align-items-center mb-3">
                        <i class="ti ti-flag"></i><span class="fw-bold mx-2">Entry Fee:</span> <span>R{{$event->entryFee}}</span>
                    </li>
                    @else


                    @if(isset($event->eventCategories))
                    @foreach($event->eventCategories as $ce)
                    <li class="d-flex align-items-center mb-3">
                        <i class="ti ti-flag"></i><span class="fw-bold mx-2">{{$ce->category->name}}</span> <span>R{{$ce->entry_fee}}</span>
                    </li>

                    @endforeach
                    @endif

                    @endif




                </ul>
                <small class="card-text text-uppercase">Contact</small>
                <ul class="list-unstyled mb-4 mt-3">
                    <li class="d-flex align-items-center mb-3"><i class="ti ti-phone-call"></i><span class="fw-bold mx-2">Organizer:</span> <span>{{$event->organizer}}</span></li>

                    <li class="d-flex align-items-center mb-3"><i class="ti ti-mail"></i><span class="fw-bold mx-2">Email:</span> <a href="mailto:{{$event->email}}">{{$event->email}}</a></li>
                </ul>

            </div>
        </div>
        <!--/ About User -->
        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Documents</small>
                @guest

                @else

                @if(Auth::user()->is_admin($event->id)->count() > 0 || Auth::user()->id == 584 )
                <div class="btn btn-success btn-sm float-right" data-bs-target='#addFileModal' data-bs-toggle='modal'>Upload .PDF</div>
                @endif
                @endguest



            </div>

            <div class="card-body">

                <div class="demo-inline-spacing mt-3">
                    <div class="list-group">

                        @foreach($event->files as $key=> $file)



                        <div class="file">
                            <div class="row">

                                <div class="col-7">
                                    <a href="{{route('file.show',$file->id)}}" class="list-group-item list-group-item-action d-flex justify-content-between">
                                        <div class="li-wrapper d-flex justify-content-start align-items-center">
                                            <div class="avatar avatar-sm me-3">
                                                <span class="avatar-initial rounded-circle bg-label-success">{{($key+1)}}</span>
                                            </div>
                                            <div class="list-content">
                                                <h6 class="mb-1">{{$file->name}}</h6>

                                            </div>
                                        </div>


                                    </a>
                                </div>
                                <div class="col-4">
                                    <small>
                                        @can('admin')
                                        @if(Auth::user()->id == $event->admin || Auth::user()->id == 584)
                                        <div data-id="{{$file->id}}" class="btn btn-danger btn-sm deleteFileButton ml-4">Delete</div>
                                        @endif
                                        @endcan
                                    </small>
                                </div>
                            </div>


                        </div>
                        @endforeach





                    </div>
                </div>
            </div>
        </div>

        <!--        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Photos</small>


            </div>
            <div class="card-body">

                @if(isset($event->sell->id))
                @if($event->sell->type == 'Photos')

                <a class="btn btn-secondary btn-sm" href="{{route('frontend.event.photos',$event->id)}}">Photos</a>

                @endif
                @endif

            </div>
        </div> -->

        @if($event->results_published == 1)

        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Results</small></div>
            <div class="card-body">

                <a href="{{route('result.show',$event->id)}}" class="btn bg-label-success btn-sm">Results here</a>

            </div>
        </div>
        @endif
        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Draws</small></div>
            <div class="card-body">
           
                @foreach($eventDraws as $draw)
                <div class="col m-2">
                    @if($draw->published == 1)
                    <a href="{{route('frontend.showDraw',$draw->id)}}" class="btn btn-sm btn-success">{{$draw->drawName}}</a>
                    @if(Auth::check())
                    @if(Auth::user()->id == 1764 || Auth::user()->id == 584 )
                    <a href="{{route('frontend.bracket.fixtures',$draw->id)}}" class="btn btn-sm btn-primary">Fixtures</a>
                    @endif
                    @endif
                    @else
                    <div class="badge bg-danger">{{$draw->drawName}} - Not published</div>

                    @endif
                </div>
                @endforeach


            </div>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <small class="card-text text-uppercase">Players</small><br>

                @foreach($event->eventCategories as $eventcategories)
                <div class="card p-2 mb-2">

                    <h3 class="badge bg-label-primary">{{$eventcategories->category->name}} ({{count($eventcategories->registrations)}}) </h3>
                    <div class="list-group list-group-flush">

                        <div class="demo-inline-spacing ">
                            <div class="list-group list-group-flush">

                                @foreach($eventcategories->registrations as $registration)

                                <a href="javascript:void(0);" class="list-group-item list-group-item-action"> {{$registration->players[0]->name}} {{$registration->players[0]->surname}}


                                </a>


                                @endforeach
                            </div>
                        </div>


                    </div>





                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
