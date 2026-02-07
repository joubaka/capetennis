@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')

@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')

@endsection

@section('page-script')

@endsection

@section('content')

<div class="container">
    <div class="card ">

        <div class="card m-2">
           
           
            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th># of Photos</th>
                                <th>Name</th>
                                
                                <th>Event</th>
                                

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @foreach($folders as $folder)
                            <tr>
                               

                                <td>
                                <a href="{{route('frontend.event.show.folder',$folder->id)}}">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="{{asset('assets/img/avatars/folder.png')}}" alt="cbImg">
                                </a>
                            </td>
                            <td>{{count($folder->photos)}}</td>
                              <td>{{$folder->name}}</td>  
                               <td>
                                    {{$folder->event->name}}
                                </td>
                               
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>


@endsection