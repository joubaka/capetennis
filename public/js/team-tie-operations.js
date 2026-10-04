(function (document) {
  'use strict';
  var message = document.getElementById('team-tie-message');
  if (!message) return;
  var busy = false;
  document.addEventListener('click', function (event) {
    var button = event.target.closest('.team-tie-action');
    if (!button || button.disabled || busy) return;
    busy = true;
    button.disabled = true;
    message.className = 'alert alert-info';
    message.textContent = 'Checking tie…';
    var token = document.querySelector('meta[name="csrf-token"]');
    fetch(button.dataset.url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' },
      body: '{}'
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (!response.ok || !data.success) {
          var errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
          throw new Error(errors || data.message || 'Could not update the tie. Refresh and try again.');
        }
        message.className = 'alert alert-success';
        message.textContent = data.message || 'Tie updated.';
        window.location.reload();
      });
    }).catch(function (error) {
      message.className = 'alert alert-danger';
      message.textContent = error.message || 'Could not update the tie.';
      message.scrollIntoView({ block: 'nearest' });
      button.disabled = false;
      busy = false;
    });
  });
})(document);
