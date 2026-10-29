<script>
(function($) {
$('#score-format').on('change', function() {
    const option = this.options[this.selectedIndex];
    $('#score-format-help').text(option.dataset.description || '');
});

// Compare the editable draft against the saved setup; nothing is persisted until save.
const savedScoreFormat = $('#score-format').val() || '';
const savedScoreLabel = $('#score-format option:selected').text();
let draftPlayoffs = () => null;
let savedPlayoffs = null;
let isSavingSettings = false;
const rulesEditable = @json($competitionRulesEditable);
const escapeSettingsText = value => $('<span>').text(String(value)).html().replace(/"/g, '&quot;').replace(/'/g, '&#39;');
const savedGroupCounts = () => (window.RR_SAVED_GROUPS || window.RR_GROUPS || []).map(group => (group.registrations || group.players || []).length);
const assignedPlayerTotal = () => savedGroupCounts().reduce((sum, count) => sum + count, 0);
let refreshPlayoffGroups = () => {};
window.RRSettingsGroupsChanged = function() {
    const counts = savedGroupCounts();
    $('#settings-boxes').val(counts.length);
    $('#rr-overview-player-count').text(assignedPlayerTotal());
    $('#rr-overview-group-count').text(counts.length);
    $('#rr-overview-match-count').text(counts.reduce((sum, count) => sum + count * (count - 1) / 2, 0));
    $('#rr-overview-group-distribution').html((window.RR_SAVED_GROUPS || window.RR_GROUPS || []).map((group, index) => '<div class="col-auto"><span class="badge bg-primary">Group ' + escapeSettingsText(group.name) + ': ' + counts[index] + ' players</span></div>').join(''));
    refreshPlayoffGroups();
};

function updateSettingsChanges() {
    const changes = [];
    if (($('#score-format').val() || '') !== savedScoreFormat) {
        changes.push('Match scoring: ' + savedScoreLabel + ' → ' + $('#score-format option:selected').text());
    }
    const draft = draftPlayoffs();
    if (draft !== null && JSON.stringify(draft) !== JSON.stringify(savedPlayoffs)) {
        const describe = item => item ? item.name + ': ' + (item.enabled ? 'Enabled' : 'Disabled') + ', ' + item.size + ' places, positions ' + (item.positions || []).join(', ') + (item.group_order ? ', group order ' + item.group_order.join(', ') : '') : 'Removed';
        const original = new Map(savedPlayoffs.map(item => [item.slug, item]));
        draft.forEach(item => {
            const before = original.get(item.slug);
            if (JSON.stringify(before) !== JSON.stringify(item)) changes.push((before ? describe(before) : 'New playoff') + ' → ' + describe(item));
            original.delete(item.slug);
        });
        original.forEach(item => changes.push(describe(item) + ' → Removed'));
    }
    $('#draw-settings-changes').html(changes.length ? '<ul class="mb-0">' + changes.map(change => '<li>' + escapeSettingsText(change) + '</li>').join('') + '</ul>' : '<p class="text-muted mb-0">No changes.</p>');
    $('#btn-save-settings').prop('disabled', isSavingSettings || !rulesEditable || !changes.length);
}
$('#score-format').on('change', updateSettingsChanges);
$('#drawSettingsForm').on('submit', function(event) {
    event.preventDefault();
    const $button = $('#btn-save-settings');
    if (isSavingSettings || $button.prop('disabled')) return;

    const payload = {_token: @json(csrf_token())};
    const score = $('#score-format').val() || '';
    if (score !== savedScoreFormat) payload.score_format = score;
    const draft = draftPlayoffs();
    if (draft !== null && JSON.stringify(draft) !== JSON.stringify(savedPlayoffs)) {
        payload.playoff_config = draft;
        payload.preset_key = window.currentPresetKey || null;
    }
    isSavingSettings = true;
    $('#draw-settings-error').text('');
    $button.prop('disabled', true).text('Saving…');
    $.ajax({
        url: APP_URL + '/backend/draw/' + DRAW_ID + '/settings',
        method: 'POST', contentType: 'application/json', data: JSON.stringify(payload),
        success: function(response) {
            if (response.success) window.location.reload();
            else $('#draw-settings-error').text(response.message || 'Unable to save changes.');
        },
        error: function(xhr) {
            const errors = xhr.responseJSON?.errors;
            $('#draw-settings-error').text(errors ? Object.values(errors).flat().join(' ') : (xhr.responseJSON?.message || 'Unable to save changes. Your draft is still here.'));
        },
        complete: function() { isSavingSettings = false; $button.text('Save changes'); updateSettingsChanges(); }
    });
});

// ============================================================
// PLAYOFF CONFIGURATION HANDLERS
// ============================================================
@unless($roundRobinOnly)

// Store playoff config in memory
let playoffConfig = @json($playoffConfig ?? []);
savedPlayoffs = JSON.parse(JSON.stringify(playoffConfig));
draftPlayoffs = () => playoffConfig;
let numGroups = {{ $currentBoxes ?? 4 }};
let maxPositions = 10;
let showDisabledPlayoffs = false;
let showFuturePositions = false;
const qualifierCount = positions => positions.reduce((sum, position) => sum + savedGroupCounts().filter(count => count >= position).length, 0);

// Initialize saved preset key on page load
window.currentPresetKey = '{{ $savedPresetKey ?? '' }}';

// Debug: Log all available preset keys
console.log('📋 [INIT] Available preset keys in dropdown:');
$('#preset-selector option[value!=""]').each(function() {
    console.log('  -', $(this).val(), ':', $(this).text());
});

// If a preset is saved, try to load its maxPositions
if (window.currentPresetKey) {
    console.log('🔍 [INIT] Looking for saved preset:', window.currentPresetKey);
    const $savedOption = $('#preset-selector option[value="' + window.currentPresetKey + '"]');
    if ($savedOption.length > 0) {
        const savedMaxPos = parseInt($savedOption.data('max-positions')) || 10;
        maxPositions = savedMaxPos;
        console.log('✅ [INIT] Loaded saved preset:', window.currentPresetKey, '| maxPositions:', maxPositions);
    } else {
        // Presets can remain saved after the group count changes.
        console.warn('⚠️ [INIT] Saved preset key not found in dropdown:', window.currentPresetKey);
        console.log('[INIT] Keeping the saved preset and its configuration unchanged.');
        // Preserve saved preset identity even when it is not listed for this group count.
    }
}

// Toggle position button
$(document).on('click', '.position-btn', function() {
    const $btn = $(this);
    const idx = $btn.data('idx');
    const pos = $btn.data('pos');
    
    // Get actual player counts
    const groupPlayerCounts = savedGroupCounts();
    const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
    
    // Check if position exists in ANY group
    const groupsWithThisPosition = groupPlayerCounts.filter(count => count >= pos).length;
    
    // Show warning if position doesn't exist in all groups (but allow selection)
    if (groupsWithThisPosition > 0 && groupsWithThisPosition < numGroups && !$btn.hasClass('btn-primary')) {
        Swal.fire({
            title: 'Partial Position Warning',
            html: `<p>Position <strong>#${pos}</strong> exists in only <strong>${groupsWithThisPosition} of ${numGroups}</strong> groups.</p>
                   <p class="text-warning">This will bring <strong>${groupsWithThisPosition} players</strong> (not ${numGroups}).</p>
                   <p class="text-muted small">To get ${numGroups} players, add more players to smaller groups.</p>
                   <p><strong>Continue anyway?</strong></p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, select it',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ffc107'
        }).then((result) => {
            if (result.isConfirmed) {
                togglePositionSelection($btn, idx, pos);
            }
        });
        return;
    }
    
    // Direct toggle for valid positions or deselection
    togglePositionSelection($btn, idx, pos);
});

// Helper function to toggle position selection
function togglePositionSelection($btn, idx, pos) {
    // Toggle button state
    $btn.toggleClass('btn-primary btn-outline-secondary');
    
    // Update config
    if (!playoffConfig[idx].positions) {
        playoffConfig[idx].positions = [];
    }
    
    const posIdx = playoffConfig[idx].positions.indexOf(pos);
    if (posIdx === -1) {
        playoffConfig[idx].positions.push(pos);
    } else {
        playoffConfig[idx].positions.splice(posIdx, 1);
    }
    
    // Sort positions
    playoffConfig[idx].positions.sort((a, b) => a - b);
    
    // Update preview
    updatePlayoffPreview(idx);
    updateFlowPreview();
}

// Update playoff name
$(document).on('input', '.playoff-name', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].name = $(this).val();
    updateFlowPreview();
});

// Update playoff size
$(document).on('change', '.playoff-size', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].size = parseInt($(this).val());
    updatePlayoffPreview(idx);
    updateFlowPreview();
});

// Toggle playoff enabled
$(document).on('change', '.playoff-enabled', function() {
    const idx = $(this).data('idx');
    playoffConfig[idx].enabled = $(this).is(':checked');
    renderPlayoffTable();
    updateFlowPreview();
});

// Remove playoff draw
$(document).on('click', '.btn-remove-playoff', function() {
    const idx = $(this).data('idx');
    
    Swal.fire({
        title: 'Remove this playoff draw?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        confirmButtonColor: '#dc3545'
    }).then((result) => {
        if (result.isConfirmed) {
            playoffConfig.splice(idx, 1);
            renderPlayoffTable();
            updateFlowPreview();
        }
    });
});

// Add new playoff draw
$('#btn-add-playoff').on('click', function() {
    const newIdx = playoffConfig.length;
    playoffConfig.push({
        name: 'New Playoff Draw',
        slug: 'new-' + Date.now() + '-' + newIdx,
        size: 4,
        positions: [],
        enabled: true
    });
    renderPlayoffTable();
    updateFlowPreview();
});

// Load preset template
$('#btn-load-preset').on('click', function() {
    const $select = $('#preset-selector');
    const $option = $select.find(':selected');
    const presetKey = $option.val(); // Store the preset key
    const configJson = $option.data('config');
    const presetMaxPos = parseInt($option.data('max-positions')) || 10;
    const presetGroups = parseInt($option.data('groups')) || numGroups;
    
    if (!configJson || configJson.length === 0) {
        toastr.warning('Please select a preset template first.');
        return;
    }
    
    Swal.fire({
        title: 'Load Preset?',
        html: `This will replace your current playoff configuration.<br><br>` +
              `<small class="text-muted">Template is for <strong>${presetGroups} group(s)</strong> with positions up to <strong>#${presetMaxPos}</strong></small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, load it',
        confirmButtonColor: '#198754'
    }).then((result) => {
        if (result.isConfirmed) {
            playoffConfig = JSON.parse(JSON.stringify(configJson)); // Deep copy
            maxPositions = presetMaxPos; // Update max positions for this template
            
            // Store preset key for saving
            window.currentPresetKey = presetKey;
            
            toastr.success('Preset loaded unchanged. Positions without assigned players remain available for later. Review and save when ready.');

            renderPlayoffTable();
            updateFlowPreview();
        }
    });
});

// Update preview for a single playoff row
function updatePlayoffPreview(idx) {
    const config = playoffConfig[idx];
    const posCount = (config.positions || []).length;
    const totalPlayers = qualifierCount(config.positions || []);
    const $preview = $(`.playoff-preview[data-idx="${idx}"]`);
    const size = config.size || 4;
    
    let statusClass = 'text-muted';
    let statusIcon = '';
    if (totalPlayers > size) {
        statusClass = 'text-danger fw-bold';
        statusIcon = '⚠️ ';
    } else if (totalPlayers === size) {
        statusClass = 'text-success fw-bold';
        statusIcon = '✓ ';
    }
    
    
    $preview.html(`<span class="${statusClass}">${statusIcon}${totalPlayers} players</span>`);
}

// Render the entire playoff table
function renderPlayoffTable() {
    let html = '';
    // Use dynamic maxPositions (set when loading preset, default 10)
    const positionsToShow = Math.max(1, ...savedGroupCounts(), ...(showFuturePositions ? [maxPositions || 10] : []));
    
    // Get actual player counts for validation
    const groupPlayerCounts = savedGroupCounts();
    const minPlayersInGroup = groupPlayerCounts.length > 0 ? Math.min(...groupPlayerCounts) : 0;
    const maxPlayersInGroup = groupPlayerCounts.length > 0 ? Math.max(...groupPlayerCounts) : 0;
    const actualTotalPlayers = assignedPlayerTotal();
    
    playoffConfig.forEach((playoff, idx) => {
        if (!playoff.enabled && !showDisabledPlayoffs) return;
        const positions = playoff.positions || [];
        const totalPlayers = qualifierCount(positions);
        const size = playoff.size || 4;
        
        let statusClass = 'text-muted';
        let statusIcon = '';
        if (totalPlayers > size) {
            statusClass = 'text-danger fw-bold';
            statusIcon = '⚠️ ';
        } else if (totalPlayers === size) {
            statusClass = 'text-success fw-bold';
            statusIcon = '✓ ';
        }
        
        html += `
        <tr data-idx="${idx}">
          <td>
            <div class="form-check form-switch">
              <input class="form-check-input playoff-enabled" type="checkbox" 
                     ${playoff.enabled ? 'checked' : ''}
                     data-idx="${idx}">
            </div>
          </td>
          <td>
            <input type="text" class="form-control form-control-sm playoff-name" 
                   value="${escapeSettingsText(playoff.name)}" data-idx="${idx}" style="min-width: 150px;">
          </td>
          <td>
            <select class="form-select form-select-sm playoff-size" data-idx="${idx}" style="width: 80px;">
              ${[2, 4, 8, 16, 32].map(size => 
                `<option value="${size}" ${playoff.size == size ? 'selected' : ''}>${size}</option>`
              ).join('')}
            </select>
          </td>
          <td>
            <div class="d-flex flex-wrap gap-1">
              ${Array.from(new Set([...Array.from({length: positionsToShow}, (_, i) => i + 1), ...positions])).sort((a, b) => a - b).map(pos => {
                const isSelected = positions.includes(pos);
                const groupsWithPosition = groupPlayerCounts.filter(count => count >= pos).length;
                const isFullyInvalid = pos > maxPlayersInGroup; // Doesn't exist in ANY group
                const isPartial = pos > minPlayersInGroup && pos <= maxPlayersInGroup; // Exists in SOME groups
                
                let btnClass, tooltip, style = '';
                
                if (isSelected) {
                  btnClass = 'btn-primary';
                  tooltip = isPartial ? 
                    `Position #${pos} - Only ${groupsWithPosition}/${numGroups} groups (partial)` :
                    `Position #${pos} from each group`;
                } else if (isFullyInvalid) {
                  btnClass = 'btn-outline-danger';
                  tooltip = `Position #${pos} has no assigned players yet; keep it for later`;
                  style = 'opacity: 0.65;';
                } else if (isPartial) {
                  btnClass = 'btn-outline-warning';
                  tooltip = `Position #${pos} exists in ${groupsWithPosition}/${numGroups} groups (partial)`;
                } else {
                  btnClass = 'btn-outline-secondary';
                  tooltip = `Position #${pos} from each group`;
                }
                
                return `<button type="button" 
                        class="btn btn-sm position-btn ${btnClass}"
                        data-idx="${idx}" 
                        data-pos="${pos}"
                        title="${escapeSettingsText(tooltip)}"
                        ${style ? `style="${style}"` : ''}>
                  #${pos}${isFullyInvalid ? '✗' : isPartial ? '⚠' : ''}
                </button>`;
              }).join('')}
            </div>
            <small class="text-muted">
              <strong>Assigned positions:</strong> ${maxPlayersInGroup ? `#1–#${maxPlayersInGroup}` : 'None yet'}
              ${minPlayersInGroup !== maxPlayersInGroup ? 
                ` | <span class="text-warning">Partial: #${minPlayersInGroup + 1}-#${maxPlayersInGroup}</span>` : ''}
            </small>
            ${positions.some(pos => pos > maxPlayersInGroup) ? '<br><small class="text-muted">Future positions preserved; no qualifiers there yet.</small>' : ''}
          </td>
          <td>
            <small class="playoff-preview ${statusClass}" data-idx="${idx}">
              ${statusIcon}${totalPlayers} players
            </small>
          </td>
          <td>
            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-playoff" data-idx="${idx}">
              <i class="ti ti-trash"></i>
            </button>
          </td>
        </tr>`;
    });
    $('#playoff-config-body').html(html || '<tr><td colspan="6" class="text-muted">No enabled playoffs. Load a preset, add a playoff, or show disabled playoffs.</td></tr>');
    $('#rr-show-disabled-label').text('Show disabled playoffs (' + playoffConfig.filter(item => !item.enabled).length + ')');
}

// Update flow preview - MASTER SYNC FUNCTION
function updateFlowPreview() {
    updateSettingsChanges();
    console.log('🔄 [SYNC] updateFlowPreview called - numGroups:', numGroups, 'maxPositions:', maxPositions);
    
    // Update player accounting first
    updatePlayerAccounting();
    
    const $preview = $('#playoff-flow-preview');
    let html = '';
    
    // Group names
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    // Build flow diagram
    html += '<div class="d-flex align-items-start gap-4 flex-wrap">';
    
    // Groups column
    html += '<div class="border rounded p-3 bg-light">';
    html += '<h6 class="fw-bold mb-2">Groups</h6>';
    groupNames.forEach(name => {
        html += `<div class="badge bg-primary mb-1 d-block">Group ${name}</div>`;
    });
    html += '</div>';
    
    // Arrow
    html += '<div class="d-flex align-items-center"><i class="ti ti-arrow-right fs-4 text-muted"></i></div>';
    
    // Playoff draws
    html += '<div class="d-flex flex-wrap gap-2">';
    playoffConfig.filter(p => p.enabled).forEach(playoff => {
        const positions = playoff.positions || [];
        const totalPlayers = qualifierCount(positions);
        const posText = positions.map(p => '#' + p).join(', ') || 'None';
        
        html += `
        <div class="border rounded p-3 ${totalPlayers > playoff.size ? 'border-danger' : 'border-success'}">
          <h6 class="fw-bold mb-2">${escapeSettingsText(playoff.name)}</h6>
          <div class="small">
            <div><strong>Size:</strong> ${playoff.size} players</div>
            <div><strong>From:</strong> ${posText}</div>
            <div><strong>Total:</strong> ${totalPlayers} players</div>
            ${totalPlayers > playoff.size ? 
              '<span class="badge bg-danger">⚠ Too many players!</span>' : 
              totalPlayers < playoff.size ? 
              '<span class="badge bg-warning">⚠ Needs more players</span>' :
              '<span class="badge bg-success">✓ Perfect fit</span>'
            }
          </div>
        </div>`;
    });
    html += '</div>';
    
    html += '</div>';
    
    $preview.html(html);
    
    console.log('✅ [SYNC] Flow preview updated, triggering seeding chart update...');
    
    // Update detailed seeding chart (which cascades to matrix and bracket viz)
    updateSeedingChart();
}

// Update player accounting and validation
function updatePlayerAccounting() {
    console.log('👥 [ACCOUNTING] Calculating player distribution...');
    
    const $accounting = $('#player-accounting');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    // Use ACTUAL player count from the draw, not theoretical maximum
    const actualTotalPlayers = assignedPlayerTotal();
    const groupPlayerCounts = savedGroupCounts();
    
    // Calculate actual positions available (smallest group determines max valid position)
    const minPlayersInGroup = groupPlayerCounts.length > 0 ? Math.min(...groupPlayerCounts) : 0;
    const maxValidPosition = Math.max(0, ...groupPlayerCounts);
    
    console.log('📊 [ACCOUNTING] Actual players:', actualTotalPlayers, '| Min per group:', minPlayersInGroup, '| Max valid position:', maxValidPosition);
    
    
    
    // Calculate players in each enabled playoff
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    let totalAccommodated = 0;
    let playoffBreakdown = [];
    let partialPositions = []; // Track positions that don't exist in all groups
    
    // Track which positions are used
    let positionsUsed = new Set();
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        let actualPlayers = 0;
        let partialDetails = [];
        
        positions.forEach(pos => {
            positionsUsed.add(pos);
            
            // Count how many groups actually have this position
            let groupsWithPosition = 0;
            groupPlayerCounts.forEach(count => {
                if (pos <= count) {
                    groupsWithPosition++;
                }
            });
            
            actualPlayers += groupsWithPosition;
            
            // Track partial positions
            if (groupsWithPosition < numGroups && groupsWithPosition > 0) {
                partialDetails.push({
                    pos: pos,
                    groups: groupsWithPosition,
                    total: numGroups
                });
                if (!partialPositions.find(p => p.pos === pos)) {
                    partialPositions.push({
                        pos: pos,
                        groups: groupsWithPosition,
                        total: numGroups
                    });
                }
            }
        });
        
        totalAccommodated += actualPlayers;
        
        playoffBreakdown.push({
            name: playoff.name,
            size: playoff.size,
            positions: positions,
            players: actualPlayers,
            partialDetails: partialDetails,
            status: actualPlayers === playoff.size ? 'perfect' : 
                    actualPlayers > playoff.size ? 'overflow' : 'underflow'
        });
    });
    
    // Calculate unallocated positions (only count positions that actually exist in at least one group)
    const unallocatedPositions = [];
    for (let pos = 1; pos <= maxValidPosition; pos++) {
        if (!positionsUsed.has(pos)) {
            // Count how many groups have this position
            const groupsWithPos = groupPlayerCounts.filter(count => count >= pos).length;
            if (groupsWithPos > 0) {
                unallocatedPositions.push({
                    pos: pos,
                    count: groupsWithPos
                });
            }
        }
    }
    const unallocatedPlayers = unallocatedPositions.reduce((sum, p) => sum + p.count, 0);
    
    
    // Build HTML
    let html = '';
    
    // Warning if no players assigned
    if (actualTotalPlayers === 0) {
        html += '<div class="alert alert-danger">';
        html += '<i class="ti ti-alert-circle me-1"></i> ';
        html += '<strong>No Players Assigned!</strong> You can configure playoffs now; qualifiers appear after player assignments are saved.';
        html += '</div>';
        $accounting.html(html);
        return;
    }
    
    // Summary Stats
    html += '<div class="row g-3 mb-4">';
    
    // ACTUAL Total Players
    html += '<div class="col-md-3">';
    html += '<div class="card border-primary">';
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Actual Players in Draw</h6>';
    html += `<h2 class="mb-0 text-primary">${actualTotalPlayers}</h2>`;
    html += `<small class="text-muted">${numGroups} groups | ${minPlayersInGroup}-${Math.max(...groupPlayerCounts)} per group</small>`;
    html += '</div></div></div>';
    
    // Accommodated
    html += '<div class="col-md-3">';
    html += '<div class="card border-success">';
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">In Playoff Draws</h6>';
    html += `<h2 class="mb-0 text-success">${totalAccommodated}</h2>`;
    html += `<small class="text-muted">${enabledPlayoffs.length} playoff draw${enabledPlayoffs.length !== 1 ? 's' : ''}</small>`;
    html += '</div></div></div>';
    
    // Unallocated
    html += '<div class="col-md-3">';
    html += `<div class="card border-${unallocatedPlayers > 0 ? 'warning' : 'secondary'}">`;
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Not in Playoffs</h6>';
    html += `<h2 class="mb-0 text-${unallocatedPlayers > 0 ? 'warning' : 'secondary'}">${unallocatedPlayers}</h2>`;
    html += `<small class="text-muted">${unallocatedPositions.length} position${unallocatedPositions.length !== 1 ? 's' : ''} unused</small>`;
    html += '</div></div></div>';
    
    
    // Status
    const allAccommodated = unallocatedPlayers === 0 && partialPositions.length === 0;
    const hasWarnings = partialPositions.length > 0;
    html += '<div class="col-md-3">';
    html += `<div class="card border-${allAccommodated ? 'success' : hasWarnings ? 'warning' : 'secondary'}">`;
    html += '<div class="card-body text-center">';
    html += '<h6 class="text-muted mb-2">Status</h6>';
    html += `<h2 class="mb-0">${allAccommodated ? '✓' : hasWarnings ? '⚠️' : '○'}</h2>`;
    html += `<small class="text-${allAccommodated ? 'success' : hasWarnings ? 'warning' : 'secondary'} fw-bold">`;
    html += allAccommodated ? 'Valid' : hasWarnings ? 'Partial' : 'Selected qualifiers only';
    html += '</small>';
    html += '</div></div></div>';
    
    html += '</div>';
    
    
    
    // Detailed Breakdown
    if (enabledPlayoffs.length > 0) {
        html += '<div class="table-responsive mb-3">';
        html += '<table class="table table-sm table-bordered">';
        html += '<thead class="table-light">';
        html += '<tr>';
        html += '<th>Playoff Draw</th>';
        html += '<th class="text-center">Bracket Size</th>';
        html += '<th class="text-center">Positions Used</th>';
        html += '<th class="text-center">Players Assigned</th>';
        html += '<th class="text-center">Status</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        playoffBreakdown.forEach(playoff => {
            const statusClass = playoff.status === 'perfect' ? 'table-success' : 
                              playoff.status === 'overflow' ? 'table-danger' : 'table-warning';
            const statusIcon = playoff.status === 'perfect' ? '✓' : 
                             playoff.status === 'overflow' ? '⚠️' : '⚠️';
            const statusText = playoff.status === 'perfect' ? 'Perfect Match' : 
                             playoff.status === 'overflow' ? `${playoff.players - playoff.size} over capacity` : 
                             `${playoff.size - playoff.players} slots empty`;
            
            html += `<tr class="${statusClass}">`;
            html += `<td><strong>${escapeSettingsText(playoff.name)}</strong>`;
            if (playoff.partialDetails.length > 0) {
                html += ` <span class="badge bg-warning text-dark">⚠ ${playoff.partialDetails.length} partial</span>`;
            }
            html += `</td>`;
            html += `<td class="text-center">${playoff.size}</td>`;
            html += `<td class="text-center">`;
            playoff.positions.forEach(pos => {
                const groupsWithPos = groupPlayerCounts.filter(count => count >= pos).length;
                const isPartial = groupsWithPos < numGroups;
                html += `<span class="badge ${isPartial ? 'bg-warning text-dark' : 'bg-primary'} me-1" 
                              title="${groupsWithPos}/${numGroups} groups">#${pos}</span>`;
            });
            html += `</td>`;
            html += `<td class="text-center"><strong>${playoff.players}</strong>`;
            if (playoff.partialDetails.length > 0) {
                const partialStr = playoff.partialDetails.map(p => `#${p.pos}:${p.groups}/${p.total}`).join(', ');
                html += ` <small class="text-warning d-block">(${partialStr})</small>`;
            }
            html += `</td>`;
            html += `<td class="text-center">${statusIcon} ${statusText}</td>`;
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
    }
    
    // Partial positions info
    if (partialPositions.length > 0) {
        html += '<div class="alert alert-info mb-3">';
        html += '<i class="ti ti-info-circle me-1"></i> ';
        html += '<strong>Partial Positions:</strong> ';
        partialPositions.forEach(p => {
            html += `Position <strong>#${p.pos}</strong> exists in <strong>${p.groups}/${p.total}</strong> groups (${p.groups} players). `;
        });
        html += '<br>These positions are valid but won\'t provide the full number of players.';
        html += '</div>';
    }
    
    // Unallocated Positions Warning
    if (unallocatedPlayers > 0) {
        html += '<div class="alert alert-warning mb-0">';
        html += '<i class="ti ti-alert-triangle me-1"></i> ';
        html += `<strong>Warning:</strong> ${unallocatedPlayers} player${unallocatedPlayers !== 1 ? 's' : ''} not assigned to any playoff. `;
        html += '<br><strong>Unused positions:</strong> ';
        unallocatedPositions.forEach(p => {
            html += `#${p.pos} (${p.count} player${p.count !== 1 ? 's' : ''}) `;
        });
        html += '</div>';
    } else if (partialPositions.length === 0) {
        html += '<div class="alert alert-success mb-0">';
        html += '<i class="ti ti-check-circle me-1"></i> ';
        html += `<strong>All Clear!</strong> All ${actualTotalPlayers} players are accommodated in playoff draws.`;
        html += '</div>';
    }
    
    
    $accounting.html(html);
    
    console.log('✅ [ACCOUNTING] Player accounting updated:', {
        total: actualTotalPlayers,
        accommodated: totalAccommodated,
        unallocated: unallocatedPlayers
    });
}

