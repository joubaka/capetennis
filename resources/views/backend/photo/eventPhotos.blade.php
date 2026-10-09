@extends('layouts.backend')

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

<div class="container photo-admin">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3"><div><h4 class="mb-1">{{ $event->name }} · Photo folders</h4><p class="text-muted mb-0">Open a folder to upload photos or manage its contents.</p></div></div>
    <div class="card ">

        <div class="card m-2">
            <div>
                <a class="btn btn-success btn-sm m-2" href="#folder-modal-add" data-bs-toggle="modal" data-bs-target="#folder-modal-add">Add folder</a>
            </div>

            <div class="table-responsive">
                    <table class="table" id="photo-list">
                        <thead>
                            <tr>
                                <th>Folder</th>
                                <th>Photos</th>
                                <th>Name</th>

                                <th>Event</th>
                                <th>Action</th>

                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @forelse($event->photoFolders as $folder)
                            <tr>


                                <td>
                                <a href="{{route('photoFolder.show',$folder->id)}}">
                                <img class=" img-thumbnail" style="max-width: 100px;" src="{{asset('assets/img/avatars/folder.png')}}" alt="Open {{ $folder->name }}">
                                </a>
                            </td>
                            <td>{{count($folder->photos)}}</td>
                              <td><a href="{{ route('photoFolder.show', $folder->id) }}">{{ $folder->name }}</a></td>
                               <td>
                                    {{$folder->event->name}}
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="Actions for {{ $folder->name }}"><i class="ti ti-dots-vertical"></i></button>
                                        <div class="dropdown-menu">
                                            <a class=" edit-folder-button dropdown-item" data-id="{{$folder}}" data-bs-target="#folder-modal-edit" data-bs-toggle="modal" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Rename folder</a>
                                           <form action="{{route('photoFolder.destroy',$folder->id)}}" method="post" onsubmit="return confirm('Delete this folder record? This action cannot be undone.')">
                                           @csrf
                                           @Method('DELETE')
                                             <button type="submit" class="dropdown-item" data-id="{{$folder->id}}" ><i class="delete ti ti-trash me-1"></i>Delete folder</button>
                                           </form>

                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            @endforelse
                        </tbody>
                    </table>
                </div>

        </div>

    </div>

</div>

@include('backend.photo._includes.folder-edit-modal')
@include('backend.photo._includes.folder-add-modal')

<style>.photo-admin :is(.btn,.dropdown-item,input,select) { min-height:44px !important; } .photo-admin .dropdown-toggle { min-width:44px; } .photo-admin td { white-space:normal; overflow-wrap:anywhere; } .photo-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }</style>
@endsection