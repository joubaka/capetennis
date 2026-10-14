@once
  <style>
    .team-player-identity { min-width: 0; }
    .team-player-identity > .rounded-circle { flex-shrink: 0; }
    .team-player-identity > span:last-child,
    .team-player-identity > a { min-width: 0; overflow-wrap: anywhere; }
    .team-player-actions { flex-shrink: 0; }

    @media (max-width: 575.98px) {
      .team-player-actions > .btn {
        display: inline-flex;
        flex: 1 1 10rem;
        align-items: center;
        justify-content: center;
        min-height: 2.75rem;
        white-space: normal;
      }
    }
  </style>
@endonce