// Seed builder: straight alphabetical order for EVEN group counts
// (gives natural cross-group pairing A↔D, B↔C via standard bracket
// matchups).  For ODD group counts, rotate by floor(N/2) per position
// to avoid same-group R1 clashes.
// An optional groupOrder array overrides the default A→Z ordering for
// specific brackets (e.g. A,B,D,C to avoid Round-2 rematches after BYEs).
function buildSnakeSeeds(positions, groupNames, groupOrder) {
    var seeds = [];
    // Apply group_order override: listed groups first, then any remainder in alpha order
    var orderedNames = groupNames.slice();
    if (groupOrder && groupOrder.length > 0) {
        var listed = groupOrder.filter(function(g) { return groupNames.indexOf(g) !== -1; });
        var remainder = groupNames.filter(function(g) { return listed.indexOf(g) === -1; });
        orderedNames = listed.concat(remainder);
    }
    var n = orderedNames.length;
    var halfOffset = Math.floor(n / 2);
    positions.forEach(function(pos, posIdx) {
        var offset = (n >= 3 && n % 2 !== 0) ? (posIdx * halfOffset) % n : 0;
        for (var g = 0; g < n; g++) {
            var gn = orderedNames[(g + offset) % n];
            seeds.push({ group: gn, position: pos });
        }
    });
    return seeds;
}

