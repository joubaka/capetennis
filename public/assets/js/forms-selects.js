/**
 * Selects & Tags
 */

'use strict';

$(function () {
  const selectPicker = $('.selectpicker'),
    select2Player = $('.select2player'),
    select2Gender = $('.select2gender'),
    select2Category = $('.select2category '),
    select2Icons = $('.select2-icons');
  var createPlayerButton = $('#createPlayerButton');
  var payment = $('#payment');

  payment.on('click', function () {
    var data = $('form').serialize()
    $.post(APP_URL + '/reg', data, function (data) {
      console.log(data);
    })
    console.log(data);
  })

  $('#addPlayerModal').on('shown.bs.modal', function (e) {
    var i = null;

    i = $(e.relatedTarget).attr('data-index');

    createPlayerButton.attr('data-index', i)
  })


  createPlayerButton.on('click', function (f) {
    var mydata = $('.formPlayer').serialize();
    var selectIndex = 10;


    selectIndex = $('#createPlayerButton').attr('data-index');


    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });
    $.ajax({
      method: "POST",
      url: APP_URL + '/backend/player',
      data: mydata,


      success: function (datas) {

        $('#addPlayerModal').modal('hide');


        var newOption = new Option(datas.name + ' ' + datas.surname, datas.id, false, false);
        var sel = $('.select2player');
        console.log('selects', sel[selectIndex])
        $(sel[selectIndex]).append(newOption).trigger('change');

        $(sel[selectIndex]).val(datas.id).trigger('change');


      },
      error: function (error) {
        console.log(error)
      },

    });
  })

  // Bootstrap Select
  // --------------------------------------------------------------------
  if (selectPicker.length) {
    selectPicker.selectpicker();

  }

  // Select2
  // --------------------------------------------------------------------
  $(".select2category").select2();
  // Default
  if (select2Player.length) {
    select2Player.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent(),
        searchInputPlaceholder: 'Type here to search..',
        language: {
          noResults: function () {
            return $("<div class='btn btn-primary btn-sm'data-name data-index='" + 0 + "' data-bs-toggle='modal' data-bs-target='#addPlayerModal'>Add New Player</div>");
          }
        }

      });
    });
  }
  if (select2Gender.length) {
    select2Gender.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent(),
        searchInputPlaceholder: 'Type here to search..',


      });
    })
  }
  if (select2Category.length) {
    select2Category.each(function () {
      var $this = $(this);
      $this.wrap('<div class="position-relative"></div>').select2({
        placeholder: 'Select value',
        dropdownParent: $this.parent(),
        searchInputPlaceholder: 'Type here to search..',


      });
    });
  }

  // Select2 Icons
  if (select2Icons.length) {
    // custom template to render icons
    function renderIcons(option) {
      if (!option.id) {
        return option.text;
      }
      var $icon = "<i class='" + $(option.element).data('icon') + " me-2'></i>" + option.text;

      return $icon;
    }
    select2Icons.wrap('<div class="position-relative"></div>').select2({
      templateResult: renderIcons,
      templateSelection: renderIcons,
      escapeMarkup: function (es) {
        return es;
      }
    });
  }

});
