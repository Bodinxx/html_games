<?php
// ============================================================
// Dungeon Data JSON Editor
// ============================================================
session_start();

define('DATA_DIR',  __DIR__ . DIRECTORY_SEPARATOR);
define('CORE_FILES', ['doors.json','monsters.json','room_features.json',
                      'special_rooms.json','traps.json','treasures.json']);
define('EXCLUDED',   ['dungeon_data.json']);
// bcrypt hash of the admin password
define('ADMIN_HASH', '$2y$10$4kGlfNoJ8zkR2ksdVEqX8.5iskS/5tvVVFH.eRaQLbFG3yke6XkF6');

// ── AJAX handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $act = $_POST['action'];
    $ok  = !empty($_SESSION['de_auth']);

    if ($act !== 'login' && !$ok) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    switch ($act) {

        case 'login': {
            // Session-based brute-force throttle
            if (!isset($_SESSION['de_fails']))   $_SESSION['de_fails']   = 0;
            if (!isset($_SESSION['de_lockout'])) $_SESSION['de_lockout'] = 0;
            $now = time();
            if ($_SESSION['de_lockout'] > $now) {
                echo json_encode(['error' => 'Too many failed attempts. Try again shortly.']); break;
            }
            if (password_verify($_POST['pw'] ?? '', ADMIN_HASH)) {
                session_regenerate_id(true);
                $_SESSION['de_auth']    = true;
                $_SESSION['de_fails']   = 0;
                $_SESSION['de_lockout'] = 0;
                echo json_encode(['ok' => true]);
            } else {
                $_SESSION['de_fails']++;
                // Exponential back-off: 1 min after 5 fails, doubling to max 10 min
                if ($_SESSION['de_fails'] >= 5) {
                    $lockSeconds = min(600, 60 * pow(2, $_SESSION['de_fails'] - 5));
                    $_SESSION['de_lockout'] = $now + (int)$lockSeconds;
                }
                sleep(1);
                echo json_encode(['error' => 'Incorrect password']);
            }
            break;
        }

        case 'logout':
            session_destroy();
            echo json_encode(['ok' => true]);
            break;

        case 'list':
            $ff = array_filter(
                array_map('basename', glob(DATA_DIR . '*.json') ?: []),
                fn($f) => !in_array($f, EXCLUDED)
            );
            sort($ff);
            echo json_encode(['ok' => true, 'files' => array_values($ff), 'core' => CORE_FILES]);
            break;

        case 'load': {
            $f = safeFile($_POST['file'] ?? '');
            if (!$f || !file_exists(DATA_DIR . $f)) {
                echo json_encode(['error' => 'File not found']); break;
            }
            $raw = file_get_contents(DATA_DIR . $f);
            if ($raw === false) { echo json_encode(['error' => 'Failed to read file']); break; }
            $d = json_decode($raw, true);
            if ($d === null) { echo json_encode(['error' => 'Invalid JSON in file']); break; }
            echo json_encode(['ok' => true, 'data' => $d]);
            break;
        }

        case 'save': {
            $f = safeFile($_POST['file'] ?? '');
            if (!$f) { echo json_encode(['error' => 'Invalid filename']); break; }
            $c = $_POST['content'] ?? '';
            $d = json_decode($c, true);
            if ($d === null) {
                echo json_encode(['error' => 'Bad JSON: ' . json_last_error_msg()]); break;
            }
            $out = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $n = file_put_contents(DATA_DIR . $f, $out);
            echo $n !== false
                ? json_encode(['ok' => true, 'bytes' => $n])
                : json_encode(['error' => 'Write failed — check server permissions']);
            break;
        }

        case 'create': {
            $name = trim($_POST['name'] ?? '');
            if (substr($name, -5) !== '.json') $name .= '.json';
            $f = safeFile($name);
            if (!$f) {
                echo json_encode(['error' => 'Invalid name (letters, digits, hyphens, underscores only)']); break;
            }
            if (file_exists(DATA_DIR . $f)) { echo json_encode(['error' => 'File already exists']); break; }
            $written = file_put_contents(DATA_DIR . $f, '{}');
            if ($written === false) { echo json_encode(['error' => 'Failed to create file — check permissions']); break; }
            echo json_encode(['ok' => true, 'file' => $f]);
            break;
        }

        case 'delete': {
            $f = safeFile($_POST['file'] ?? '');
            if (!$f || in_array($f, CORE_FILES)) {
                echo json_encode(['error' => 'Cannot delete core files']); break;
            }
            if (!file_exists(DATA_DIR . $f)) { echo json_encode(['error' => 'File not found']); break; }
            if (!unlink(DATA_DIR . $f)) { echo json_encode(['error' => 'Failed to delete file']); break; }
            echo json_encode(['ok' => true]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
    }
    exit;
}

function safeFile(string $raw): string {
    $b = basename(trim($raw));
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.json$/', $b)) return '';
    if (in_array($b, EXCLUDED)) return '';
    // After basename(), $b contains no directory separators, so DATA_DIR . $b is safe.
    // For existing files, do a realpath check to guard against symlinks.
    $dir = realpath(DATA_DIR);
    if ($dir === false) return '';
    if (file_exists(DATA_DIR . $b)) {
        $rp = realpath(DATA_DIR . $b);
        if ($rp === false || strpos($rp, $dir . DIRECTORY_SEPARATOR) !== 0) return '';
    }
    return $b;
}

$authed = !empty($_SESSION['de_auth']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dungeon Data Editor</title>
<style>
:root {
  --bg:#1a1a2e; --panel:#16213e; --panel2:#0f3460;
  --accent:#e94560; --a2:#4a90d9;
  --text:#e0e0e0; --dim:#888; --border:#2a2a4a;
  --ibg:#0d1b2a; --cbg:#0d1b2a; --cbg2:#111828;
  --cb:#1e2a3a; --sel:rgba(74,144,217,.28); --hdr:#0a1624;
  --ok:#27ae60; --err:#e74c3c;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:var(--bg);color:var(--text);
     height:100vh;display:flex;flex-direction:column;overflow:hidden}

/* ── Login overlay ── */
#overlay{position:fixed;inset:0;background:rgba(0,0,0,.88);display:flex;
         align-items:center;justify-content:center;z-index:999}
#overlay.h{display:none}
.lbox{background:var(--panel2);border:2px solid var(--accent);border-radius:8px;
      padding:2rem;width:310px;text-align:center}
.lbox h2{color:var(--accent);letter-spacing:2px;margin-bottom:.3rem;font-size:1.1rem}
.lbox p{color:var(--dim);font-size:.78rem;margin-bottom:1.2rem}
.lbox input{width:100%;background:var(--ibg);border:1px solid var(--border);
            color:var(--text);padding:.55rem;border-radius:4px;font-size:.9rem;
            text-align:center;letter-spacing:3px;margin-bottom:.7rem}
.lbox input:focus{outline:1px solid var(--a2)}
.lerr{color:var(--err);font-size:.78rem;min-height:1.1em;margin-bottom:.5rem}

/* ── Header ── */
header{background:var(--panel2);padding:.55rem 1rem;display:flex;
       justify-content:space-between;align-items:center;
       border-bottom:2px solid var(--accent);flex-shrink:0}
header h1{font-size:1rem;color:var(--accent);letter-spacing:1px}

/* ── App layout ── */
.app{display:flex;flex:1;overflow:hidden;min-height:0}

/* ── Sidebar ── */
.sb{width:200px;min-width:200px;background:var(--panel);
    border-right:1px solid var(--border);display:flex;flex-direction:column;overflow:hidden}
.sb-hd{padding:.5rem .7rem;font-size:.62rem;text-transform:uppercase;letter-spacing:2px;
       color:var(--a2);border-bottom:1px solid var(--border);flex-shrink:0}
.fl{flex:1;overflow-y:auto;padding:.3rem}
.fi{padding:.4rem .6rem;border-radius:4px;cursor:pointer;font-size:.8rem;color:var(--dim);
    display:flex;align-items:center;gap:.3rem;transition:background .1s,color .1s}
.fi:hover{background:var(--panel2);color:var(--text)}
.fi.active{background:var(--accent);color:#fff}
.fi .fn{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fi .cbadge{font-size:.58rem;padding:1px 4px;border-radius:2px;flex-shrink:0;
            background:rgba(255,255,255,.08);color:var(--dim)}
.fi.active .cbadge{background:rgba(0,0,0,.2);color:rgba(255,255,255,.6)}
.fi .delbtn{color:var(--err);font-size:.85rem;visibility:hidden;flex-shrink:0;
            background:none;border:none;cursor:pointer;padding:1px 3px;line-height:1}
.fi:hover .delbtn{visibility:visible}
.fi.active .delbtn{visibility:hidden}
.sb-ft{padding:.4rem;border-top:1px solid var(--border);flex-shrink:0}

/* ── Main area ── */
.mn{flex:1;display:flex;flex-direction:column;overflow:hidden;min-height:0}

/* ── Tab bar ── */
.tabs{display:flex;background:var(--panel);border-bottom:1px solid var(--border);
      overflow-x:auto;flex-shrink:0;padding:0 .5rem;gap:2px;align-items:flex-end}
.tabs::-webkit-scrollbar{height:3px}
.tab{padding:.35rem .75rem;cursor:pointer;font-size:.76rem;color:var(--dim);
     border-top-left-radius:4px;border-top-right-radius:4px;
     border:1px solid transparent;border-bottom:none;white-space:nowrap;
     transition:all .1s;margin-top:.25rem}
.tab:hover{color:var(--text);background:var(--ibg)}
.tab.active{background:var(--ibg);color:var(--a2);border-color:var(--border)}

/* ── Toolbar ── */
.tb{padding:.35rem .8rem;display:flex;gap:.4rem;align-items:center;
    border-bottom:1px solid var(--border);background:var(--ibg);flex-shrink:0}
.tb-info{font-size:.7rem;color:var(--dim);flex:1}

/* ── Grid area ── */
.ga{flex:1;overflow:auto;position:relative;min-height:0}
.ge{display:flex;align-items:center;justify-content:center;height:100%;
    color:var(--dim);font-size:.9rem;text-align:center;padding:2rem;line-height:1.7}

/* ── Spreadsheet table ── */
.gt{border-collapse:collapse;min-width:100%;font-size:.81rem;table-layout:fixed}
.gt thead th{
  background:var(--hdr);color:var(--a2);font-size:.7rem;
  text-transform:uppercase;letter-spacing:.5px;
  padding:.38rem .55rem;border:1px solid var(--cb);
  position:sticky;top:0;z-index:5;
  white-space:nowrap;font-weight:600;user-select:none;overflow:hidden;
}
.gt thead th.th-rn{width:38px;min-width:38px;max-width:38px;
                   text-align:center;color:var(--dim);font-size:.62rem}
.gt tbody tr{background:var(--cbg)}
.gt tbody tr:nth-child(even){background:var(--cbg2)}
.gt tbody tr:hover{background:rgba(74,144,217,.07)}
.gt td{border:1px solid var(--cb);padding:0;vertical-align:top}
.gt td.td-rn{
  width:38px;min-width:38px;max-width:38px;
  text-align:center;color:var(--dim);font-size:.68rem;
  background:var(--hdr);position:sticky;left:0;z-index:2;
  user-select:none;vertical-align:middle;
}
.td-rn .rn{pointer-events:none;line-height:1}
.td-rn .rd{
  display:none;background:none;border:none;
  color:var(--err);cursor:pointer;font-size:.9rem;padding:2px 4px;
}
.gt tbody tr:hover .td-rn .rn{display:none}
.gt tbody tr:hover .td-rn .rd{display:inline}

/* ── Cell ── */
.ci{
  min-height:26px;padding:.3rem .5rem;outline:none;
  cursor:text;white-space:pre-wrap;word-break:break-word;
  line-height:1.45;display:block;
}
.ci:focus{background:var(--sel);box-shadow:inset 0 0 0 2px var(--a2)}
.ci.t-num{text-align:right;font-family:monospace;font-size:.79rem}
.ci.t-bool{text-align:center;font-size:.79rem}
.ci.t-arr{color:var(--a2);font-size:.75rem}
.ah{font-size:.6rem;color:var(--dim);font-style:italic;margin-left:3px;font-weight:400;text-transform:none;letter-spacing:0}

/* ── Status bar ── */
.stbar{padding:.28rem .8rem;background:var(--panel);border-top:1px solid var(--border);
       font-size:.7rem;color:var(--dim);display:flex;gap:.8rem;
       align-items:center;flex-shrink:0}
.stmsg{flex:1}
.stmsg.ok{color:var(--ok)}
.stmsg.err{color:var(--err)}

/* ── Buttons ── */
.btn{padding:.28rem .6rem;border:none;border-radius:4px;cursor:pointer;
     font-size:.76rem;font-weight:600;transition:filter .12s;white-space:nowrap}
.btn:hover:not(:disabled){filter:brightness(1.15)}
.btn:disabled{opacity:.45;cursor:default}
.btn-p{background:var(--accent);color:#fff}
.btn-s{background:var(--panel2);color:var(--text);border:1px solid var(--border)}
.btn-d{background:var(--err);color:#fff}

/* ── Modals ── */
.mo{position:fixed;inset:0;background:rgba(0,0,0,.72);
    display:flex;align-items:center;justify-content:center;z-index:600}
.mo.h{display:none}
.mb{background:var(--panel2);border:1px solid var(--border);border-radius:8px;
    padding:1.4rem;width:340px}
.mb h3{color:var(--accent);margin-bottom:.9rem;font-size:.95rem}
.mb label{display:block;font-size:.76rem;color:var(--dim);margin-bottom:.25rem}
.mb input,.mb select{
  width:100%;background:var(--ibg);border:1px solid var(--border);
  color:var(--text);padding:.45rem .55rem;border-radius:4px;
  font-size:.82rem;margin-bottom:.7rem
}
.mb input:focus,.mb select:focus{outline:1px solid var(--a2)}
.merr{color:var(--err);font-size:.76rem;min-height:1.1em;margin-bottom:.4rem}
.mac{display:flex;gap:.4rem;justify-content:flex-end}

/* ── Loading spinner ── */
#spin{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);
      z-index:800;align-items:center;justify-content:center}
#spin.s{display:flex}
.spr{width:34px;height:34px;border:3px solid var(--border);
     border-top-color:var(--accent);border-radius:50%;
     animation:rot .7s linear infinite}
@keyframes rot{to{transform:rotate(360deg)}}

/* ── Scrollbars ── */
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:var(--panel)}
::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
</style>
</head>
<body>

<!-- Loading spinner -->
<div id="spin"><div class="spr"></div></div>

<!-- Login overlay -->
<div id="overlay" class="<?= $authed ? 'h' : '' ?>">
  <div class="lbox">
    <h2>⚔ DUNGEON EDITOR</h2>
    <p>Enter admin password to access the data editor</p>
    <input type="password" id="lpw" placeholder="Password" autocomplete="current-password">
    <div class="lerr" id="lerr"></div>
    <button class="btn btn-p" style="width:100%;padding:.55rem" onclick="doLogin()">UNLOCK</button>
  </div>
</div>

<!-- New file modal -->
<div class="mo h" id="m-add">
  <div class="mb">
    <h3>➕ New JSON File</h3>
    <label>Filename (without .json extension)</label>
    <input type="text" id="m-add-name" placeholder="e.g. my_custom_data">
    <div class="merr" id="m-add-err"></div>
    <div class="mac">
      <button class="btn btn-s" onclick="closeModal('m-add')">Cancel</button>
      <button class="btn btn-p" onclick="doCreateFile()">Create</button>
    </div>
  </div>
</div>

<!-- Add column modal -->
<div class="mo h" id="m-col">
  <div class="mb">
    <h3>➕ Add Column</h3>
    <label>Column name (JSON key)</label>
    <input type="text" id="m-col-key" placeholder="field_name">
    <label>Type</label>
    <select id="m-col-type">
      <option value="string">String</option>
      <option value="number">Number</option>
      <option value="boolean">Boolean (true / false)</option>
      <option value="array">Array (one item per line)</option>
    </select>
    <div class="merr" id="m-col-err"></div>
    <div class="mac">
      <button class="btn btn-s" onclick="closeModal('m-col')">Cancel</button>
      <button class="btn btn-p" onclick="doAddColumn()">Add</button>
    </div>
  </div>
</div>

<!-- Add section modal -->
<div class="mo h" id="m-sec">
  <div class="mb">
    <h3>➕ Add Section</h3>
    <label>Section key name (becomes a tab)</label>
    <input type="text" id="m-sec-key" placeholder="e.g. custom_monsters">
    <div class="merr" id="m-sec-err"></div>
    <div class="mac">
      <button class="btn btn-s" onclick="closeModal('m-sec')">Cancel</button>
      <button class="btn btn-p" onclick="doAddSection()">Add</button>
    </div>
  </div>
</div>

<!-- Header -->
<header>
  <h1>⚔ DUNGEON DATA EDITOR</h1>
  <div style="display:flex;align-items:center;gap:.8rem">
    <span id="file-lbl" style="font-size:.76rem;color:var(--dim)">No file selected</span>
    <button class="btn btn-s" style="font-size:.72rem" onclick="doLogout()">Logout</button>
  </div>
</header>

<div class="app">

  <!-- Sidebar -->
  <div class="sb">
    <div class="sb-hd">Data Files</div>
    <div class="fl" id="file-list"></div>
    <div class="sb-ft">
      <button class="btn btn-s" style="width:100%;font-size:.74rem" onclick="openModal('m-add')">+ New File</button>
    </div>
  </div>

  <!-- Main -->
  <div class="mn">
    <div class="tabs" id="tabs"></div>

    <div class="tb">
      <span class="tb-info" id="tb-info">Select a file to begin editing</span>
      <button class="btn btn-s" id="btn-add-row" disabled onclick="addRow()">+ Row</button>
      <button class="btn btn-s" id="btn-add-col" disabled onclick="openModal('m-col')">+ Col</button>
      <button class="btn btn-s" id="btn-add-sec" disabled onclick="openModal('m-sec')">+ Section</button>
      <button class="btn btn-p" id="btn-save"    disabled onclick="doSave()">💾 Save</button>
    </div>

    <div class="ga" id="grid-area">
      <div class="ge">Select a file from the sidebar to begin editing</div>
    </div>

    <div class="stbar">
      <span class="stmsg" id="st-msg"></span>
      <span id="st-r" style="color:var(--dim);font-size:.68rem"></span>
    </div>
  </div>
</div>

<script>
// ── Init flag from PHP ───────────────────────────────────────
const INIT_AUTHED = <?= $authed ? 'true' : 'false' ?>;

// ── State ────────────────────────────────────────────────────
const S = {
  files: [], core: [],
  file: null, data: null,
  sections: [], section: null,
  schemas: {}, dirty: false
};

// ── API ──────────────────────────────────────────────────────
async function api(params) {
  const r = await fetch(window.location.pathname, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams(params)
  });
  return r.json();
}

// ── Auth ─────────────────────────────────────────────────────
async function doLogin() {
  const pw = document.getElementById('lpw').value;
  if (!pw) return;
  const r = await api({action: 'login', pw});
  if (r.ok) {
    document.getElementById('overlay').classList.add('h');
    init();
  } else {
    document.getElementById('lerr').textContent = r.error || 'Login failed';
  }
}

async function doLogout() {
  await api({action: 'logout'});
  location.reload();
}

document.getElementById('lpw').addEventListener('keydown', e => {
  if (e.key === 'Enter') doLogin();
});

// ── Bootstrap ────────────────────────────────────────────────
async function init() {
  spin(true);
  const r = await api({action: 'list'});
  spin(false);
  if (!r.ok) return;
  S.files = r.files;
  S.core  = r.core;
  renderFileList();
}

// ── File list render ─────────────────────────────────────────
function renderFileList() {
  const el = document.getElementById('file-list');
  el.innerHTML = '';
  S.files.forEach(f => {
    const isCore = S.core.includes(f);
    const d = document.createElement('div');
    d.className = 'fi' + (f === S.file ? ' active' : '');
    d.innerHTML =
      `<span class="fn">${esc(f)}</span>` +
      (isCore
        ? `<span class="cbadge">core</span>`
        : `<button class="delbtn" title="Delete file"
             onclick="event.stopPropagation();delFile('${esc(f)}')">×</button>`);
    d.addEventListener('click', () => loadFile(f));
    el.appendChild(d);
  });
}

// ── Load file ────────────────────────────────────────────────
async function loadFile(f) {
  if (S.dirty && S.file) {
    if (!confirm('Unsaved changes will be lost. Continue?')) return;
  }
  spin(true);
  const r = await api({action: 'load', file: f});
  spin(false);
  if (r.error) { st(r.error, 'err'); return; }

  S.file    = f;
  S.data    = r.data;
  S.dirty   = false;
  S.sections = Object.keys(r.data).filter(k => Array.isArray(r.data[k]));
  S.schemas  = {};
  S.sections.forEach(k => { S.schemas[k] = inferSchema(r.data[k]); });
  S.section  = S.sections[0] || null;

  document.getElementById('file-lbl').textContent = f;
  document.getElementById('btn-save').disabled    = false;
  document.getElementById('btn-add-sec').disabled = false;
  document.title = 'Editor — ' + f;

  renderFileList();
  renderTabs();
  renderGrid();
  st('Loaded ' + f, 'ok');
}

// ── Tabs ─────────────────────────────────────────────────────
function renderTabs() {
  const el = document.getElementById('tabs');
  el.innerHTML = '';
  S.sections.forEach(k => {
    const t = document.createElement('div');
    t.className = 'tab' + (k === S.section ? ' active' : '');
    t.textContent = k;
    t.addEventListener('click', () => switchSection(k));
    el.appendChild(t);
  });
  const nonArr = S.data
    ? Object.keys(S.data).filter(k => !Array.isArray(S.data[k]))
    : [];
  if (nonArr.length) {
    const note = document.createElement('span');
    note.style.cssText = 'font-size:.64rem;color:var(--dim);padding:.4rem;align-self:center;flex-shrink:0';
    note.textContent   = nonArr.length + ' non-tabular key(s) hidden';
    el.appendChild(note);
  }
  updateCtrls();
}

function switchSection(k) {
  if (k === S.section) return;
  flushSection();
  S.section = k;
  renderTabs();
  renderGrid();
}

function updateCtrls() {
  const has = !!S.section;
  document.getElementById('btn-add-row').disabled = !has;
  document.getElementById('btn-add-col').disabled = !has;
}

// ── Schema inference ─────────────────────────────────────────
function inferSchema(arr) {
  if (!arr || arr.length === 0) return [];
  // Array of primitives → single "value" column
  if (typeof arr[0] !== 'object' || arr[0] === null) {
    return [{ key: 'value', type: typeof arr[0], itemType: null }];
  }
  // Array of objects → collect all keys across all rows
  const keys = {}, keyOrder = [];
  arr.forEach(item => {
    if (item && typeof item === 'object' && !Array.isArray(item)) {
      Object.entries(item).forEach(([k, v]) => {
        if (!(k in keys)) {
          keys[k]  = Array.isArray(v) ? 'array' : typeof v;
          keyOrder.push(k);
        }
      });
    }
  });
  return keyOrder.map(k => ({
    key: k, type: keys[k],
    itemType: keys[k] === 'array' ? arrItemType(arr, k) : null
  }));
}

function arrItemType(arr, key) {
  for (const item of arr) {
    const v = item && item[key];
    if (Array.isArray(v) && v.length > 0) return typeof v[0] === 'number' ? 'number' : 'string';
  }
  return 'string';
}

// ── Column width hints ───────────────────────────────────────
const COL_W = {
  id:'90px', name:'170px', label:'165px', desc:'330px',
  effect:'300px', notes:'240px', value:'100%',
  dc:'62px', hp:'62px', ac:'62px', xp:'70px', cr:'58px',
  dmg:'130px', tags:'185px', spells:'210px',
  gp:'90px', items:'310px', spellcaster:'82px'
};
function colWidth(col) {
  return COL_W[col.key] || (col.type === 'string' ? '160px' : '100px');
}

// ── Render grid ──────────────────────────────────────────────
function renderGrid() {
  const ga     = document.getElementById('grid-area');
  const schema = S.section ? S.schemas[S.section] : null;
  const arr    = S.section ? (S.data[S.section] || []) : null;

  if (!S.section || !schema) {
    const empty = !S.data || Object.keys(S.data).length === 0;
    ga.innerHTML =
      `<div class="ge">${empty
        ? 'This file is empty.<br>Use <strong>+ Section</strong> to add a section (tab).'
        : 'No tabular sections found.<br>Use <strong>+ Section</strong> to add one.'
      }</div>`;
    updateTbInfo(0, 0);
    return;
  }

  ga.innerHTML = '';

  const table = document.createElement('table');
  table.className = 'gt';
  table.id = 'the-grid';

  // thead
  const thr = document.createElement('tr');
  const thRn = document.createElement('th');
  thRn.className = 'th-rn';
  thRn.textContent = '#';
  thr.appendChild(thRn);

  schema.forEach(col => {
    const th = document.createElement('th');
    th.style.width   = colWidth(col);
    th.style.minWidth = colWidth(col);
    const hint = col.type === 'array' ? '<span class="ah">[↵]</span>' : '';
    th.innerHTML = esc(col.key) + hint;
    th.title = `${col.key} (${col.type}${col.itemType ? '[]' : ''})`;
    thr.appendChild(th);
  });

  const thead = document.createElement('thead');
  thead.appendChild(thr);
  table.appendChild(thead);

  const tbody = document.createElement('tbody');
  arr.forEach((item, i) => tbody.appendChild(makeRow(item, i + 1, schema)));
  table.appendChild(tbody);

  ga.appendChild(table);
  updateTbInfo(arr.length, schema.length);
}

function makeRow(item, idx, schema) {
  const tr = document.createElement('tr');
  tr.className = 'data-row';

  // Row-number / delete cell
  const tdRn = document.createElement('td');
  tdRn.className = 'td-rn';
  tdRn.innerHTML =
    `<span class="rn">${idx}</span>` +
    `<button class="rd" title="Delete row" onclick="delRow(this.closest('tr'))">×</button>`;
  tr.appendChild(tdRn);

  schema.forEach(col => {
    const td  = document.createElement('td');
    const raw = (typeof item === 'object' && item !== null && !Array.isArray(item))
      ? (item[col.key] ?? '')
      : item;

    const div = document.createElement('div');
    div.contentEditable = 'true';
    div.spellcheck      = false;
    div.className = 'ci' +
      (col.type === 'number'  ? ' t-num'  : '') +
      (col.type === 'boolean' ? ' t-bool' : '') +
      (col.type === 'array'   ? ' t-arr'  : '');
    div.textContent = fmtVal(raw, col);

    div.addEventListener('blur',    () => markDirty());
    div.addEventListener('keydown', cellKeydown);
    td.appendChild(div);
    tr.appendChild(td);
  });

  return tr;
}

// ── Value format / parse ─────────────────────────────────────
function fmtVal(val, col) {
  if (val === null || val === undefined) return '';
  if (col.type === 'array') {
    if (!Array.isArray(val)) return String(val);
    return val.join('\n');
  }
  return String(val);
}

function parseVal(text, col) {
  text = (text || '').trim();
  if (col.type === 'number') return text === '' ? 0 : (isNaN(+text) ? 0 : +text);
  if (col.type === 'boolean') return text === 'true' || text === '1' || text === 'yes';
  if (col.type === 'array') {
    if (text === '') return [];
    const items = text.split('\n').map(s => s.trim()).filter(Boolean);
    return col.itemType === 'number' ? items.map(s => isNaN(+s) ? 0 : +s) : items;
  }
  return text;
}

// ── Flush current section to state.data ─────────────────────
function flushSection() {
  if (!S.section) return;
  const table = document.getElementById('the-grid');
  if (!table) return;
  const schema = S.schemas[S.section];
  const rows   = table.querySelectorAll('tbody tr.data-row');
  S.data[S.section] = Array.from(rows).map(tr => deserRow(tr, schema));
}

function deserRow(tr, schema) {
  const cells = tr.querySelectorAll('.ci');
  if (schema.length === 1 && schema[0].key === 'value') {
    return parseVal(cells[0] ? cells[0].textContent : '', schema[0]);
  }
  const obj = {};
  schema.forEach((col, i) => {
    if (cells[i]) obj[col.key] = parseVal(cells[i].textContent, col);
  });
  return obj;
}

// ── Add / delete row ─────────────────────────────────────────
function addRow() {
  if (!S.section) return;
  const schema = S.schemas[S.section];
  const tbody  = document.querySelector('#the-grid tbody');
  if (!tbody) return;

  let defItem;
  if (schema.length === 1 && schema[0].key === 'value') {
    defItem = schema[0].type === 'number' ? 0 : '';
  } else {
    defItem = {};
    schema.forEach(col => {
      defItem[col.key] = col.type === 'number'  ? 0
                       : col.type === 'boolean' ? false
                       : col.type === 'array'   ? []
                       : '';
    });
  }

  const rowNum = tbody.querySelectorAll('tr.data-row').length + 1;
  const tr     = makeRow(defItem, rowNum, schema);
  tbody.appendChild(tr);
  renum();
  updateTbInfo(tbody.querySelectorAll('tr.data-row').length, schema.length);
  markDirty();

  const first = tr.querySelector('.ci');
  if (first) { first.focus(); window.getSelection().selectAllChildren(first); }
}

function delRow(tr) {
  if (!confirm('Delete this row?')) return;
  tr.remove();
  renum();
  const schema = S.section ? S.schemas[S.section] : [];
  const tbody  = document.querySelector('#the-grid tbody');
  if (tbody) updateTbInfo(tbody.querySelectorAll('tr.data-row').length, schema.length);
  markDirty();
}

function renum() {
  document.querySelectorAll('#the-grid tbody tr.data-row').forEach((tr, i) => {
    const rn = tr.querySelector('.rn');
    if (rn) rn.textContent = i + 1;
  });
}

// ── Add column ───────────────────────────────────────────────
function doAddColumn() {
  const key    = document.getElementById('m-col-key').value.trim();
  const type   = document.getElementById('m-col-type').value;
  const errEl  = document.getElementById('m-col-err');
  errEl.textContent = '';

  if (!key || !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
    errEl.textContent = 'Invalid key name (start with letter or _, alphanumeric)'; return;
  }
  if (!S.section) { closeModal('m-col'); return; }
  const schema = S.schemas[S.section];
  if (schema.find(c => c.key === key)) { errEl.textContent = 'Column already exists'; return; }

  flushSection();
  schema.push({ key, type, itemType: type === 'array' ? 'string' : null });
  const defVal = type === 'number' ? 0 : type === 'boolean' ? false : type === 'array' ? [] : '';
  (S.data[S.section] || []).forEach(item => {
    if (item && typeof item === 'object' && !Array.isArray(item)) item[key] = defVal;
  });
  renderGrid();
  markDirty();
  closeModal('m-col');
  document.getElementById('m-col-key').value = '';
}

// ── Add section ──────────────────────────────────────────────
function doAddSection() {
  const key   = document.getElementById('m-sec-key').value.trim();
  const errEl = document.getElementById('m-sec-err');
  errEl.textContent = '';

  if (!key || !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
    errEl.textContent = 'Invalid key name'; return;
  }
  if (!S.data) { closeModal('m-sec'); return; }
  if (key in S.data) { errEl.textContent = 'Section already exists'; return; }

  S.data[key] = [];
  S.sections.push(key);
  S.schemas[key] = [];
  S.section = key;

  renderTabs();
  renderGrid();
  markDirty();
  closeModal('m-sec');
  document.getElementById('m-sec-key').value = '';
}

// ── Save ─────────────────────────────────────────────────────
async function doSave() {
  if (!S.file) return;
  flushSection();
  spin(true);
  const r = await api({ action: 'save', file: S.file, content: JSON.stringify(S.data) });
  spin(false);
  if (r.error) { st(r.error, 'err'); return; }
  S.dirty = false;
  document.title = 'Editor — ' + S.file;
  st('Saved ' + S.file + '  (' + r.bytes + ' bytes)', 'ok');
}

// ── Create / delete files ────────────────────────────────────
async function doCreateFile() {
  const name  = document.getElementById('m-add-name').value.trim();
  const errEl = document.getElementById('m-add-err');
  errEl.textContent = '';
  if (!name) { errEl.textContent = 'Enter a filename'; return; }

  spin(true);
  const r = await api({ action: 'create', name });
  spin(false);
  if (r.error) { errEl.textContent = r.error; return; }

  if (!S.files.includes(r.file)) { S.files.push(r.file); S.files.sort(); }
  renderFileList();
  closeModal('m-add');
  document.getElementById('m-add-name').value = '';
  st('Created ' + r.file, 'ok');
}

async function delFile(f) {
  if (!confirm('Permanently delete ' + f + '? This cannot be undone.')) return;
  spin(true);
  const r = await api({ action: 'delete', file: f });
  spin(false);
  if (r.error) { st(r.error, 'err'); return; }

  S.files = S.files.filter(x => x !== f);
  if (S.file === f) {
    S.file = null; S.data = null;
    S.sections = []; S.section = null; S.schemas = {};
    S.dirty = false;
    document.getElementById('file-lbl').textContent = 'No file selected';
    document.getElementById('tabs').innerHTML = '';
    document.getElementById('grid-area').innerHTML =
      '<div class="ge">Select a file from the sidebar to begin editing</div>';
    document.getElementById('btn-save').disabled    = true;
    document.getElementById('btn-add-sec').disabled = true;
    updateCtrls();
    document.title = 'Dungeon Data Editor';
  }
  renderFileList();
  st('Deleted ' + f, 'ok');
}

// ── Keyboard navigation ──────────────────────────────────────
function cellKeydown(e) {
  if (e.key === 'Tab') {
    e.preventDefault();
    navCell(e.target, e.shiftKey ? -1 : 1, 0);
    return;
  }
  if (e.key === 'Enter' && !e.shiftKey) {
    // Allow real newlines only in array-typed cells
    const td  = e.target.closest('td');
    const tds = Array.from(td.parentElement.children);
    const ci  = tds.indexOf(td) - 1;  // subtract row-num column
    const schema = S.section ? S.schemas[S.section] : [];
    if (ci >= 0 && schema[ci] && schema[ci].type === 'array') return;
    e.preventDefault();
    navCell(e.target, 0, 1);
  }
}

function navCell(cell, dx, dy) {
  const td    = cell.closest('td');
  const tr    = td.closest('tr');
  const tbody = tr.closest('tbody');
  if (!tbody) return;

  const rows = Array.from(tbody.querySelectorAll('tr.data-row'));
  const ri   = rows.indexOf(tr);
  const tds  = Array.from(tr.querySelectorAll('td')).filter(t => !t.classList.contains('td-rn'));
  const ci   = tds.indexOf(td);
  if (ri === -1 || ci === -1) return;

  let nri = ri, nci = ci + dx;
  if (dy !== 0) { nri = ri + dy; nci = ci; }

  // Wrap columns
  if (nci < 0) { nri--; nci = tds.length - 1; }
  if (nci >= tds.length) { nri++; nci = 0; }

  if (nri < 0 || nri >= rows.length) return;
  const targetCells = Array.from(rows[nri].querySelectorAll('td'))
    .filter(t => !t.classList.contains('td-rn'));
  const targetCell  = targetCells[nci];
  if (targetCell) {
    const div = targetCell.querySelector('.ci');
    if (div) { div.focus(); window.getSelection().selectAllChildren(div); }
  }
}

// ── Modal helpers ────────────────────────────────────────────
function openModal(id) {
  document.getElementById(id).classList.remove('h');
  const inp = document.querySelector(`#${id} input`);
  if (inp) setTimeout(() => inp.focus(), 50);
}
function closeModal(id) {
  document.getElementById(id).classList.add('h');
}
// Close modals on overlay click
document.querySelectorAll('.mo').forEach(mo => {
  mo.addEventListener('click', e => { if (e.target === mo) mo.classList.add('h'); });
});
// Close modals on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.mo:not(.h)').forEach(m => m.classList.add('h'));
});
// Enter key inside modals
['m-add','m-col','m-sec'].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
      e.preventDefault();
      if (id === 'm-add') doCreateFile();
      else if (id === 'm-col') doAddColumn();
      else if (id === 'm-sec') doAddSection();
    }
  });
});

// ── UI helpers ───────────────────────────────────────────────
function spin(show) {
  document.getElementById('spin').className = show ? 's' : '';
}

function st(msg, type) {
  const el = document.getElementById('st-msg');
  el.textContent  = msg;
  el.className    = 'stmsg' + (type ? ' ' + type : '');
  if (type === 'ok') setTimeout(() => { if (el.textContent === msg) el.textContent = ''; }, 4000);
}

function markDirty() {
  if (!S.dirty) {
    S.dirty = true;
    document.title = '* Editor — ' + (S.file || '');
  }
}

function updateTbInfo(rows, cols) {
  document.getElementById('tb-info').textContent =
    S.section ? `${S.section}  ·  ${rows} row${rows !== 1 ? 's' : ''}  ·  ${cols} column${cols !== 1 ? 's' : ''}` : '';
  document.getElementById('st-r').textContent =
    S.file ? S.file : '';
}

function esc(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Auto-start if already authenticated ─────────────────────
if (INIT_AUTHED) init();
</script>
</body>
</html>