// Generate detailed seeding chart - CASCADES TO MATRIX AND BRACKET
function updateSeedingChart() {
    console.log('📊 [SYNC] updateSeedingChart called');
    
    const $chart = $('#playoff-seeding-chart');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    // Filter enabled playoffs
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    
    if (enabledPlayoffs.length === 0) {
        $chart.html('<div class="text-muted">No enabled playoff draws configured.</div>');
        updateCompleteSeedingMatrix(); // Still update the complete matrix
        console.log('⚠️ [SYNC] No enabled playoffs, matrix updated');
        return;
    }
    
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered table-sm">';
    html += '<thead class="table-light">';
    html += '<tr>';
    html += '<th class="text-center">Playoff Draw</th>';
    html += '<th class="text-center">Bracket Position</th>';
    html += '<th class="text-center">From Groups (Position)</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        const totalPlayers = qualifierCount(positions);
        
        // Calculate seeding for this playoff (snake order)
        let seeds = buildSnakeSeeds(positions, groupNames, playoff.group_order || null);
        
        // Now show each seed and where it goes in the bracket
        if (seeds.length > 0) {
            html += `<tr class="table-primary"><td colspan="3"><strong>${escapeSettingsText(playoff.name)}</strong> (${playoff.size}-player draw)</td></tr>`;
            
            seeds.forEach((seed, idx) => {
                const bracketPosition = idx + 1;
                const statusClass = bracketPosition > playoff.size ? 'table-danger' : '';
                
                html += `<tr class="${statusClass}">`;
                html += `<td>${escapeSettingsText(playoff.name)}</td>`;
                html += `<td class="text-center"><strong>Seed ${bracketPosition}</strong></td>`;
                html += `<td class="text-center">Group <strong>${seed.group}</strong> position <strong>#${seed.position}</strong></td>`;
                html += `</tr>`;
            });
        } else {
            html += `<tr><td colspan="3" class="text-muted text-center"><em>${escapeSettingsText(playoff.name)} - No positions selected</em></td></tr>`;
        }
    });
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    html += '<div class="alert alert-info mt-3 mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>How to read:</strong> Each row shows where a specific player will be seeded in the playoff bracket. ';
    html += 'For example, "Seed 1: Group A position #1" means the player who finishes 1st in Group A will be seeded 1st in that playoff draw.';
    html += '</div>';
    
    $chart.html(html);
    
    console.log('✅ [SYNC] Seeding chart updated, cascading to matrix and bracket...');
    
    // Update complete seeding matrix
    updateCompleteSeedingMatrix();
    
    // Update bracket visualization
    updateBracketVisualization();
    
    console.log('✅ [SYNC] All visualizations synced');
}

