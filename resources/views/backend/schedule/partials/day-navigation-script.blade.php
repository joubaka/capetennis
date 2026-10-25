<script>
document.querySelectorAll('[data-schedule-day-navigation]').forEach(nav => {
  const active = nav.querySelector('[aria-current="date"]');
  if (!active) return;
  const navBox = nav.getBoundingClientRect();
  const activeBox = active.getBoundingClientRect();
  if (activeBox.right > navBox.right) nav.scrollLeft += activeBox.right - navBox.right;
  else if (activeBox.left < navBox.left) nav.scrollLeft -= navBox.left - activeBox.left;
});
</script>
