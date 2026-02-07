@foreach($event->regions as $region)



@foreach($region->teams as $team)

<div class="container-full">
    <!-- Content Header (Page header) -->

    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="mr-auto">


            </div>
        </div>
    </div>

    <!-- Main content -->
    <section class="content">

        <div class="row">



            <div class="col-12">

                <div class="box">
                    <div class="box-header with-border">
                        @if (session('success'))
                        <div class="col-sm-12">
                            <div class="alert  alert-success alert-dismissible fade show" role="alert">
                                {{ session('success') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>

                            </div>

                        </div>
                        @endif
                        <a href="#" class="btn btn-primary">Change team Players</a>

                        <br>

                        <h3 class="box-title">{{$team->name}} {{$team->id}}</h3>

                        <br><br>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all Players in event</a>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all unpaid Players in event</a>
                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to Players in {{$team->name}}</a>


                        <a class="btn btn-rounded mb-5 btn-warning" style="float: left;" href="#">Email to all Players in {{$team->regions['region_name']}}</a>

                    </div>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th width='5%'>Nr</th>
                                        <th width='15%'>Name and Surname</th>
                                        <th width='15%'>email</th>
                                        <th width='15%'>Cell nr</th>
                                        <th width='15%'>Pay Status</th>
                                        <td></td>
                                    </tr>
                                </thead>
                                <tbody>

                                    @foreach($team->players as $key => $playerDetails)

                                    <tr>
                                        <td>{{$key+1}}</td>
                                        <td>{{$playerDetails->name}} {{$playerDetails->surname}}</td>
                                        <td>{{$playerDetails->email}}</td>
                                        <td>{{$playerDetails->cellNr}}</td>
                                        <td style="{{$playerDetails->pivot->pay_status == 1 ? 'background-color:green':'' }}">
                                            {{$playerDetails->pivot->pay_status == 1 ? 'Paid':'Not-paid' }}
                                            <span data-id="{{$playerDetails->id}}" data-teamId="{{$team->id}}" class="ml-1 btn btn-secondary btn-sm  markPaid">Change Pay Status </span>

                                        </td>
                                        <td><a class="btn btn-warning" href="#">Email Player</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>

                            </table>
                        </div>
                    </div>
                    <!-- /.box-body -->
                </div>
                <!-- /.box -->


            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->

    </section>
    <!-- /.content -->

</div>
@endforeach
@endforeach