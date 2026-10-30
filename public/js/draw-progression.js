$(document).ready(function () {
    // Shared review flow: confirm canonical standings before progressing.
    function escapeProgressReviewHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function progressGroupHtml(group, checkboxId, destination) {
        var rows = group.standings.map(function (row) {
            var tiebreak = row.tiebreak
                ? '<span class="badge bg-label-info">' + escapeProgressReviewHtml(row.tiebreak) + '</span>'
                : '—';

            return '<tr>'
                + '<td class="text-center fw-bold">' + row.position + '</td>'
                + '<td class="text-start fw-semibold">' + escapeProgressReviewHtml(row.player) + '</td>'
                + '<td class="text-center">' + row.played + '</td>'
                + '<td class="text-center">' + row.wins + '</td>'
                + '<td class="text-center">' + row.losses + '</td>'
                + '<td class="text-center text-nowrap">' + row.sets_won + '–' + row.sets_lost + '</td>'
                + '<td class="text-center text-nowrap">' + row.games_won + '–' + row.games_lost + '</td>'
                + '<td class="text-center">' + tiebreak + '</td>'
                + '</tr>';
        }).join('');

        return '<p class="text-muted mb-3">Check the final order carefully. These positions will be used to place players into the ' + escapeProgressReviewHtml(destination) + '.</p>'
            + '<div class="table-responsive border rounded mb-3">'
            + '<table class="table table-sm table-striped align-middle mb-0">'
            + '<thead><tr><th class="text-center">Pos</th><th class="text-start">Player / team</th><th class="text-center">P</th>'
            + '<th class="text-center">W</th><th class="text-center">L</th><th class="text-center">Sets</th>'
            + '<th class="text-center">Games</th><th class="text-center">Tie-break</th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table></div>'
            + '<div class="form-check text-start border rounded p-3 ps-5 bg-light">'
            + '<input class="form-check-input" type="checkbox" id="' + checkboxId + '">'
            + '<label class="form-check-label fw-semibold" for="' + checkboxId + '">I confirm that the standings and final order for '
            + escapeProgressReviewHtml(group.name) + ' are correct.</label></div>';
    }

    $(document).on('click', '.progress-draw', async function () {
        var $button = $(this);
        if (window.RRGroups && (window.RRGroups.isDirty() || window.RRGroups.isBusy())) {
            toastr.warning('Save your player assignments before reviewing progression.');
            return;
        }
        $button.prop('disabled', true).attr('aria-busy', 'true');

        Swal.fire({
            title: 'Loading final standings…',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); }
        });

        try {
            var response = await $.get($button.data('review-url'));
            var review = response.review;
            var destination = review.destination || 'playoffs';
            var groups = review.groups || [];

            if (!groups.length) {
                throw { responseJSON: { message: 'No round-robin groups are available to review.' } };
            }

            var confirmedGroupIds = [];
            var step = 0;
            while (step < groups.length) {
                var group = groups[step];
                var checkboxId = 'confirm-progress-group-' + group.id;
                var isLast = step === groups.length - 1;
                var result = await Swal.fire({
                    titleText: 'Confirm ' + group.name,
                    html: progressGroupHtml(group, checkboxId, destination),
                    icon: 'question',
                    width: 960,
                    progressSteps: groups.map(function (_, index) { return String(index + 1); }),
                    currentProgressStep: step,
                    showCancelButton: true,
                    showDenyButton: step > 0,
                    denyButtonText: 'Back',
                    cancelButtonText: 'Cancel',
                    confirmButtonText: isLast ? 'Confirm & progress to ' + destination : 'Confirm & next group',
                    focusConfirm: false,
                    preConfirm: function () {
                        if (!document.getElementById(checkboxId).checked) {
                            Swal.showValidationMessage('Confirm that this group is correct before continuing.');
                            return false;
                        }
                        return true;
                    }
                });

                if (result.isDenied) {
                    step--;
                    confirmedGroupIds.pop();
                    continue;
                }
                if (!result.isConfirmed) {
                    return;
                }

                confirmedGroupIds.push(group.id);
                step++;
            }

            var progressResponse = await $.post($button.data('url'), {
                _token: $('meta[name="csrf-token"]').attr('content')
                , confirmed_group_ids: confirmedGroupIds
                , standings_snapshot: review.snapshot
            });

            await Swal.fire({
                title: 'Draw progressed',
                text: progressResponse.message,
                icon: 'success',
                confirmButtonText: 'Refresh draws'
            });
            window.location.reload();
        } catch (xhr) {
            Swal.close();
            toastr.error(xhr.responseJSON?.message || 'Could not progress ' + $button.data('draw-name') + '.');
        } finally {
            $button.prop('disabled', false).removeAttr('aria-busy');
        }
    });

});
