<style>
.clothing-refund-workspace { min-width:0; max-width:100%; }
.clothing-refund-workspace .card-body { min-width:0; }
.clothing-refund-workspace .clothing-refund-lines td { white-space:normal; overflow-wrap:anywhere; }
@media(max-width:767px) {
  .clothing-refund-workspace .table-responsive { overflow:visible; }
  .clothing-refund-workspace .clothing-refund-lines,
  .clothing-refund-workspace .clothing-refund-lines tbody { display:block; width:100%; }
  .clothing-refund-workspace .clothing-refund-lines thead { display:none; }
  .clothing-refund-workspace .clothing-refund-lines tr { display:block; border:1px solid #d9e1eb; border-radius:.5rem; padding:.75rem; margin-bottom:1rem; }
  .clothing-refund-workspace .clothing-refund-lines td { display:block; border:0; padding:.4rem 0; }
  .clothing-refund-workspace .clothing-refund-lines td::before { content:attr(data-label); display:block; color:#566a7f; font-size:.8rem; font-weight:600; margin-bottom:.2rem; }
  .clothing-refund-workspace .clothing-refund-lines td[data-label="Item / size"] { font-weight:600; }
  .clothing-refund-workspace .clothing-refund-lines td[data-label="Select"] { display:flex; align-items:center; gap:.75rem; min-height:44px; }
  .clothing-refund-workspace .clothing-refund-lines td[data-label="Select"]::before { margin-bottom:0; }
  .clothing-refund-workspace .clothing-refund-lines .form-check-input { margin:0; width:1.25rem; height:1.25rem; }
  .clothing-refund-workspace .clothing-refund-lines input[type="number"] { min-height:44px; width:100%; }
  .clothing-refund-workspace .btn { white-space:normal; min-height:44px; max-width:100%; margin-bottom:.4rem; }
}
</style>
