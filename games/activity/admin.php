<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
if (($_SESSION['role'] ?? 'user') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}
$username = htmlspecialchars($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel – Activity Tracker</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body data-theme="dark">

<!-- Navbar -->
<nav class="navbar">
  <span class="navbar-brand">🏃 Activity Tracker</span>
  <button class="navbar-toggle" id="navbar-toggle" aria-expanded="false" aria-label="Toggle menu">☰</button>
  <ul class="navbar-nav">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="profile.php">Profile</a></li>
    <li><a href="admin.php" class="active">Admin</a></li>
  </ul>
  <div class="navbar-right">
    <span class="navbar-user">👤 <strong id="navbar-username"><?= $username ?></strong></span>
    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
  </div>
</nav>

<div class="page-container">
  <div class="page-header">
    <h1>⚙️ Admin Panel</h1>
    <p>Manage users and the activity catalog</p>
  </div>

  <!-- Admin Tabs -->
  <div class="admin-tabs">
    <button class="admin-tab-btn active" data-section="users">👥 Users</button>
    <button class="admin-tab-btn"        data-section="activities">🏋️ Activity Catalog</button>
  </div>

  <!-- Users Section -->
  <div class="admin-section active" id="section-users">
    <div class="card">
      <div class="card-title"><span class="icon">👥</span> Registered Users</div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Last Login</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="users-table-body">
            <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1.5rem">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Activities Section -->
  <div class="admin-section" id="section-activities">
    <div class="card mb-3" style="margin-bottom:1.5rem">
      <div class="card-title"><span class="icon">➕</span> Add New Activity</div>
      <div id="add-act-error"   class="alert alert-error   hidden"></div>
      <div id="add-act-success" class="alert alert-success hidden"></div>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end;flex-wrap:wrap">
        <div class="form-group" style="margin:0">
          <label for="new-act-name">Activity Name</label>
          <input type="text" id="new-act-name" class="form-control" placeholder="e.g. Skateboarding">
        </div>
        <div class="form-group" style="margin:0">
          <label for="new-act-unit">Unit</label>
          <input type="text" id="new-act-unit" class="form-control" placeholder="e.g. Mins">
        </div>
        <div class="form-group" style="margin:0">
          <label for="new-act-factor">Factor</label>
          <input type="number" id="new-act-factor" class="form-control" placeholder="e.g. 1.5" step="0.001" min="0.001">
        </div>
        <div class="form-group" style="margin:0">
          <label for="new-act-category">Category</label>
          <input type="text" id="new-act-category" class="form-control" placeholder="e.g. Cardio">
        </div>
        <div style="padding-top:1.6rem">
          <button class="btn btn-primary" id="add-act-btn">Add</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-title"><span class="icon">📋</span> Activity Catalog</div>
      <div style="margin-bottom:.75rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
        <input type="text" id="act-search" class="form-control" placeholder="🔍 Search activities…" style="max-width:280px">
        <span id="act-count" style="color:var(--text-muted);font-size:.88rem"></span>
      </div>
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Activity Name</th>
              <th>Unit</th>
              <th>Factor</th>
              <th>Category</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody id="activities-table-body">
            <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /page-container -->

<!-- Reset Password Modal -->
<div class="modal-overlay" id="reset-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>🔑 Password Reset</h3>
      <button class="modal-close" id="reset-modal-close">&times;</button>
    </div>
    <p style="color:var(--text-muted);margin-bottom:.75rem">Temporary password for <strong id="reset-username-display"></strong>:</p>
    <div class="temp-password" id="temp-password-display">—</div>
    <p class="form-hint">Share this password with the user. They should change it immediately after logging in.</p>
    <div class="modal-footer">
      <button class="btn btn-primary" id="reset-modal-copy">📋 Copy</button>
      <button class="btn btn-secondary" id="reset-modal-close2">Close</button>
    </div>
  </div>
</div>

<!-- Remove User Confirm Modal -->
<div class="modal-overlay" id="remove-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>⚠️ Remove User</h3>
      <button class="modal-close" id="remove-modal-close">&times;</button>
    </div>
    <p>Are you sure you want to permanently remove user <strong id="remove-username-display"></strong>? This action cannot be undone.</p>
    <div class="modal-footer">
      <button class="btn btn-danger" id="confirm-remove-btn">Remove User</button>
      <button class="btn btn-secondary" id="remove-modal-close2">Cancel</button>
    </div>
  </div>
</div>

<!-- Remove Activity Confirm Modal -->
<div class="modal-overlay" id="remove-act-modal">
  <div class="modal">
    <div class="modal-header">
      <h3>⚠️ Remove Activity</h3>
      <button class="modal-close" id="remove-act-modal-close">&times;</button>
    </div>
    <p>Remove <strong id="remove-act-name-display"></strong> from the catalog?</p>
    <div class="modal-footer">
      <button class="btn btn-danger" id="confirm-remove-act-btn">Remove</button>
      <button class="btn btn-secondary" id="remove-act-modal-close2">Cancel</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>
<script src="js/app.js"></script>
<script>
(function() {
  var allActivities = {};
  var userToRemove  = '';
  var actToRemove   = '';

  // Tab switching
  document.querySelectorAll('.admin-tab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var section = this.dataset.section;
      document.querySelectorAll('.admin-tab-btn').forEach(function(b) { b.classList.toggle('active', b.dataset.section === section); });
      document.querySelectorAll('.admin-section').forEach(function(s) { s.classList.toggle('active', s.id === 'section-' + section); });
    });
  });

  // Modal helpers
  function openModal(id) { document.getElementById(id).classList.add('active'); }
  function closeModal(id) { document.getElementById(id).classList.remove('active'); }

  ['reset-modal-close','reset-modal-close2'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function() { closeModal('reset-modal'); });
  });
  ['remove-modal-close','remove-modal-close2'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function() { closeModal('remove-modal'); });
  });
  ['remove-act-modal-close','remove-act-modal-close2'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function() { closeModal('remove-act-modal'); });
  });

  // Close on overlay click
  ['reset-modal','remove-modal','remove-act-modal'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
      if (e.target === this) closeModal(id);
    });
  });

  // Copy temp password
  document.getElementById('reset-modal-copy').addEventListener('click', function() {
    var pw = document.getElementById('temp-password-display').textContent;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(pw).then(function() { App.showToast('Copied to clipboard!', 'success'); });
    }
  });

  /* ── Load users ── */
  function loadUsers() {
    App.fetchJSON('./api/admin.php', { action: 'list_users' }).then(function(data) {
      var tbody = document.getElementById('users-table-body');
      if (!data.users || data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1.5rem">No users found</td></tr>';
        return;
      }
      tbody.innerHTML = '';
      data.users.forEach(function(u) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><strong>' + App.escapeHtml(u.username) + '</strong></td>' +
          '<td style="color:var(--text-muted)">' + (u.last_login || 'Never') + '</td>' +
          '<td><span class="badge badge-' + u.role + '">' + u.role + '</span></td>' +
          '<td><div class="d-flex gap-1">' +
            '<button class="btn btn-sm btn-secondary reset-pw-btn" data-user="' + App.escapeHtml(u.username) + '">🔑 Reset PW</button>' +
            '<button class="btn btn-sm btn-danger remove-user-btn" data-user="' + App.escapeHtml(u.username) + '">✕ Remove</button>' +
          '</div></td>';
        tbody.appendChild(tr);
      });

      tbody.querySelectorAll('.reset-pw-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var user = this.dataset.user;
          document.getElementById('reset-username-display').textContent = user;
          document.getElementById('temp-password-display').textContent = '…';
          openModal('reset-modal');
          App.fetchJSON('./api/admin.php', { action: 'reset_password', username: user }).then(function(data) {
            if (data.success) {
              document.getElementById('temp-password-display').textContent = data.temp_password;
            } else {
              closeModal('reset-modal');
              App.showToast(data.error || 'Reset failed', 'error');
            }
          });
        });
      });

      tbody.querySelectorAll('.remove-user-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          userToRemove = this.dataset.user;
          document.getElementById('remove-username-display').textContent = userToRemove;
          openModal('remove-modal');
        });
      });
    });
  }

  document.getElementById('confirm-remove-btn').addEventListener('click', function() {
    if (!userToRemove) return;
    App.fetchJSON('./api/admin.php', { action: 'remove_user', username: userToRemove }).then(function(data) {
      closeModal('remove-modal');
      if (data.success) {
        App.showToast('User removed', 'success');
        loadUsers();
      } else {
        App.showToast(data.error || 'Failed to remove user', 'error');
      }
      userToRemove = '';
    });
  });

  /* ── Load activities ── */
  function renderActivities(activities) {
    allActivities = activities;
    var entries = Object.entries(activities);
    document.getElementById('act-count').textContent = entries.length + ' activities';
    var search = (document.getElementById('act-search').value || '').toLowerCase();

    var filtered = entries.filter(function(e) {
      return !search || e[0].toLowerCase().includes(search) || (e[1].category||'').toLowerCase().includes(search);
    });

    var tbody = document.getElementById('activities-table-body');
    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:1.5rem">No activities found</td></tr>';
      return;
    }

    tbody.innerHTML = '';
    filtered.sort(function(a,b) { return a[0].localeCompare(b[0]); }).forEach(function(entry) {
      var name = entry[0], info = entry[1];
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td><strong>' + App.escapeHtml(name) + '</strong></td>' +
        '<td style="color:var(--text-muted)">' + App.escapeHtml(info.unit || '') + '</td>' +
        '<td style="color:var(--accent)">' + info.factor + '</td>' +
        '<td><span class="badge badge-user">' + App.escapeHtml(info.category || '') + '</span></td>' +
        '<td><button class="btn btn-sm btn-danger remove-act-btn" data-name="' + App.escapeHtml(name) + '">✕</button></td>';
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll('.remove-act-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        actToRemove = this.dataset.name;
        document.getElementById('remove-act-name-display').textContent = actToRemove;
        openModal('remove-act-modal');
      });
    });
  }

  function loadActivities() {
    App.fetchJSON('./api/admin.php', { action: 'get_activities' }).then(function(data) {
      if (data.activities) renderActivities(data.activities);
    });
  }

  document.getElementById('act-search').addEventListener('input', function() {
    renderActivities(allActivities);
  });

  document.getElementById('confirm-remove-act-btn').addEventListener('click', function() {
    if (!actToRemove) return;
    App.fetchJSON('./api/admin.php', { action: 'remove_activity', name: actToRemove }).then(function(data) {
      closeModal('remove-act-modal');
      if (data.success) {
        App.showToast('Activity removed', 'success');
        loadActivities();
      } else {
        App.showToast(data.error || 'Failed to remove activity', 'error');
      }
      actToRemove = '';
    });
  });

  // Add activity
  document.getElementById('add-act-btn').addEventListener('click', function() {
    var name     = document.getElementById('new-act-name').value.trim();
    var unit     = document.getElementById('new-act-unit').value.trim();
    var factor   = parseFloat(document.getElementById('new-act-factor').value);
    var category = document.getElementById('new-act-category').value.trim() || 'Other';

    var errEl = document.getElementById('add-act-error');
    var sucEl = document.getElementById('add-act-success');
    errEl.classList.add('hidden');
    sucEl.classList.add('hidden');

    if (!name || !unit || !factor || factor <= 0) {
      errEl.textContent = 'Please fill all fields with valid values.';
      errEl.classList.remove('hidden');
      return;
    }

    App.fetchJSON('./api/admin.php', {
      action: 'add_activity', name: name, unit: unit, factor: factor, category: category
    }).then(function(data) {
      if (data.success) {
        sucEl.textContent = '"' + name + '" added successfully!';
        sucEl.classList.remove('hidden');
        document.getElementById('new-act-name').value     = '';
        document.getElementById('new-act-unit').value     = '';
        document.getElementById('new-act-factor').value   = '';
        document.getElementById('new-act-category').value = '';
        if (data.activities) renderActivities(data.activities);
        setTimeout(function() { sucEl.classList.add('hidden'); }, 4000);
      } else {
        errEl.textContent = data.error || 'Failed to add activity';
        errEl.classList.remove('hidden');
      }
    });
  });

  // Init
  App.initAuth({
    onLogin: function(data) {
      if (data.role !== 'admin') { window.location.href = 'dashboard.php'; return; }
      App.startLogoutTimer();
      loadUsers();
      loadActivities();
    }
  });
})();
</script>
</body>
</html>
