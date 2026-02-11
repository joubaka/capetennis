'use strict';

$(function () {

    var addCategory = $('.addCategory'),
        addCategoryButton = $('.addCategoryButton');


    addCategory.on('click', function () {
        var region = $(this).data('region');
        $('#leagueRegion').val(region.id);
        console.log('clicked', region.id)
    })
    addCategoryButton.on('click', function () {
        var data = $('#addCategoryForm').serialize();
        console.log(data);
    });
});