// Generate complete seeding matrix showing all positions
function updateCompleteSeedingMatrix() {
    console.log('📋 [SYNC] updateCompleteSeedingMatrix called');
    
    const $matrix = $('#complete-seeding-matrix');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    // Determine max positions to show (default to 10 or use maxPositions)
    const maxPos = maxPositions || 10;
    
    html += '<div class="table-responsive">';
    html += '<table class="table table-bordered table-sm table-striped">';
    html += '<thead class="table-dark">';
    html += '<tr>';
    html += '<th class="text-center">Group Position</th>';
    
    // Header for each group
    groupNames.forEach(groupName => {
        html += `<th class="text-center">Group ${groupName}</th>`;
    });
    html += '<th class="text-center">Seed Range</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';
    
    // For each position (1st, 2nd, 3rd, etc.)
    for (let pos = 1; pos <= maxPos; pos++) {
        html += '<tr>';
        html += `<td class="text-center fw-bold">Position #${pos}</td>`;
        
        let seedStart = null;
        let seedEnd = null;
        
        // For each group, calculate the seed number
        groupNames.forEach((groupName, groupIdx) => {
            // Calculate seed number: (position - 1) * numGroups + groupIdx + 1
            const seedNum = (pos - 1) * numGroups + groupIdx + 1;
            
            if (seedStart === null) seedStart = seedNum;
            seedEnd = seedNum;
            
            // Check if this position is used in any enabled playoff
            let isUsed = false;
            let usedIn = [];
            
            playoffConfig.filter(p => p.enabled).forEach(playoff => {
                if ((playoff.positions || []).includes(pos)) {
                    isUsed = true;
                    usedIn.push(playoff.name);
                }
            });
            
            const cellClass = isUsed ? 'table-success' : '';
            const tooltip = usedIn.length > 0 ? `Used in: ${usedIn.join(', ')}` : 'Not used in any playoff';
            
            html += `<td class="text-center ${cellClass}" title="${escapeSettingsText(tooltip)}">`;
            html += `<strong>Seed ${seedNum}</strong>`;
            if (isUsed) {
                html += ` <span class="badge bg-success" style="font-size: 8px;">✓</span>`;
            }
            html += `</td>`;
        });
        
        // Seed range column
        html += `<td class="text-center text-muted"><small>${seedStart}-${seedEnd}</small></td>`;
        html += '</tr>';
    }
    
    html += '</tbody>';
    html += '</table>';
    html += '</div>';
    
    html += '<div class="row mt-3">';
    html += '<div class="col-md-6">';
    html += '<div class="alert alert-success mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>Legend:</strong> ';
    html += '<span class="badge bg-success me-2">✓</span> = Position is used in an enabled playoff draw<br>';
    html += '<strong>Seed Formula:</strong> Seed # = (Position - 1) × Groups + Group Order';
    html += '</div>';
    html += '</div>';
    
    html += '<div class="col-md-6">';
    html += '<div class="alert alert-info mb-0">';
    html += '<strong>Example:</strong> With 4 groups (A, B, C, D):<br>';
    html += '• Position #1 from Group A = <strong>Seed 1</strong><br>';
    html += '• Position #1 from Group B = <strong>Seed 2</strong><br>';
    html += '• Position #2 from Group A = <strong>Seed 5</strong><br>';
    html += '• Position #2 from Group B = <strong>Seed 6</strong>';
    html += '</div>';
    html += '</div>';
    html += '</div>';
    
    $matrix.html(html);
    
    console.log('✅ [SYNC] Complete seeding matrix updated');
}

