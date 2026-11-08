<style>
  .event-standings .standings-heading {
    padding: .85rem 1rem;
    border: 1px solid #c5dbe9;
    border-radius: .8rem;
    background: linear-gradient(110deg, #e5f0fb, #e1f4ef);
  }
  .event-standings .standings-heading i { color: #087169; }
  .event-standings .standings-panel { border-inline-start: 4px solid var(--standings-accent); }
  .event-standings .standings-publication { --standings-accent: #256b9c; background: #f1f7fd; }
  .event-standings .standings-ages { --standings-accent: #148477; background: #eff9f6; }
  .event-standings .standings-filters { --standings-accent: #7560ab; background: #f7f4fc; }
  .event-standings .standings-panel h2 i { color: var(--standings-accent); }
  .event-standings .standings-status {
    display: inline-block;
    padding: .25rem .65rem;
    border-radius: 2rem;
    font-size: .8rem;
    font-weight: 700;
  }
  .event-standings .standings-status.is-published { background: #d6f2e6; color: #175b41; }
  .event-standings .standings-status.is-unpublished { background: #ffedca; color: #754a12; }
  .event-standings .standings-age-link {
    min-height: 44px;
    border: 1px solid #8dbfb4;
    background: #fff;
    color: #165b50;
  }
  .event-standings .standings-age-link:hover { background: #d7eee8; color: #11493f; }
  .event-standings .standings-age-link[aria-current="page"] { border-color: #176558; background: #176558; color: #fff; }
  .event-standings .standings-filters :is(.btn, .form-select), .event-standings .standings-heading .btn { min-height: 44px; }
  .event-standings .standings-overall > .card-header { background: #e8f1fc; }
  .event-standings .standings-age-results > .card-header { background: #e2f4ee; }
  .event-standings .standings-draw > .card-header { background: #eef2f8; }
  .event-standings .standings-results > .table-responsive thead th { background: #f1f5fa; color: #29435c; }
  .event-standings .standings-results td[data-tie-points] + td { background: #e7f5ef; color: #185540; font-weight: 700; }
  .event-standings .standings-results .text-warning { color: #794d14 !important; }
  .event-standings .standings-stat { border-top: 3px solid #256b9c; }
  .event-standings .standings-stat[data-stat="teams"] { border-top-color: #148477; }
  .event-standings .standings-stat[data-stat="ties"] { border-top-color: #7560ab; }
  .event-standings .standings-stat[data-stat="rubbers"] { border-top-color: #a36125; }
  .event-standings :focus-visible { outline: 2px solid #186f77; outline-offset: 2px; }
  .event-standings :is(.standings-draw, .standings-filters) > summary { cursor: pointer; }
  @media (max-width: 575.98px) {
    .event-standings .standings-panel { padding: 1rem; }
    .event-standings .standings-age-link { flex: 1 1 7rem; }
  }
</style>
