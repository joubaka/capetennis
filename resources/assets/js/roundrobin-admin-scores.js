(function ($, window, document) {
  'use strict';

  $(document).ready(function () {

    // -------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------
    function getRow(id) {
      return $('#rr-score-table tbody tr').filter(function () {
        return $(this).find('td.d-none:first').text().trim() == id;
      });
    }

    function applyRowColours(tr, winner, reg1Id, reg2Id) {
      // td indices (hidden id=0, p1=1, p2=3)
      tr.find('td').eq(1).removeClass('bg-success bg-danger text-white');
      tr.find('td').eq(3).removeClass('bg-success bg-danger text-white');
      if (!winner) return;
      if (winner == reg1Id) {
        tr.find('td').eq(1).addClass('bg-success text-white');
        tr.find('td').eq(3).addClass('bg-danger text-white');
      } else if (winner == reg2Id) {
        tr.find('td').eq(1).addClass('bg-danger text-white');
        tr.find('td').eq(3).addClass('bg-success text-white');
      }
    }

    function rebuildActionsCell(tr, id, home, away, hasScore) {
      const $td = tr.find('td').last();
      let html = `<button class="btn btn-sm btn-primary rr-open-modal"
        data-id="${id}" data-home="${home}" data-away="${away}">Enter</button>`;
      if (hasScore) {
        html += ` <button class="btn btn-sm btn-outline-danger rr-delete-score"
          data-id="${id}"><i class="ti ti-trash"></i></button>`;
      }
      $td.html(html);
    }

    // -------------------------------------------------------
    // OPEN MODAL — prefill existing scores
    // -------------------------------------------------------
    $(document).on('click', '.rr-open-modal', function () {
      const id    = $(this).data('id');
      const home  = $(this).data('home');
      const away  = $(this).data('away');

      $('#rr-fixture-id').val(id);
      $('#rr-match-label').html(`<b>${home}</b> vs <b>${away}</b>`);
      $('.rr-p1-label').text(home);
      $('.rr-p2-label').text(away);

      // Clear inputs first
      $('.rr-s1-p1,.rr-s1-p2,.rr-s2-p1,.rr-s2-p2,.rr-s3-p1,.rr-s3-p2').val('');

      // Prefill from existing score cell (format "6-4, 3-6, 7-5")
      const scoreText = getRow(id).find('td').eq(5).text().trim();
      if (scoreText) {
        const sets = scoreText.split(',').map(s => s.trim());
        sets.forEach(function(set, i) {
          const parts = set.split('-');
          if (parts.length === 2 && i < 3) {
            $(`.rr-s${i+1}-p1`).val(parts[0]);
            $(`.rr-s${i+1}-p2`).val(parts[1]);
          }
        });
      }

      new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
    });

    // -------------------------------------------------------
    // SAVE SCORE
    // -------------------------------------------------------
    $('#rr-score-modal-form').on('submit', function (e) {
      e.preventDefault();

      const id   = $('#rr-fixture-id').val();
      const home = $('.rr-p1-label').first().text();
      const away = $('.rr-p2-label').first().text();

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
      const $btn = $('#rr-score-modal-form [type="submit"]');
      $btn.prop('disabled', true).text('Saving…');

      $.post(url, { sets })
        .done(res => {
          toastr.success('Score saved');

          const tr = getRow(id);
          if (tr.length && res.fixture) {
            // Update score cell
            tr.find('td').eq(5).text(res.fixture.score || '');
            // Update winner/loser colours
            applyRowColours(tr, res.fixture.winner_registration,
              res.fixture.r1_id, res.fixture.r2_id);
            // Rebuild actions cell (adds delete button)
            rebuildActionsCell(tr, id, home, away, true);
          }

          const modal = bootstrap.Modal.getInstance(document.getElementById('rrScoreModal'));
          if (modal) modal.hide();
        })
        .fail(err => {
          toastr.error(err.responseJSON?.message || 'Error saving score');
          console.error(err);
        })
        .always(() => {
          $btn.prop('disabled', false).text('Save');
        });
    });

    // -------------------------------------------------------
    // DELETE SCORE
    // -------------------------------------------------------
    $(document).on('click', '.rr-delete-score', function () {
      if (!confirm('Delete this score?')) return;
      const $btn = $(this);
      const id   = $btn.data('id');
      const tr   = getRow(id);
      const home = tr.find('.rr-open-modal').data('home') || '';
      const away = tr.find('.rr-open-modal').data('away') || '';

      $btn.prop('disabled', true);
      const url = window.RR_DELETE_SCORE_URL.replace('FIXTURE_ID', id);

      $.ajax({ url, method: 'DELETE' })
        .done(function () {
          toastr.success('Score deleted');
          tr.find('td').eq(5).text('');
          applyRowColours(tr, null);
          rebuildActionsCell(tr, id, home, away, false);
        })
        .fail(function (err) {
          toastr.error(err.responseJSON?.message || 'Error deleting score');
          $btn.prop('disabled', false);
        });
    });

  });

})(jQuery, window, document);