// Generate bracket visualization showing seed positions and matchups
function updateBracketVisualization() {
    const $viz = $('#bracket-visualization');
    const groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, numGroups);
    
    let html = '';
    
    const enabledPlayoffs = playoffConfig.filter(p => p.enabled);
    
    if (enabledPlayoffs.length === 0) {
        $viz.html('<div class="text-muted">No enabled playoff draws configured.</div>');
        return;
    }
    
    html += '<div class="d-flex flex-wrap gap-3">';
    
    enabledPlayoffs.forEach(playoff => {
        const positions = playoff.positions || [];
        const size = playoff.size;
        
        // Calculate seeds (snake order)
        let seeds = buildSnakeSeeds(positions, groupNames, playoff.group_order || null);
        
        // Generate standard bracket matchups based on size
        const matchups = generateBracketMatchups(size);
        
        html += '<div class="bracket-container">';
        html += `<h6 class="fw-bold mb-3 text-center">${escapeSettingsText(playoff.name)}</h6>`;
        html += `<div class="text-center mb-3"><span class="badge bg-primary">${size}-Player Draw</span></div>`;
        
        if (seeds.length === 0) {
            html += '<div class="text-muted text-center small">No positions selected</div>';
        } else {
            if (seeds.length > size) {
                html += '<div class="alert alert-danger py-1 px-2 mb-2 small">⚠️ Too many! Only first ' + size + ' used</div>';
            } else if (seeds.length < size) {
                html += '<div class="alert alert-warning py-1 px-2 mb-2 small">⚠️ ' + (size - seeds.length) + ' byes needed</div>';
            }
            
            // Show first round matchups
            html += '<div class="bracket-round">';
            html += '<div class="text-center fw-bold mb-2 small text-muted">R1 Matchups</div>';
            
            matchups.forEach((matchup) => {
                const seed1 = seeds[matchup.seed1 - 1];
                const seed2 = seeds[matchup.seed2 - 1];
                
                html += '<div class="bracket-matchup">';
                
                // Seed 1
                html += '<div class="bracket-seed">';
                html += `<span class="bracket-seed-num">#${matchup.seed1}</span>`;
                if (seed1) {
                    html += `<span class="bracket-seed-source">${seed1.group}${seed1.position}</span>`;
                } else {
                    html += '<span class="bracket-seed-source text-danger">BYE</span>';
                }
                html += '</div>';
                
                // VS
                html += '<div class="text-center text-muted" style="font-size: 10px; margin: 1px 0;">vs</div>';
                
                // Seed 2
                html += '<div class="bracket-seed">';
                html += `<span class="bracket-seed-num">#${matchup.seed2}</span>`;
                if (seed2) {
                    html += `<span class="bracket-seed-source">${seed2.group}${seed2.position}</span>`;
                } else {
                    html += '<span class="bracket-seed-source text-danger">BYE</span>';
                }
                html += '</div>';
                
                html += '</div>';
            });
            
            html += '</div>';
        }
        
        html += '</div>';
    });
    
    html += '</div>';
    
    html += '<div class="alert alert-info mt-3 mb-0">';
    html += '<i class="ti ti-info-circle me-1"></i> ';
    html += '<strong>Reading the brackets:</strong> Each matchup shows seed numbers (#1, #2, etc.) and their source position. ';
    html += 'For example, <code>#1 (A1)</code> means Seed 1 is from Group A Position #1. ';
    html += 'Standard tennis seeding ensures top seeds don\'t meet until later rounds.';
    html += '</div>';
    
    $viz.html(html);
}

