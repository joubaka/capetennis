/**
 * App User View - Account (jquery)
 */

$(function () {
  'use strict';

  // Variable declaration for table
  var dt_project_table = $('.datatable-events'),
    dt_series = $('.datatable-series'),
    dt_invoice_table = $('.datatable-invoice');
  var user = $('#user').val();
  console.log(user);
  // Project datatable
  // --------------------------------------------------------------------
  if (dt_project_table.length) {

    var dt_project = dt_project_table.DataTable({
      "ordering": false,
      paging: false,
      ajax: APP_URL + '/events/ajax/userEvents/' + user, // JSON file to add data
      columns: [
        // columns according to JSON

        { data: 'name' },
        { data: 'start_date' },
        { data: 'entryFee' },
        { data: null },
        { data: null },
      ],

      columnDefs: [

        {
          // User full name and email
          targets: 0,
          responsivePriority: 1,


          render: function (data, type, full, meta) {
            var upcoming = '';
            var d = new Date();


            if (new Date(full['start_date']) > d) {
              upcoming = 'upcoming';
            } else {

            }
            var $row_output = full['name']

            var d = "<div class='btn btn-primary  text-white'><a href='" + APP_URL + "/events/" + full.id + "' class='text-white'>" + $row_output + "</a></div>";
            d += '<span class="badge rounded-pill bg-label-success ">' + upcoming + '</span>';

            return d;
          }
        },
        { // Label
          targets: 1,
          responsivePriority: 3,

        },

        {
          // Label
          targets: 2,
          responsivePriority: 3,
          render: function (data, type, full, meta) {
            return 'R' + full['entryFee']

          }
        },
        {
          targets: 3,

          render: function (data, type, full, meta) {



            return full.registrations.length;

          }
        },
        {
          targets: 4,

          render: function (data, type, full, meta) {



            return "<a class='btn btn-sm btn-secondary' href='" + APP_URL + "/backend/eventAdmin/" + full.id + "'>Admin Page</a>";

          }
        }
      ],

    });
  }


  if (dt_series.length) {

    var dt_series = dt_series.DataTable({
      "ordering": false,
      paging: false,
      ajax: APP_URL + '/events/ajax/series', // JSON file to add data
      columns: [
        // columns according to JSON

        { data: null },
        { data: null },
        { data: null },
        { data: null }

      ], columnDefs: [

        {
          // User full name and email
          targets: 0,
          responsivePriority: 1,


          render: function (data, type, full, meta) {
           return full.id
          }
        },
        {
          // User full name and email
          targets: 1,
          responsivePriority: 1,


          render: function (data, type, full, meta) {
            console.log(full)
           return full.name;
          }
        },
        
        {
          // User full name and email
          targets: 2,
          responsivePriority: 1,


          render: function (data, type, full, meta) {
           var url = APP_URL+'/backend/ranking/settings/'+full.id;
            return '<a href="'+url+'" class="btn btn-sm btn-warning">Settings</a>';
          }
        },
        
        {
          // User full name and email
          targets: 3,
          responsivePriority: 1,


          render: function (data, type, full, meta) {
            console.log(full)
           return '<a href="#" class="btn btn-sm btn-secondary">Show</a>';
          }
        },
      ],



    });
  }
  // Invoice datatable
  // --------------------------------------------------------------------
  if (dt_invoice_table.length) {
    var dt_invoice = dt_invoice_table.DataTable({
      ajax: assetsPath + 'json/invoice-list.json', // JSON file to add data
      columns: [
        // columns according to JSON
        { data: '' },
        { data: 'invoice_id' },
        { data: 'invoice_status' },
        { data: 'total' },
        { data: 'issued_date' },
        { data: 'action' }
      ],
      columnDefs: [
        {
          // For Responsive
          className: 'control',
          responsivePriority: 2,
          targets: 0,
          render: function (data, type, full, meta) {
            return '';
          }
        },
        {
          // Invoice ID
          targets: 1,
          render: function (data, type, full, meta) {
            var $invoice_id = full['invoice_id'];
            // Creates full output for row
            var $row_output = '<a href="' + baseUrl + 'app/invoice/preview"><span>#' + $invoice_id + '</span></a>';
            return $row_output;
          }
        },
        {
          // Invoice status
          targets: 2,
          render: function (data, type, full, meta) {
            var $invoice_status = full['invoice_status'],
              $due_date = full['due_date'],
              $balance = full['balance'];
            var roleBadgeObj = {
              Sent: '<span class="badge badge-center rounded-pill bg-label-secondary w-px-30 h-px-30"><i class="ti ti-circle-check ti-sm"></i></span>',
              Draft:
                '<span class="badge badge-center rounded-pill bg-label-primary w-px-30 h-px-30"><i class="ti ti-device-floppy ti-sm"></i></span>',
              'Past Due':
                '<span class="badge badge-center rounded-pill bg-label-danger w-px-30 h-px-30"><i class="ti ti-info-circle ti-sm"></i></span>',
              'Partial Payment':
                '<span class="badge badge-center rounded-pill bg-label-success w-px-30 h-px-30"><i class="ti ti-circle-half-2 ti-sm"></i></span>',
              Paid: '<span class="badge badge-center rounded-pill bg-label-warning w-px-30 h-px-30"><i class="ti ti-chart-pie ti-sm"></i></span>',
              Downloaded:
                '<span class="badge badge-center rounded-pill bg-label-info w-px-30 h-px-30"><i class="ti ti-arrow-down-circle ti-sm"></i></span>'
            };
            return (
              "<span data-bs-toggle='tooltip' data-bs-html='true' title='<span>" +
              $invoice_status +
              '<br> <strong>Balance:</strong> ' +
              $balance +
              '<br> <strong>Due Date:</strong> ' +
              $due_date +
              "</span>'>" +
              roleBadgeObj[$invoice_status] +
              '</span>'
            );
          }
        },
        {
          // Total Invoice Amount
          targets: 3,
          render: function (data, type, full, meta) {
            var $total = full['total'];
            return '$' + $total;
          }
        },
        {
          // Actions
          targets: -1,
          title: 'Actions',
          orderable: false,
          render: function (data, type, full, meta) {
            return (
              '<div class="d-flex align-items-center">' +
              '<a href="javascript:;" class="text-body" data-bs-toggle="tooltip" title="Send Mail"><i class="ti ti-mail me-2 ti-sm"></i></a>' +
              '<a href="' +
              baseUrl +
              'app/invoice/preview" class="text-body" data-bs-toggle="tooltip" title="Preview"><i class="ti ti-eye mx-2 ti-sm"></i></a>' +
              '<a href="javascript:;" class="text-body" data-bs-toggle="tooltip" title="Download"><i class="ti ti-dots-vertical mx-1 ti-sm"></i></a>' +
              '</div>'
            );
          }
        }
      ],
      order: [[1, 'desc']],
      dom:
        '<"row mx-4"' +
        '<"col-sm-6 col-12 d-flex align-items-center justify-content-center justify-content-sm-start mb-3 mb-md-0"l>' +
        '<"col-sm-6 col-12 d-flex align-items-center justify-content-center justify-content-sm-end"B>' +
        '>t' +
        '<"row mx-4"' +
        '<"col-md-12 col-lg-6 text-center text-lg-start pb-md-2 pb-lg-0"i>' +
        '<"col-md-12 col-lg-6 d-flex justify-content-center justify-content-lg-end"p>' +
        '>',
      language: {
        sLengthMenu: 'Show _MENU_',
        search: '',
        searchPlaceholder: 'Search Invoice'
      },
      // Buttons with Dropdown
      buttons: [
        {
          extend: 'collection',
          className: 'btn btn-label-secondary dropdown-toggle float-sm-end mb-3 mb-sm-0',
          text: '<i class="ti ti-screen-share ti-xs me-2"></i>Export',
          buttons: [
            {
              extend: 'print',
              text: '<i class="ti ti-printer me-2" ></i>Print',
              className: 'dropdown-item',
              exportOptions: { columns: [1, 2, 3, 4] }
            },
            {
              extend: 'csv',
              text: '<i class="ti ti-file-text me-2" ></i>Csv',
              className: 'dropdown-item',
              exportOptions: { columns: [1, 2, 3, 4] }
            },
            {
              extend: 'excel',
              text: '<i class="ti ti-file-spreadsheet me-2"></i>Excel',
              className: 'dropdown-item',
              exportOptions: { columns: [1, 2, 3, 4] }
            },
            {
              extend: 'pdf',
              text: '<i class="ti ti-file-description me-2"></i>Pdf',
              className: 'dropdown-item',
              exportOptions: { columns: [1, 2, 3, 4] }
            },
            {
              extend: 'copy',
              text: '<i class="ti ti-copy me-2" ></i>Copy',
              className: 'dropdown-item',
              exportOptions: { columns: [1, 2, 3, 4] }
            }
          ]
        }
      ],
      // For responsive popup
      responsive: {
        details: {
          display: $.fn.dataTable.Responsive.display.modal({
            header: function (row) {
              var data = row.data();
              return 'Details of ' + data['full_name'];
            }
          }),
          type: 'column',
          renderer: function (api, rowIdx, columns) {
            var data = $.map(columns, function (col, i) {
              return col.title !== '' // ? Do not show row in modal popup if title is blank (for check box)
                ? '<tr data-dt-row="' +
                col.rowIndex +
                '" data-dt-column="' +
                col.columnIndex +
                '">' +
                '<td>' +
                col.title +
                ':' +
                '</td> ' +
                '<td>' +
                col.data +
                '</td>' +
                '</tr>'
                : '';
            }).join('');

            return data ? $('<table class="table"/><tbody />').append(data) : false;
          }
        }
      }
    });
  }
  // On each datatable draw, initialize tooltip
  dt_invoice_table.on('draw.dt', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl, {
        boundary: document.body
      });
    });
  });

  // Filter form control to default size
  // ? setTimeout used for multilingual table initialization
  setTimeout(() => {
    $('.dataTables_filter .form-control').removeClass('form-control-sm');
    $('.dataTables_length .form-select').removeClass('form-select-sm');
  }, 300);
});
