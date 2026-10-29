<style>
  [data-embedded-teams-selection] > .region-selection-container { padding:0; max-width:none; }
  [data-embedded-teams-selection] .regional-team-card > .collapse > .nav-tabs { display:none; }
  [data-embedded-teams-selection] [data-event-roster-email-action] { display:none !important; }
  [data-embedded-teams-selection] [data-region-task="messages"] { display:none !important; }
  [data-embedded-teams-selection] .region-task-panel[id$="-messages"] { display:none; }
  #tab-communications [data-event-roster-email-action] { display:flex !important; }
  #tab-communications .region-task-tabs,
  #tab-communications .regional-help,
  #tab-communications .region-task-panel { display:none; }
  #tab-communications .region-workspace-card > .card-header > :not(:first-child) { display:none !important; }
  #tab-communications .region-task-panel[id$="-messages"] { display:block; opacity:1; }
  .region-workspace-card { border: 0; box-shadow: 0 .35rem 1.25rem rgba(31, 57, 104, .09); overflow: visible; }
  .region-workspace-card > .card-header { background: linear-gradient(115deg, #12345f, #1f5c9d); color: #fff; --bs-heading-color: #fff; border-radius: var(--bs-card-border-radius, .5rem) var(--bs-card-border-radius, .5rem) 0 0; }
  .region-workspace-card > .card-header h5 { color: #fff; font-weight: 700; text-shadow: 0 1px 1px rgba(0,0,0,.18); }
  .region-workspace-card > .card-header .region-workspace-meta { color: rgba(255,255,255,.9); font-weight: 600; }
  .region-workspace-card > .card-header .badge { border: 1px solid rgba(255,255,255,.5); }
  .regional-summary { display: flex; flex-wrap: wrap; gap: .35rem 1.25rem; padding: .7rem 1rem; border: 1px solid #dbe6f4; border-radius: .65rem; background: #f8fbff; }
  .regional-summary-item { color: #68778c; white-space: nowrap; }
  .regional-summary-item strong { color: #173f78; font-size: 1rem; }
  .regional-team-card { border: 1px solid #dbe6f4; box-shadow: none; }
  .regional-team-card .card-header { background: #f8fafc; }
  .regional-team-card .card-header[data-team-workspace-header] { cursor: pointer; }
  .regional-team-card .table > :not(caption) > * > * { padding: .7rem .65rem; }
  .regional-team-card .reserve-row { background: #fffaf0; }
  .regional-team-card .replacement-player-form { min-width: 20rem; max-width: min(26rem, 80vw); }
  .regional-readonly { border: 1px solid #e2e8f0; background: #fff; }
  .regional-readonly .dropdown-menu, .regional-team-card .dropdown-menu { min-width: 15rem; }
  .regional-help { border: 1px solid #cfe0f4; border-radius: .65rem; background: #f7fbff; }
  .regional-help > summary { cursor: pointer; list-style: none; padding: .85rem 1rem; }
  .regional-help > summary::-webkit-details-marker { display: none; }
  .regional-help-step { border-left: 3px solid #80aee0; padding-left: .75rem; }
  .region-task-tabs { display: flex; flex-direction: row !important; flex-wrap: nowrap; gap: .35rem; padding: .4rem; border: 1px solid #dbe6f4; border-radius: .75rem; background: #f7faff; }
  .region-task-tabs .nav-link { display: flex; flex: 1 1 0; align-items: center; justify-content: center; gap: .4rem; min-width: 0; min-height: 2.75rem; border: 0; border-radius: .55rem; color: #506176; font-weight: 600; white-space: nowrap; }
  .region-task-tabs .nav-link.active { color: #173f78; background: #fff; box-shadow: 0 .15rem .5rem rgba(31, 57, 104, .12); }
  .regional-more-options { position: relative; flex: 0 0 auto; }
  .regional-more-options > summary { cursor: pointer; padding: .7rem 1rem; font-weight: 600; color: #506176; }
  .regional-more-options-menu { position: absolute; right: 0; top: 100%; z-index: 10; min-width: 12rem; padding: .4rem; background: #fff; border: 1px solid #dbe6f4; border-radius: .65rem; box-shadow: 0 .35rem 1rem rgba(31, 57, 104, .12); }
  .regional-more-options-menu .nav-link { width: 100%; justify-content: flex-start; }
  .region-task-panel { padding-top: 1rem; }
  .event-region-selector { width: 100%; max-width: 28rem; }
  .selection-progress { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .6rem; margin: 0; padding: 0; list-style: none; }
  .selection-progress-step { position: relative; min-width: 0; padding: .85rem; border: 1px solid #dbe6f4; border-radius: .65rem; background: #fff; }
  .selection-progress-step::before { display: inline-grid; place-items: center; width: 1.65rem; height: 1.65rem; margin-bottom: .55rem; border-radius: 50%; content: attr(data-step); background: #edf2f7; color: #68778c; font-weight: 700; }
  .selection-progress-step.is-complete { border-color: #b7e2ca; background: #f2fbf6; }
  .selection-progress-step.is-complete::before { content: '\2713'; background: #20a464; color: #fff; }
  .selection-progress-step.is-current { border-color: #80aee0; background: #f3f8ff; box-shadow: inset 0 0 0 1px #80aee0; }
  .selection-progress-step.is-current::before { background: #2374bb; color: #fff; }
  .selection-progress-step .btn-link { padding: 0; color: #173f78; font-weight: 600; text-align: left; text-decoration: none; }
  .selection-progress-step small { display: block; margin-top: .2rem; color: #68778c; }
  .regional-attention { display: flex; flex-wrap: wrap; gap: .5rem; }
  .region-action-status { padding: .45rem 1rem .3rem; color: #68778c; font-size: .75rem; }
  .roster-order-sortable tr[draggable="true"] { cursor: grab; }
  .roster-order-sortable tr.is-dragging { opacity: .45; }
  .roster-order-sortable[aria-busy="true"] { opacity: .65; cursor: wait; }
  .team-publication-button[disabled], .clothing-order-toggle[disabled] { cursor: wait; }
  @media (max-width: 767.98px) {
    .region-selection-container { padding-right: .75rem; padding-left: .75rem; }
    .region-workspaces.tab-content { padding: 0; background: transparent; box-shadow: none; }
    .region-workspace-card > .card-header { padding: 1rem; }
    .region-workspace-card > .card-header > * { min-width: 0; max-width: 100%; }
    .region-workspace-card > .card-header h5 { overflow-wrap: anywhere; }
    .region-workspace-card > .card-header .badge { white-space: normal; text-align: left; }
    .region-workspace-card > .card-body { padding: .75rem; }
    .region-workspace-card > .card-body > .tab-content { padding: 0; box-shadow: none; }
    .regional-team-card .card-header[data-team-workspace-header] { align-items: stretch !important; padding: .85rem; }
    .regional-team-card .card-header[data-team-workspace-header] > * { min-width: 0; width: 100%; }
    .regional-team-card .card-header[data-team-workspace-header] h6,
    .regional-team-card .card-header[data-team-workspace-header] .small { overflow-wrap: anywhere; }
    .regional-team-card .team-workspace-actions { justify-content: flex-start; }
    .regional-team-card .team-workspace-actions .team-workspace-toggle { flex: 1 1 auto; }
    .regional-team-card .tab-content > .tab-pane > form { padding: .85rem !important; }
    .regional-team-card .team-player-table { overflow: visible; }
    .regional-team-card .team-player-table table,
    .regional-team-card .team-player-table tbody,
    .regional-team-card .team-player-table tr,
    .regional-team-card .team-player-table td { display: block; width: 100%; }
    .regional-team-card .team-player-table thead { display: none; }
    .regional-team-card .team-player-table tbody { padding: .75rem; }
    .regional-team-card .team-player-table tr { margin-bottom: .75rem; padding: .8rem; border: 1px solid #dbe6f4; border-radius: .65rem; background: #fff; box-shadow: 0 .12rem .35rem rgba(31, 57, 104, .06); }
    .regional-team-card .team-player-table tr:last-child { margin-bottom: 0; }
    .regional-team-card .team-player-table tr.reserve-row { background: #fffaf0; }
    .regional-team-card .team-player-table td { display: grid; grid-template-columns: minmax(5.5rem, 35%) minmax(0, 1fr); gap: .65rem; align-items: start; padding: .45rem 0; border: 0; overflow-wrap: anywhere; }
    .regional-team-card .team-player-table td::before { content: attr(data-label); color: #68778c; font-size: .75rem; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; }
    .regional-team-card .team-player-table td[data-label="Select"] { grid-template-columns: 1fr; padding-top: 0; }
    .regional-team-card .team-player-table td[data-label="Select"]::before { content: none; }
    .regional-team-card .team-player-table td[data-mobile-full] { display: block; text-align: center; }
    .regional-team-card .team-player-table td[data-mobile-full]::before { content: none; }
    .regional-team-card .replacement-player-form { min-width: 0; width: min(18rem, calc(100vw - 2rem)); max-width: 100%; }
    .regional-more-options-menu { position: static; box-shadow: none; }
    .region-task-tabs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
    .region-task-tabs .nav-link { width: 100% !important; min-width: 0; padding: .6rem .4rem; white-space: normal; line-height: 1.3; font-size: .85rem; }
    .region-task-tabs .nav-link .ti { flex-shrink: 0; }
    .regional-more-options { min-width: 0; }
    .regional-more-options > summary { min-height: 2.75rem; padding: .65rem .4rem; font-size: .85rem; }
    .regional-more-options-menu { min-width: 0; }
    .regional-team-card .team-player-table tr { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .25rem .65rem; box-shadow: none; padding: 1rem; }
    .regional-team-card .team-player-table td { grid-column: 1 / -1; min-width: 0; width: auto; grid-template-columns: 5rem minmax(0, 1fr); font-size: .85rem; }
    .regional-team-card .team-player-table td[data-label="Player"] { grid-column: 1; grid-row: 1; display: block; padding: 0; font-size: 1rem; }
    .regional-team-card .team-player-table td[data-label="Player"]::before,
    .regional-team-card .team-player-table td[data-label="Rank"]::before,
    .regional-team-card .team-player-table td[data-label="Regional action"]::before { content: none; }
    .regional-team-card .team-player-table td[data-label="Select"] { grid-column: 2; grid-row: 1; width: auto; display: block; padding: .1rem 0; }
    .regional-team-card .team-player-table td[data-label="Rank"] { grid-column: 1; grid-row: 2; display: block; }
    .regional-team-card .team-player-table td[data-label="Regional action"] { grid-column: 2; grid-row: 2; display: block; padding: 0; }
    .regional-team-card .team-player-table td[data-label="Selection / payment"] { grid-row: 3; padding-bottom: .65rem; border-bottom: 1px solid #edf0f4; margin-bottom: .25rem; }
    .regional-team-card .team-player-table td[data-label="Selection / payment"]::before { content: 'Status'; }
    .regional-team-card .team-player-table [data-invitation-payment-transfer] { grid-column: 1 / -1; width: 100%; max-width: none !important; min-width: 0; }
    .regional-team-card .team-player-table .badge { white-space: normal; text-align: left; line-height: 1.35; }
    .regional-team-card .team-player-table .dropdown-menu { min-width: min(15rem, calc(100vw - 3rem)) !important; }
    .regional-team-card .team-player-table .dropdown-item { white-space: normal; }
    .selection-progress { grid-template-columns: 1fr; }
    .selection-progress-step { display: grid; grid-template-columns: 2rem 1fr; column-gap: .65rem; align-items: start; }
    .selection-progress-step::before { grid-row: 1 / span 2; margin-bottom: 0; }
  }
</style>
