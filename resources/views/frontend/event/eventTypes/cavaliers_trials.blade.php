@php
  $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
@endphp
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
                @include('frontend.event.partials.event-information')
                @include('frontend.event.partials.event-announcements')

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
      @php
  $formatWithdrawalDeadline = optional($event->withdrawal_deadline)->format('d M Y');
@endphp

        @include('frontend.event.partials.event-about')
        @include('frontend.event.partials.event-documents', ['documentNewWindow' => false])

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
        <div id="event-draws-match-times" class="card mb-4" tabindex="-1" data-event-draw-section aria-label="Published draws">
            <div class="card-header"> <small class="card-text text-uppercase">Draws</small></div>
            <div class="card-body">
           
                @foreach($eventDraws as $draw)
                <div class="col m-2">
                    @if($draw->published == 1)
                    <a href="{{route('frontend.showDraw',$draw->id)}}" class="btn btn-sm btn-success">
                      {{$draw->drawName}}
                      <span style="white-space: normal; line-height: 1.4;" class="badge {{ $publishedDayLabels->has($draw->id) ? 'bg-label-light' : 'bg-label-secondary' }} ms-1">
                        {{ $publishedDayLabels->has($draw->id) ? 'Times available · '.$publishedDayLabels->get($draw->id) : 'Times to follow' }}
                      </span>
                    </a>
                    @can('view', $draw)
                    <a href="{{route('frontend.bracket.fixtures',$draw->id)}}" class="btn btn-sm btn-primary">Fixtures</a>
                    @endcan
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

                    @php
                      $paidRegs = $eventcategories->categoryEventRegistrations->where('payment_status_id', 1)->where('status', '!=', 'withdrawn');
                    @endphp
                    <h3 class="badge bg-label-primary">{{$eventcategories->category->name}} ({{$paidRegs->count()}}) </h3>
                    <div class="list-group list-group-flush">

                        <div class="demo-inline-spacing ">
                            <div class="list-group list-group-flush">

                                @foreach($paidRegs as $cereg)
                                @php $registration = $cereg->registration; @endphp
                                @if($registration && $registration->players->count())

                                <a href="javascript:void(0);" class="list-group-item list-group-item-action"> {{$registration->players[0]->name}} {{$registration->players[0]->surname}}


                                </a>
                                @endif

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
