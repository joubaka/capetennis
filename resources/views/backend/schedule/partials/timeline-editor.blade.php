<div class="modal fade" id="editTimelineModal" tabindex="-1" aria-labelledby="editTimelineLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><form id="edit-timeline-form" class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="editTimelineLabel">Edit timeline time</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p id="edit-timeline-venue" class="fw-semibold"></p>
      <label class="form-label" for="edit-timeline-time">Date and time</label>
      <input id="edit-timeline-time" class="form-control mb-3" type="datetime-local" step="60" required aria-describedby="edit-timeline-help">
      <p id="edit-timeline-help" class="small text-muted">Changes the free court slots in this timeline for this session. Existing matches keep their times. Move a match to the new slot or use Reschedule to change its booking; normal conflict checks still apply. Changing scheduling settings resets edited timeline times.</p>
      <div id="edit-timeline-status" class="text-danger" role="status" aria-live="polite"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Update timeline</button></div>
  </form></div>
</div>