// Generate standard bracket matchups based on draw size
function generateBracketMatchups(size) {
    const matchups = [];
    
    switch(size) {
        case 2:
            matchups.push({seed1: 1, seed2: 2});
            break;
        case 4:
            matchups.push({seed1: 1, seed2: 4});
            matchups.push({seed1: 2, seed2: 3});
            break;
        case 8:
            matchups.push({seed1: 1, seed2: 8});
            matchups.push({seed1: 3, seed2: 6});
            matchups.push({seed1: 4, seed2: 5});
            matchups.push({seed1: 2, seed2: 7});
            break;
        case 16:
            matchups.push({seed1: 1, seed2: 16});
            matchups.push({seed1: 2, seed2: 15});
            matchups.push({seed1: 3, seed2: 14});
            matchups.push({seed1: 4, seed2: 13});
            matchups.push({seed1: 5, seed2: 12});
            matchups.push({seed1: 6, seed2: 11});
            matchups.push({seed1: 7, seed2: 10});
            matchups.push({seed1: 8, seed2: 9});
            break;
        case 32:
            // Standard 32-draw seeding
            matchups.push({seed1: 1, seed2: 32});
            matchups.push({seed1: 16, seed2: 17});
            matchups.push({seed1: 8, seed2: 25});
            matchups.push({seed1: 9, seed2: 24});
            matchups.push({seed1: 4, seed2: 29});
            matchups.push({seed1: 13, seed2: 20});  
            matchups.push({seed1: 5, seed2: 28});
            matchups.push({seed1: 12, seed2: 21});
            matchups.push({seed1: 2, seed2: 31});
            matchups.push({seed1: 15, seed2: 18});
            matchups.push({seed1: 7, seed2: 26});
            matchups.push({seed1: 10, seed2: 23});
            matchups.push({seed1: 3, seed2: 30});
            matchups.push({seed1: 14, seed2: 19});
            matchups.push({seed1: 6, seed2: 27});
            matchups.push({seed1: 11, seed2: 22});
            break;
    }
    
    return matchups;
}

