<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$username = htmlspecialchars($_SESSION['user']);
$role     = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile &amp; Settings – Activity Tracker</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body data-theme="dark">

<!-- Navbar -->
<nav class="navbar">
  <span class="navbar-brand">🏃 Activity Tracker</span>
  <button class="navbar-toggle" id="navbar-toggle" aria-expanded="false" aria-label="Toggle menu">☰</button>
  <ul class="navbar-nav">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="profile.php" class="active">Profile</a></li>
    <?php if ($role === 'admin'): ?>
    <li><a href="admin.php">Admin</a></li>
    <?php endif; ?>
  </ul>
  <div class="navbar-right">
    <span class="navbar-user">👤 <strong id="navbar-username"><?= $username ?></strong></span>
    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
  </div>
</nav>

<div class="page-container">
  <div class="page-header">
    <h1>Profile &amp; Settings</h1>
    <p>Manage your personal information, goals, and preferences</p>
  </div>

  <div class="profile-grid">

    <!-- Personal Info -->
    <div class="card">
      <div class="card-title"><span class="icon">👤</span> Personal Information</div>
      <div id="profile-success" class="alert alert-success hidden"></div>
      <div id="profile-error"   class="alert alert-error   hidden"></div>

      <div class="form-group">
        <label for="p-fullname">Full Name</label>
        <input type="text" id="p-fullname" class="form-control" placeholder="Your full name">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="p-weight">Weight</label>
          <div style="display:flex;gap:.5rem">
            <input type="number" id="p-weight" class="form-control" placeholder="e.g. 75" min="20" max="500" step="0.1">
            <select id="p-weight-unit" class="form-control" style="flex:0 0 70px">
              <option value="kg">kg</option>
              <option value="lbs">lbs</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="p-height">Height</label>
          <div id="height-input-cm" style="display:flex;gap:.5rem">
            <input type="number" id="p-height" class="form-control" placeholder="e.g. 175" min="100" max="250">
            <select id="p-height-unit" class="form-control" style="flex:0 0 70px">
              <option value="cm">cm</option>
              <option value="ft">ft/in</option>
            </select>
          </div>
          <div id="height-input-ft" style="display:none;gap:.5rem">
            <input type="number" id="p-height-ft" class="form-control" placeholder="Feet" min="3" max="8" step="1" style="flex:0 0 60px">
            <input type="number" id="p-height-in" class="form-control" placeholder="Inches" min="0" max="11" step="1" style="flex:0 0 70px">
            <select class="form-control" style="flex:0 0 70px;pointer-events:none;background:#555;color:#aaa">
              <option>ft/in</option>
            </select>
          </div>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="p-age">Age</label>
          <input type="number" id="p-age" class="form-control" placeholder="e.g. 30" min="10" max="120">
        </div>
        <div class="form-group">
          <label for="p-gender">Gender</label>
          <select id="p-gender" class="form-control">
            <option value="m">Male</option>
            <option value="f">Female</option>
            <option value="o">Other</option>
          </select>
        </div>
      </div>
      <button class="btn btn-primary" id="save-profile-btn">Save Profile</button>
    </div>

    <!-- BMR Display -->
    <div class="card">
      <div class="card-title"><span class="icon">🔥</span> BMR &amp; Calorie Targets</div>
      <div class="bmr-display">
        <div class="bmr-value" id="bmr-value">—</div>
        <div class="bmr-label">Basal Metabolic Rate (kcal/day)</div>
      </div>
      <div class="bmr-grid mt-2">
        <div class="bmr-item">
          <div class="val" id="bmr-meal-thresh">—</div>
          <div class="lbl">Max kcal/meal (BMR ÷ 3)<br><small style="font-size:.7rem;color:var(--text-muted)">Clean meal threshold</small></div>
        </div>
        <div class="bmr-item">
          <div class="val" id="bmr-active">—</div>
          <div class="lbl">Active TDEE estimate<br><small style="font-size:.7rem;color:var(--text-muted)">BMR × 1.55</small></div>
        </div>
      </div>
      <p class="form-hint mt-2">These values update automatically when you save your profile.</p>
    </div>

    <!-- Goals -->
    <div class="card">
      <div class="card-title"><span class="icon">🎯</span> Weekly Goals</div>
      <div id="goals-success" class="alert alert-success hidden"></div>
      <div id="goals-error"   class="alert alert-error   hidden"></div>

      <div class="form-row">
        <div class="form-group">
          <label for="g-steps">Daily Steps Goal</label>
          <input type="number" id="g-steps" class="form-control" placeholder="e.g. 6000" min="0">
        </div>
        <div class="form-group">
          <label for="g-workout">Workout Hours/Week</label>
          <input type="number" id="g-workout" class="form-control" placeholder="e.g. 5" min="0" step="0.5">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="g-sleep">Sleep Goal (hrs/night)</label>
          <input type="number" id="g-sleep" class="form-control" placeholder="e.g. 7" min="0" max="24" step="0.5">
        </div>
        <div class="form-group">
          <label for="g-meals">Clean Meals/Week
            <span class="tooltip-icon" title="A clean meal is whole foods with nutritional value, under the kcal/meal threshold shown in BMR section. Examples: grilled chicken with vegetables, salads with lean protein, whole grain bowls.">?</span>
          </label>
          <input type="number" id="g-meals" class="form-control" placeholder="e.g. 14" min="0">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="g-activity">Weekly Activity Points Goal</label>
          <input type="number" id="g-activity" class="form-control" placeholder="e.g. 300" min="0" step="1">
        </div>
        <div class="form-group" style="align-self:flex-end;margin-bottom:0;">
          <p class="form-hint" style="margin:0;font-size:.85rem;color:var(--text-muted)">This target is used to measure your activity points progress.</p>
        </div>
      </div>
      <button class="btn btn-primary" id="save-goals-btn">Save Goals</button>
    </div>

    <!-- Theme -->
    <div class="card">
      <div class="card-title"><span class="icon">🎨</span> Theme</div>
      <div id="theme-success" class="alert alert-success hidden"></div>

      <div class="theme-grid" id="theme-grid">
        <?php
        $themes = [
          ['dark',        'Dark',        'swatch-dark'],
          ['light',       'Light',       'swatch-light'],
          ['ocean',       'Ocean',       'swatch-ocean'],
          ['dark blue',   'Dark Blue',   'swatch-darkblue'],
          ['light blue',  'Light Blue',  'swatch-lightblue'],
          ['dark green',  'Dark Green',  'swatch-darkgreen'],
          ['light green', 'Light Green', 'swatch-lightgreen'],
          ['dark red',    'Dark Red',    'swatch-darkred'],
          ['light red',   'Light Red',   'swatch-lightred'],
          ['industrial',  'Industrial',  'swatch-industrial'],
        ];
        foreach ($themes as $t): ?>
        <label class="theme-option">
          <input type="radio" name="theme" value="<?= htmlspecialchars($t[0]) ?>">
          <div class="theme-swatch <?= $t[2] ?>"></div>
          <span class="theme-label"><?= $t[1] ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card">
      <div class="card-title"><span class="icon">🔒</span> Change Password</div>
      <div id="pw-success" class="alert alert-success hidden"></div>
      <div id="pw-error"   class="alert alert-error   hidden"></div>

      <div class="form-group">
        <label for="pw-current">Current Password</label>
        <input type="password" id="pw-current" class="form-control" placeholder="Enter current password" autocomplete="current-password">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="pw-new">New Password</label>
          <input type="password" id="pw-new" class="form-control" placeholder="Min 6 characters" autocomplete="new-password">
        </div>
        <div class="form-group">
          <label for="pw-confirm">Confirm New</label>
          <input type="password" id="pw-confirm" class="form-control" placeholder="Repeat new password" autocomplete="new-password">
        </div>
      </div>
      <button class="btn btn-primary" id="change-pw-btn">Update Password</button>
    </div>

  </div><!-- /profile-grid -->
