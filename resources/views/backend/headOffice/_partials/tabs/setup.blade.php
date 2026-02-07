<div class="card mb-6">
    <div class="card-body">

        <h5 class="card-title mb-4">Categories</h5>
        <div class="card shadow-none mb-6 border-0">
            <div class="table-responsive table-border-bottom-0">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-truncate">Regions</th>
                            <th class="text-truncate">Teams</th>
                            <th class="text-truncate">Categories</th>
                            <th class="text-truncate">Recent Activities</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($event->regions as $region)
                        <tr>
                            <td class="text-truncate"><i class="ti ti-brand-windows ti-md text-info me-4"></i> <span class="text-heading">{{$region->region_name}}</span></td>
                            <td class="text-truncate">
                                @foreach($region->teams as $team)
                                <p class="badge bg-label-info">{{$team->name}}</p><br>

                                @endforeach
                            </td>
                            <td class="text-truncate">Switzerland</td>
                            <td class="text-truncate">10, July 2021 20:07</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <h5 class="card-title mb-4">Categories</h5>
        <div class="card shadow-none mb-6 border-0">
            <div class="table-responsive border border-top-0 rounded">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-nowrap w-50">Type</th>
                            <th class="text-nowrap text-center w-25">Email</th>
                            <th class="text-nowrap text-center w-25">App</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-nowrap text-heading">Order purchase</td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_1" checked="">
                                </div>
                            </td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_2" checked="">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-nowrap text-heading">Order cancelled</td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_4" checked="">
                                </div>
                            </td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_5">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-nowrap text-heading">Order refund request</td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_7">
                                </div>
                            </td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_8" checked="">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-nowrap text-heading">Order confirmation</td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_9" checked="">
                                </div>
                            </td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_10">
                                </div>
                            </td>
                        </tr>
                        <tr class="border-transparent">
                            <td class="text-nowrap text-heading">Payment error</td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_11" checked="">
                                </div>
                            </td>
                            <td>
                                <div class="form-check d-flex justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="defaultCheck_order_12">
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>


    </div>
</div>