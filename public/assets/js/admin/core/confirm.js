/**
 * AdminConfirm — Destructive action guard.
 *
 * Wraps AdminModal.confirm with opinionated defaults for dangerous operations.
 *
 * Usage:
 *   AdminConfirm.destructive('Delete all fixtures?', 'This cannot be undone.')
 *     .then(ok => { if (ok) { ... } });
 *
 *   AdminConfirm.lock('Lock Draw?', '<p>...</p>').then(ok => ...)
 *   AdminConfirm.unlock('Unlock Draw?', '<p>...</p>').then(ok => ...)
 */

(function (root) {
  'use strict';

  function destructive(title, htmlOrText) {
    return root.AdminModal.confirm({
      title:        title,
      html:         htmlOrText,
      icon:         'warning',
      confirmText:  'Yes, proceed',
      confirmColor: '#dc3545'
    });
  }

  function lock(title, html) {
    return root.AdminModal.confirm({
      title:        title || 'Lock Draw?',
      html:         html || '<p>Locking prevents further changes.</p>',
      icon:         'warning',
      confirmText:  'Yes, lock',
      confirmColor: '#dc3545'
    });
  }

  function unlock(title, html) {
    return root.AdminModal.confirm({
      title:        title || 'Unlock Draw?',
      html:         html || '<p>Unlocking allows changes.</p>',
      icon:         'question',
      confirmText:  'Yes, unlock',
      confirmColor: '#198754'
    });
  }

  root.AdminConfirm = {
    destructive: destructive,
    lock:        lock,
    unlock:      unlock
  };

}(window));
