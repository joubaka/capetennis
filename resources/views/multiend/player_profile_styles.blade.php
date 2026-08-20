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
    width: 100%;
  }

  .player-profile-page .planning-tabs {
    background: rgba(75, 70, 92, .04);
    border-radius: .65rem;
    display: grid;
    gap: .4rem;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    padding: .35rem;
    width: 100%;
  }

  .player-profile-page .planning-tabs .nav-link {
    border: 1px solid transparent;
    border-radius: .5rem;
    color: var(--profile-muted);
    font-weight: 500;
    align-items: center;
    display: flex;
    justify-content: center;
    min-height: 3rem;
    padding: .65rem .9rem;
  }

  .player-profile-page .planning-tabs .nav-link:not(.active):hover {
    background: rgba(115, 103, 240, .06);
    border-color: rgba(115, 103, 240, .15);
    color: var(--bs-primary);
  }

  .player-profile-page .player-planning-card > .card-header {
    background: transparent;
    border-bottom: 1px solid var(--profile-border);
    padding: 1rem;
  }

  .player-profile-page .player-planning-card > .tab-content {
    padding: 1.5rem;
  }

  .player-profile-page .profile-empty-state {
    align-items: center;
    background: rgba(75, 70, 92, .025);
    border: 1px dashed rgba(75, 70, 92, .18);
    border-radius: .75rem;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 13rem;
    padding: 2rem;
    text-align: center;
  }

  .player-profile-page .profile-empty-icon {
    align-items: center;
    border-radius: 50%;
    display: inline-flex;
    font-size: 1.5rem;
    height: 3.25rem;
    justify-content: center;
    margin-bottom: 1rem;
    width: 3.25rem;
  }

  .player-profile-page .discipline-summary {
    background: rgba(75, 70, 92, .035);
    border-radius: .5rem;
    padding: .8rem 1rem;
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

    .player-profile-page .planning-tabs .nav-link {
      font-size: .875rem;
      padding-inline: .5rem;
    }
  }

  @media (max-width: 399.98px) {
    .player-profile-page .goal-action-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
