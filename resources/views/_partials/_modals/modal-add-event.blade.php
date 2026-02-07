<!-- Add New Address Modal -->
<div class="modal fade" id="addEvent" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-simple modal-add-new-address">
        <div class="modal-content p-3 p-md-5">
            <div class="modal-body">
                <!-- Full Editor -->
                <form action="{{route('events.store')}}" method="post">
                    @csrf
                    <div class="card">


                        <h5 class="card-header">Create Event</h5>


                        <div class="card-body">
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Event Name</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="name" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-date-input" class="col-md-2 col-form-label">Start Date</label>
                                <div class="col-md-10">
                                    <input class="form-control" type="date" name="start_date" value="2021-06-18" id="html5-date-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-date-input" class="col-md-2 col-form-label">End Date</label>
                                <div class="col-md-10">
                                    <input class="form-control" type="date" name="endDate" value="2021-06-18" id="html5-date-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="deadline" class="col-md-2 col-form-label">Deadline</label>
                                <div class="col-md-10">
                                    <input class="form-control" type="text" name="deadline" value="" id="deadline">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-date-input" class="col-md-2 col-form-label">Information</label>

                                <div id="full-editor">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-email-input" class="col-md-2 col-form-label">Organizer</label>
                                <div class="col-md-10">
                                    <input class="form-control" name="organizer"  value="" id="html5-email-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-email-input" class="col-md-2 col-form-label">Email</label>
                                <div class="col-md-10">
                                    <input class="form-control" name="email" type="email" value="john@example.com" id="html5-email-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Entry Fee</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="entry_fee" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Logo</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="logo" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Venues</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="venues" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="defaultSelect" class="form-label">Event Type</label>
                                <select id="defaultSelect" class="form-select" name="event_type">
                                    @foreach($eventTypes as $key => $value)
                                    <option value="{{$value->id}}">{{$value->name}}</option>
                                    @endforeach

                                </select>
                            </div>
                            <div class="mb-3 row">
                                <label for="html5-text-input" class="col-md-4 col-form-label">Deadline</label>
                                <div class="col-md-8">
                                    <input class="form-control" type="text" name="deadline" value="" id="html5-text-input">
                                </div>
                            </div>
                            <div class="mb-3 row ">

                                <label class="switch switch-success">
                                    <input type="checkbox" class="switch-input" id="status" name="published">
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on">
                                            <i class="ti ti-check"></i>
                                        </span>
                                        <span class="switch-off">
                                            <i class="ti ti-x"></i>
                                        </span>
                                    </span>
                                    <span class="switch-label">Published</span>
                                </label>

                                <label class="switch switch-success">
                                    <input type="checkbox" class="switch-input" id="signUp" name="signUP">
                                    <span class="switch-toggle-slider">
                                        <span class="switch-on">
                                            <i class="ti ti-check"></i>
                                        </span>
                                        <span class="switch-off">
                                            <i class="ti ti-x"></i>
                                        </span>
                                    </span>
                                    <span class="switch-label">Sign-up Open</span>
                                </label>
                            </div>
                            <div class="mb-3 row">

                                <label for="select2Multiple" class="form-label">Please select Admin for event</label>



                                <div class="position-relative" data-select2-id="128">
                                    <select name="admins" id="select2user" class=" select2user select2 form-select select2-hidden-accessible"  data-select2-id="select2Basic" tabindex="-1" aria-hidden="true">
                                 
                                    @foreach($users as $user)
                                        <option value="{{$user->id}}">{{$user->name}} {{$user->surname}}</option>
                                        @endforeach


                                    </select>

                                </div>

                            </div>
                        </div>

                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-4" id="createEventButton">Create Event</button>
                </form>
                <!-- /Full Editor -->
            </div>
        </div>
    </div>
</div>
<!--/ Add New Address Modal -->