</div><!-- /page-container -->

<div id="toast-container"></div>
<script src="js/app.js"></script>
<script>
(function() {
  var profile = {};
  var goals   = {};

  function updateBMR() {
    var w = parseFloat(document.getElementById('p-weight').value) || 0;
    var h = parseFloat(document.getElementById('p-height').value) || 0;
    var a = parseFloat(document.getElementById('p-age').value)    || 0;
    var g = document.getElementById('p-gender').value;
    if (!w || !h || !a) return;

    // BMR calculation: Revised Harris-Benedict (1984)
    var bmr = (g === 'f')
      ? 447.593 + (9.247 * w) + (3.098 * h) - (4.330 * a)
      : 88.362  + (13.397 * w) + (4.799 * h) - (5.677 * a);

    bmr = Math.round(bmr);
    document.getElementById('bmr-value').textContent      = bmr.toLocaleString();
    document.getElementById('bmr-meal-thresh').textContent = Math.round(bmr / 3).toLocaleString() + ' kcal';
    document.getElementById('bmr-active').textContent      = Math.round(bmr * 1.55).toLocaleString() + ' kcal';
  }

  // Unit conversion functions
  function getWeightInKg() {
    var val = parseFloat(document.getElementById('p-weight').value) || 0;
    var unit = document.getElementById('p-weight-unit').value;
    if (unit === 'lbs') {
      return val / 2.20462; // Convert lbs to kg
    }
    return val; // Already in kg
  }

  function getHeightInCm() {
    var unit = document.getElementById('p-height-unit').value;
    if (unit === 'ft') {
      var ft = parseFloat(document.getElementById('p-height-ft').value) || 0;
      var in_ = parseFloat(document.getElementById('p-height-in').value) || 0;
      return (ft * 30.48) + (in_ * 2.54); // Convert ft/in to cm
    }
    return parseFloat(document.getElementById('p-height').value) || 0; // Already in cm
  }

  // Handle height unit change
  document.getElementById('p-height-unit').addEventListener('change', function() {
    var cmDiv = document.getElementById('height-input-cm');
    var ftDiv = document.getElementById('height-input-ft');
    if (this.value === 'ft') {
      var cm = parseFloat(document.getElementById('p-height').value) || 0;
      var ft = Math.floor(cm / 30.48);
      var inches = Math.round(((cm / 30.48) - ft) * 12);
      document.getElementById('p-height-ft').value = ft;
      document.getElementById('p-height-in').value = inches;
      cmDiv.style.display = 'none';
      ftDiv.style.display = 'flex';
    } else {
      var ft = parseFloat(document.getElementById('p-height-ft').value) || 0;
      var in_ = parseFloat(document.getElementById('p-height-in').value) || 0;
      var cm = (ft * 30.48) + (in_ * 2.54);
      document.getElementById('p-height').value = Math.round(cm * 10) / 10;
      cmDiv.style.display = 'flex';
      ftDiv.style.display = 'none';
    }
    updateBMR();
  });

  // Handle weight unit change
  document.getElementById('p-weight-unit').addEventListener('change', function() {
    var val = parseFloat(document.getElementById('p-weight').value) || 0;
    if (this.value === 'lbs') {
      document.getElementById('p-weight').value = Math.round(val * 2.20462 * 10) / 10;
    } else {
      document.getElementById('p-weight').value = Math.round(val / 2.20462 * 10) / 10;
    }
    updateBMR();
  });

  ['p-weight','p-height','p-age','p-gender','p-height-ft','p-height-in'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', updateBMR);
      el.addEventListener('change', updateBMR);
    }
  });

  function showAlert(id, msg, type) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.className = 'alert alert-' + (type || 'success');
    setTimeout(function() { el.classList.add('hidden'); }, 4000);
  }

  // Load profile/goals on init
  App.initAuth({
    onLogin: function(data) {
      App.startLogoutTimer();
      profile = data.profile || {};
      goals   = data.goals   || {};

      document.getElementById('p-fullname').value = profile.full_name || '';
      document.getElementById('p-weight').value   = profile.weight    || '';
      document.getElementById('p-height').value   = profile.height    || '';
      document.getElementById('p-age').value      = profile.age       || '';
      if (profile.gender) document.getElementById('p-gender').value = profile.gender;

      // Set units to kg and cm by default
      document.getElementById('p-weight-unit').value = 'kg';
      document.getElementById('p-height-unit').value = 'cm';

      document.getElementById('g-steps').value   = goals.avg_steps            || '';
      document.getElementById('g-workout').value = goals.workout_hours       || '';
      document.getElementById('g-sleep').value   = goals.sleep_goal          || '';
      document.getElementById('g-meals').value   = goals.clean_meals_goal    || '';
      document.getElementById('g-activity').value= goals.activity_points_goal || '';

      // Set theme radio
      var currentTheme = data.theme || 'dark';
      var radios = document.querySelectorAll('input[name="theme"]');
      radios.forEach(function(r) { r.checked = (r.value === currentTheme); });

      updateBMR();
    }
  });

  // Save profile
  document.getElementById('save-profile-btn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    App.fetchJSON('./api/user.php', {
      action:    'update_profile',
      full_name: document.getElementById('p-fullname').value.trim(),
      weight:    Math.round(getWeightInKg() * 100) / 100,
      height:    Math.round(getHeightInCm() * 10) / 10,
      age:       parseInt(document.getElementById('p-age').value, 10) || 0,
      gender:    document.getElementById('p-gender').value,
    }).then(function(data) {
      if (data.success) {
        showAlert('profile-success', 'Profile saved!', 'success');
        updateBMR();
      } else {
        showAlert('profile-error', data.error || 'Failed to save profile', 'error');
      }
    }).finally(function() { btn.disabled = false; });
  });

  // Save goals
  document.getElementById('save-goals-btn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    App.fetchJSON('./api/user.php', {
      action:            'update_goals',
      avg_steps:         parseFloat(document.getElementById('g-steps').value)     || 0,
      workout_hours:     parseFloat(document.getElementById('g-workout').value)   || 0,
      sleep_goal:        parseFloat(document.getElementById('g-sleep').value)     || 0,
      clean_meals_goal:  parseFloat(document.getElementById('g-meals').value)     || 0,
      activity_points_goal: parseFloat(document.getElementById('g-activity').value) || 0,
    }).then(function(data) {
      if (data.success) {
        showAlert('goals-success', 'Goals saved!', 'success');
      } else {
        showAlert('goals-error', data.error || 'Failed to save goals', 'error');
      }
    }).finally(function() { btn.disabled = false; });
  });

  // Theme change (live preview)
  document.querySelectorAll('input[name="theme"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
      var theme = this.value;
      App.applyTheme(theme);
      try { localStorage.setItem('apt_theme', theme); } catch(_) {}
      App.fetchJSON('./api/user.php', { action: 'update_theme', theme: theme })
        .then(function(data) {
          if (data.success) {
            showAlert('theme-success', 'Theme saved!', 'success');
          }
        });
    });
  });

  // Change password
  document.getElementById('change-pw-btn').addEventListener('click', function() {
    var btn      = this;
    var oldPass  = document.getElementById('pw-current').value;
    var newPass  = document.getElementById('pw-new').value;
    var confirm  = document.getElementById('pw-confirm').value;

    if (!oldPass) { showAlert('pw-error', 'Current password is required.', 'error'); return; }
    if (newPass.length < 6) { showAlert('pw-error', 'New password must be at least 6 characters.', 'error'); return; }
    if (newPass !== confirm) { showAlert('pw-error', 'New passwords do not match.', 'error'); return; }

    btn.disabled = true;
    App.fetchJSON('./api/user.php', {
      action:       'change_password',
      old_password: oldPass,
      new_password: newPass,
    }).then(function(data) {
      if (data.success) {
        showAlert('pw-success', 'Password changed successfully!', 'success');
        document.getElementById('pw-current').value = '';
        document.getElementById('pw-new').value     = '';
        document.getElementById('pw-confirm').value = '';
      } else {
        showAlert('pw-error', data.error || 'Failed to change password', 'error');
      }
    }).finally(function() { btn.disabled = false; });
  });
})();
</script>
</body>
</html>
