/* Roster email review and canonical player-management entry points. */
(function ($, window, document) {
  'use strict';
  const APP_URL = window.APP_URL || window.location.origin;
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' } });
  window.emailQuill = null;

  function resetEmailForm() {
    const form = document.getElementById('sendMailForm');
    if (!form) return false;
    form.reset();
    $('#emailPlayerId, #emailTeamId, #catEvent, #emailToHidden, #target_type').val('');
    $('#emailRecipientSelect, #emailRegionSelect, #emailTeamSelect, #emailCategorySelect').val(null).trigger('change');
    $('#emailRecipientSelect').closest('.mb-3').addClass('d-none');
    $('#regionSelectWrapper, #teamSelectWrapper, #categorySelectWrapper').addClass('d-none');
    window.emailQuill?.setText('');
    return true;
  }

  $('#sendMailModal').on('shown.bs.modal', function () {
    if (!window.emailQuill) {
      window.emailQuill = new Quill('#messageEditor', {
        theme: 'snow', placeholder: 'Write your message for review…',
        modules: { toolbar: [['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']] }
      });
    }
    window.emailQuill.focus();
  });

  $(document).on('click', '.emailPlayer, .emailTeamBtn, .emailRegionBtn, .emailUnpaidRegionBtn', function (event) {
    event.preventDefault();
    if (!resetEmailForm()) return;
    const $button = $(this);
    let label;
    if ($button.hasClass('emailPlayer')) {
      $('#target_type').val('player');
      $('#emailToHidden, #emailPlayerId').val($button.data('playerid'));
      label = `Review email: ${$button.data('name')}`;
    } else if ($button.hasClass('emailTeamBtn')) {
      $('#target_type').val('team');
      $('#emailToHidden').val('All players in team');
      $('#emailTeamId').val($button.data('teamid'));
      label = `Review email to players in team: ${$button.data('teamname')}`;
    } else {
      const unpaid = $button.hasClass('emailUnpaidRegionBtn');
      $('#target_type').val('region');
      $('#emailToHidden').val(unpaid ? 'All Unregistered players in Region' : 'All players in region');
      $('#regionSelectWrapper').removeClass('d-none');
      const $select = $('#emailRegionSelect');
      if ($select.hasClass('select2-hidden-accessible')) $select.select2('destroy');
      $select.empty().append(new Option($button.data('regionname'), $button.data('regionid'), true, true))
        .select2({ width: '100%', dropdownParent: $('#sendMailModal') });
      label = `Review email to ${unpaid ? 'unpaid ' : ''}players in region: ${$button.data('regionname')}`;
    }
    $('#sendMailLabel').text(label);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('sendMailModal')).show();
  });

  $('#sendMailForm').on('submit', function (event) {
    event.preventDefault();
    const $form = $(this);
    if ($form.data('busy')) return;
    if (window.emailQuill) $('#emailMessage').val(window.emailQuill.root.innerHTML);
    const $button = $form.find('[type="submit"]');
    $form.data('busy', true);
    $button.prop('disabled', true);
    toastr.info('Preparing recipient and message review…');
    // Hidden context fields are authoritative for these scoped entry points.
    const data = $form.serializeArray().filter(field => {
      if (field.name === 'to') return field.value === String($('#emailToHidden').val());
      if (field.name === 'team_id') return field.value !== '';
      if (field.name === 'catEvent') return field.value !== '';
      return true;
    });
    $.post(APP_URL + '/backend/email/send', $.param(data))
      .done(response => {
        if (response.review_required && response.review_url) {
          window.location.assign(response.review_url);
          return;
        }
        if (!response.success) { toastr.error(response.message || response.result?.message || 'Unable to prepare email review.'); return; }
        toastr.success(response.message || 'Email prepared.');
        bootstrap.Modal.getInstance(document.getElementById('sendMailModal'))?.hide();
        if (response.result?.report_url) window.location.assign(response.result.report_url);
      })
      .fail(xhr => toastr.error(xhr.responseJSON?.message || 'Unable to prepare email review. Your draft has been kept.'))
      .always(() => { $form.data('busy', false); $button.prop('disabled', false); });
  });

  // Old shared views still have these controls. Never revive the legacy direct writes.
  $(document).on('click', '.editRosterBtn, .replacePlayerBtn', function (event) {
    event.preventDefault();
    const teamId = Number($(this).data('teamid'));
    if (teamId) window.location.assign(APP_URL + '/backend/teams/' + teamId + '/substitutions');
  });
  $(document).on('click', '.changePayStatus, .refundToWallet', function (event) {
    event.preventDefault();
    const eventId = Number($('#event_id').val());
    if (eventId) window.location.assign(APP_URL + '/backend/event/' + eventId + '/finances');
  });
})(jQuery, window, document);