// Initialize flow preview
$(document).ready(function() {
    console.log('==========================================');
    console.log('🚀 [INIT] Round Robin Playoff System');
    console.log('📊 Initial State:');
    console.log('  - numGroups:', numGroups);
    console.log('  - maxPositions:', maxPositions);
    console.log('  - playoffConfig length:', playoffConfig.length);
    console.log('  - savedPresetKey:', window.currentPresetKey || 'none');
    console.log('  - dropdown value:', $('#preset-selector').val());
    console.log('==========================================');
    
    // Just render the table with the database config (no auto-cleanup)
    renderPlayoffTable();
    
    // Validate configuration on load
    validateDrawConfiguration();
    
    // Initial render of all visualizations
    updateFlowPreview();
    
    console.log('✅ [INIT] All visualizations initialized');
});

// Validate draw configuration on page load
function validateDrawConfiguration() {
    console.log('🔍 [VALIDATION] Checking draw configuration...');
    
    const warnings = [];
    const errors = [];
    
    // 1. Check if actual groups match settings
    const actualGroups = savedGroupCounts().length;
    const settingsGroups = {{ $currentBoxes ?? 4 }};
    
    if (actualGroups !== settingsGroups) {
        warnings.push({
            type: 'group-mismatch',
            message: `Group count mismatch: ${actualGroups} groups exist, but settings show ${settingsGroups}. Consider updating settings.`
        });
        console.warn('⚠️ [VALIDATION] Group count mismatch:', actualGroups, 'vs', settingsGroups);
    } else {
        console.log('✅ [VALIDATION] Group count matches:', actualGroups);
    }
    
    // 2. Check total players
    const totalPlayers = assignedPlayerTotal();
    console.log('📊 [VALIDATION] Total players in draw:', totalPlayers);
    
    if (totalPlayers === 0) {
        warnings.push({
            type: 'no-players',
            message: 'No players assigned to groups yet. Go to "Players & Groups" tab to assign players.'
        });
    }
    
    // 3. Check if playoff config exists
    if (!playoffConfig || playoffConfig.length === 0) {
        warnings.push({
            type: 'no-playoffs',
            message: 'No playoff draws configured. Consider setting up playoff brackets.'
        });
    } else {
        console.log('✅ [VALIDATION] Playoff config exists:', playoffConfig.length, 'draws');
    }
    
    // 4. Check if groups have uneven player counts
    const groupCounts = savedGroupCounts();
    const maxCount = Math.max(...groupCounts);
    const minCount = Math.min(...groupCounts);
    const difference = maxCount - minCount;
    
    if (difference > 2 && totalPlayers > 0) {
        warnings.push({
            type: 'uneven-groups',
            message: `Groups have uneven player counts (${minCount}-${maxCount}). Consider redistributing for fairness.`
        });
        console.warn('⚠️ [VALIDATION] Uneven group distribution:', groupCounts);
    }
    
    // 5. Check draw type
    const drawType = '{{ $draw->drawType->name ?? 'Unknown' }}';
    console.log('📋 [VALIDATION] Draw type:', drawType);
    
    // Display warnings if any
    $('#rr-settings-validation').remove();
    if (warnings.length > 0 || errors.length > 0) {
        displayValidationMessages(warnings, errors);
    } else {
        console.log('✅ [VALIDATION] All checks passed!');
    }
}

