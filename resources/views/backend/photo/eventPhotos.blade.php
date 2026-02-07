@extends('layouts/layoutMaster')

@section('title', 'Event Details')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
@endsection

<!-- Page -->
@section('page-style')

@endsection


@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/photos.js')}}"></script>
@endsection

@section('content')

<div class="container">
    <div class="card ">

        <div class="card m-2">
            <div>
                <a class="btn btn-success btn-sm m-2" href="javascript::void[0]" data-bs-toggle="modal" data-bs-target="#folder-modal-add">Add new Folder</a>
            </div>
           
            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th># of Photos</th>
                                <th>Name</th>
                                
                                <th>Event</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @foreach($event->photoFolders as $folder)
                            <tr>
                               

                                <td>
                                <a href="{{route('photoFolder.show',$folder->id)}}">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="{{asset('assets/img/avatars/folder.png')}}" alt="cbImg">
                                </a>
                            </td>
                            <td>{{count($folder->photos)}}</td>
                              <td>{{$folder->name}}</td>  
                               <td>
                                    {{$folder->event->name}}
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown"><i class="ti ti-dots-vertical"></i></button>
                                        <div class="dropdown-menu">
                                            <a class=" edit-folder-button dropdown-item" data-id="{{$folder}}" data-bs-target="#folder-modal-edit" data-bs-toggle="modal" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Edit</a>
                                           <form action="{{route('photoFolder.destroy',$folder->id)}}" method="post">
                                           @csrf 
                                           @Method('DELETE')
                                             <button type="submit" class="dropdown-item" data-id="{{$folder->id}}" ><i class="delete ti ti-trash me-1"></i>Delete</button>
                                           </form>
                                           
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>

@include('backend.photo._includes.folder-edit-modal')
@include('backend.photo._includes.folder-add-modal')
@include('backend.photo._includes.photo-preview-modal')
@endsection