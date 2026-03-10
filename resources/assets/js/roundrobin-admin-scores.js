(function ($, window, document) {
  'use strict';


  $(document).ready(function () {

    // OPEN MODAL
    $(document).on('click', '.rr-open-modal', function () {
      const id = $(this).data('id');
      const home = $(this).data('home');
      const away = $(this).data('away');

      $('#rr-fixture-id').val(id);
      $('#rr-match-label').html(`<b>${home}</b> vs <b>${away}</b>`);

      $('.rr-p1-label').text(home);
      $('.rr-p2-label').text(away);

      $('.rr-s1-p1, .rr-s1-p2, .rr-s2-p1, .rr-s2-p2, .rr-s3-p1, .rr-s3-p2')
        .val('');

      new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
    });


    // SAVE SCORE
    $('#rr-score-modal-form').on('submit', function (e) {
      e.preventDefault();

      const id = $('#rr-fixture-id').val();

      function readSet(p1, p2) {
        const v1 = $(p1).val().trim();
        const v2 = $(p2).val().trim();

        if (v1 === '' && v2 === '') return null;
        if (v1 === '' || v2 === '') {
          toastr.error('Complete both sides of the set.');
          throw new Error();
        }
        return `${v1}-${v2}`;
      }

      let sets;
      try {
        sets = [
          readSet('.rr-s1-p1', '.rr-s1-p2'),
          readSet('.rr-s2-p1', '.rr-s2-p2'),
          readSet('.rr-s3-p1', '.rr-s3-p2'),
        ].filter(Boolean);
      } catch (e) {
        return;
      }

      if (!sets.length) {
        toastr.error('Enter at least one set.');
        return;
      }

      const url = window.RR_SAVE_SCORE_URL.replace('FIXTURE_ID', id);

      $.post(url, { sets })
        .done(res => {
          toastr.success('Score saved');

          // Update score in table
          const tr = $(`#rr-score-table tr`).filter(function () {
            return $(this).find('td:first').text() == id;
          });

          if (tr.length && res.fixture) {
            tr.find('td').eq(5).text(res.fixture.score || '');
          }

          const modal = bootstrap.Modal.getInstance(
            document.getElementById('rrScoreModal')
          );
          if (modal) modal.hide();
        })
        .fail(err => {
          toastr.error(err.responseJSON?.message || 'Error saving');
          console.error(err);
        });
    });

    // DELETE SCORE
    $(document).on('click', '.rr-delete-score', function () {
      var $btn = $(this);
      var id = $btn.data('id');
      if (!confirm('Delete this score?')) return;

      $btn.prop('disabled', true);
      var url = window.RR_DELETE_SCORE_URL.replace('FIXTURE_ID', id);

      $.ajax({ url: url, method: 'DELETE' })
        .done(function () {
          toastr.success('Score deleted');
          // Clear score cell & colours in the table row
          var tr = $('#rr-score-table tr').filter(function () {
            return $(this).find('td:first').text().trim() == id ||
                   $(this).find('td.d-none:first').text().trim() == id;
          });
          if (tr.length) {
            tr.find('td').eq(5).text('');
            tr.find('td').removeClass('bg-success bg-danger text-white');
            $btn.remove();
          }
        })
        .fail(function (err) {
          toastr.error(err.responseJSON?.message || 'Error deleting score');
        })
        .always(function () {
          $btn.prop('disabled', false);
        });
    });

  });

})(jQuery, window, document);