// Display validation messages
function displayValidationMessages(warnings, errors) {
    let html = '';
    
    if (errors.length > 0) {
        html += '<div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">';
        html += '<strong><i class="ti ti-alert-triangle me-1"></i> Configuration Errors:</strong><ul class="mb-0 mt-2">';
        errors.forEach(err => {
            html += `<li>${err.message}</li>`;
        });
        html += '</ul>';
        html += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        html += '</div>';
    }
    
    if (warnings.length > 0) {
        html += '<div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">';
        html += '<strong><i class="ti ti-info-circle me-1"></i> Configuration Warnings:</strong><ul class="mb-0 mt-2">';
        warnings.forEach(warn => {
            html += `<li>${warn.message}</li>`;
        });
        html += '</ul>';
        html += '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        html += '</div>';
    }
    
    // Insert after the Draw Overview card
    $('#settings-pane details.card.border-info').after('<div id="rr-settings-validation">' + html + '</div>');
}

$('#rr-show-disabled').on('change', function() {
    showDisabledPlayoffs = this.checked;
    renderPlayoffTable();
});
$('#rr-show-future-positions').on('change', function() {
    showFuturePositions = this.checked;
    renderPlayoffTable();
});
refreshPlayoffGroups = function() {
    numGroups = savedGroupCounts().length;
    renderPlayoffTable();
    validateDrawConfiguration();
    updateFlowPreview();
};
@endunless
})(jQuery);
</script>
