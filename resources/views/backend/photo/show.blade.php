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
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="container photo-admin">


    <div class="card">
        <div class="card m-2">

            <div>
                <button class="btn btn-sm btn-primary" data-bs-target="#uploadModal" data-bs-toggle="modal">Upload Photos</button>
                <h3>{{ $event->name }} · Photos<a class="ms-3 btn btn-sm btn-secondary" href="{{route('eventPhoto.show',$event->id)}}">Back to all Folders</a></h3>
                <p class="badge bg-secondary">Folder: {{$folder->name}}</p>
</div>




            <div class="table-responsive">
                <table class="table" id="photo-list">
                    <thead>
                        <tr>
                            <td></td>
                            <th>Photo</th>
                            <th>Name</th>

                            <th>Folder</th>
                            <th>Action</th>

                        </tr>
                    </thead>
                    <tbody class="table-border-bottom-0">
                        @forelse($photos as $image)
                        <tr data-id="{{$image}}">
                            <td class="  dt-checkboxes-cell"><input type="checkbox" class="dt-checkboxes form-check-input" aria-label="Select {{ $image->name }}" value="{{$image->id}}"></td>

                            <td>
                                <a href="#photo-modal-preview" class="preview" data-image="{{$image}}" data-bs-target="#photo-modal-preview" data-bs-toggle="modal">

                                    <img class=" img-thumbnail" style="max-width: 100px;" src="{{ asset('storage/photoFolder/'.$image->path) }}" alt="Preview {{ $image->name }}">
                                </a>


                            </td>
                            <td>{{$image->name}}</td>
                            <td>
                                {{$image->folder->name}}
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown" aria-label="Photo actions"><i class="ti ti-dots-vertical"></i></button>
                                    <div class="dropdown-menu">
                                        <!-- <a class="dropdown-item editPicture" href="javascript:void(0);"><i class="ti ti-pencil me-1"></i>Edit</a> -->
                                        <form action="{{route('photo.destroy',$image->id)}}" method="post" onsubmit="return confirm('Delete this photo? This action cannot be undone.')">
                                            @csrf
                                            @Method('DELETE')
                                            <button type="submit" class="dropdown-item" data-id="{{$image->id}}"><i class="delete ti ti-trash me-1"></i>Delete</button>
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

            <div class="border-top p-3"><p id="photo-selection-count" class="small text-muted" role="status" aria-live="polite">0 photos selected</p><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" id="moveSelected" data-bs-target="#move-selected-modal" data-bs-toggle="modal" disabled>Move selected photos</button><button type="button" class="btn btn-outline-danger" id="deleteSelected" disabled>Delete selected photos</button></div></div>
        </div>


    </div>
</div>







@include('backend.photo._includes.upload-modal')
@include('backend.photo._includes.photo-preview-modal')
@include('backend.photo._includes.photo-move-modal')


<style>.photo-admin :is(.btn,.dropdown-item,input:not([type=checkbox]),select) { min-height:44px !important; } .photo-admin .dropdown-toggle { min-width:44px; } .photo-admin td { white-space:normal; overflow-wrap:anywhere; } .photo-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; } .photo-admin [type=checkbox] { min-width:24px; min-height:24px; }</style>
@endsection