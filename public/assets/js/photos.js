'use strict';

$(function () {
    $.ajaxSetup({
        headers:
            { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
    });
    var photoTable = $('#photo-list'),
        editFolderButton = $('.edit-folder-button'),
        deleteSelectedButton = $('#deleteSelected'),
        moveSelectedButton = $('#moveSelected'),
        previewPhoto = $('.preview');




    previewPhoto.on('click', function () {
        var photo = $(this).data('image');
        console.log(photo);
        var img = $('<img/>').attr({
            src: APP_URL + '/storage/photoFolder/' + photo.path,
        });
        console.log(img)
        console.log($('#frame').html(img))
    })

    editFolderButton.on('click', function () {
        var id = $(this).data('id');
        $('#folder-name').val(id.name);

        var url = APP_URL + '/backend/photoFolder/' + id.id;
        $('#edit-folder-form').attr('action', url);
        console.log(url);
    })

    console.log(editFolderButton);
    photoTable.dataTable();

    deleteSelectedButton.on('click', function () {
        var rowcollection = photoTable.$(".dt-checkboxes:checked", { "page": "all" });
        var values = Array();
        $.each(rowcollection, function (key, value) {


            values.push($(value).val());

        })
        console.log(values);
        $.ajax({

            method: "POST",
            url: APP_URL + '/backend/photo/deleteSelected',
            data: { data: values },
        }).done(function (msg) {
            console.log(msg);
            location.reload();
        });
    })

    moveSelectedButton.on('click', function () {
        var rowcollection = photoTable.$(".dt-checkboxes:checked", { "page": "all" });
        var values = Array();
        $.each(rowcollection, function (key, value) {


            values.push($(value).val());

        })
        $('#photos').val(values)

        $('#submit-move-button').on('click', function () {
            var folder = $('#folder').val();

            $.ajax({

                method: "POST",
                url: APP_URL + '/backend/photo/moveSelected',
                data: {
                    data: values,
                    'folder_id': folder
                },
            }).done(function (msg) {
                console.log(msg);
                 location.reload();
            }).fail(function (error) {
                console.log(error);
            });
        })


    })
})
