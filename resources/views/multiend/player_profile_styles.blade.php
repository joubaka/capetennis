<style>
  .player-profile-page {
    --profile-border: rgba(75, 70, 92, .12);
    --profile-muted: #6f6b7d;
  }

  .player-profile-page .player-summary-card,
  .player-profile-page .player-planning-card {
    border: 1px solid var(--profile-border);
    box-shadow: 0 .25rem 1.125rem rgba(75, 70, 92, .08);
  }

  .player-profile-page .player-avatar-placeholder {
    align-items: center;
    background: linear-gradient(135deg, rgba(115, 103, 240, .18), rgba(115, 103, 240, .05));
    border-radius: 50%;
    color: var(--bs-primary);
    display: inline-flex;
    font-size: 1.35rem;
    font-weight: 700;
    height: 4.5rem;
    justify-content: center;
    width: 4.5rem;
  }

  .player-profile-page .player-stat {
    background: rgba(115, 103, 240, .05);
    border: 1px solid rgba(115, 103, 240, .12);
    border-radius: .5rem;
    min-height: 4.75rem;
    padding: .875rem;
  }

  .player-profile-page .linked-user-list .list-group-item {
    background: transparent;
    border: 0;
    padding: .4rem 0;
  }

  .player-profile-page .profile-detail-row {
    display: grid;
    gap: .75rem;
    grid-template-columns: 7rem minmax(0, 1fr);
    padding: .42rem 0;
  }

  .player-profile-page .profile-detail-row > :last-child {
    min-width: 0;
    overflow-wrap: anywhere;
  }

  .player-profile-page .planning-tabs-wrap {
    margin-inline: -.25rem;
    overflow-x: auto;
    padding: .25rem;
    scrollbar-width: thin;
  }

  .player-profile-page .planning-tabs {
    display: inline-flex;
    flex-wrap: nowrap;
    gap: .35rem;
    min-width: max-content;
  }

  .player-profile-page .planning-tabs .nav-link {
    border: 1px solid transparent;
    border-radius: .5rem;
    color: var(--profile-muted);
    font-weight: 500;
    padding: .65rem .9rem;
    white-space: nowrap;
  }

  .player-profile-page .planning-tabs .nav-link:not(.active):hover {
    background: rgba(115, 103, 240, .06);
    border-color: rgba(115, 103, 240, .15);
    color: var(--bs-primary);
  }

  .player-profile-page .quick-action-panel {
    background: rgba(75, 70, 92, .025);
    border: 1px solid var(--profile-border);
    border-radius: .75rem;
    height: 100%;
    padding: 1.25rem;
  }

  .player-profile-page .goal-action-grid {
    display: grid;
    gap: .625rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .player-profile-page .goal-action-grid .btn,
  .player-profile-page .record-action-list .btn {
    align-items: center;
    display: flex;
    justify-content: flex-start;
    min-height: 2.75rem;
    text-align: left;
  }

  .player-profile-page .record-action-list {
    display: grid;
    gap: .75rem;
  }

  @media (max-width: 767.98px) {
    .player-profile-page .profile-detail-row {
      grid-template-columns: 6.25rem minmax(0, 1fr);
    }

    .player-profile-page .planning-header {
      margin-top: .5rem;
    }

    .player-profile-page .player-planning-card .tab-content {
      padding: 1rem;
    }
  }

  @media (max-width: 399.98px) {
    .player-profile-page .goal-action-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
