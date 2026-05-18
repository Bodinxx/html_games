<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LoreBinder</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&family=Roboto+Mono:wght@400;500;700&family=Unica+One&family=Audiowide&family=Michroma&family=Quantico:wght@400;700&family=Oswald:wght@400;500;700&family=Bebas+Neue&family=Great+Vibes&family=Cookie&family=Pattaya&family=Bungee&family=Chango&family=Architects+Daughter&family=Tangerine:wght@400;700&family=Amatic+SC:wght@400;700&display=swap" />
  <link rel="stylesheet" href="base.css" />
  <link rel="stylesheet" href="app.css" />
</head>
<body>
  <main class="layout">
    <aside class="sidebar" id="sidebar">
      <header class="section-header">
        <h1>LoreBinder</h1>
        <button id="toggle-sidebar-btn" type="button" aria-expanded="true" aria-controls="sidebar-content">⟨</button>
      </header>
      <div id="sidebar-content" class="sidebar-content">
        <div class="tree-tools">
          <button id="new-doc-btn" type="button">+ Doc</button>
          <button id="new-folder-btn" type="button">+ Folder</button>
          <button id="rename-node-btn" type="button">Rename</button>
          <button id="delete-node-btn" type="button" class="danger">Delete</button>
        </div>
        <ul id="file-tree" class="file-tree"></ul>

        <section class="assets-panel">
          <div class="section-header compact">
            <h2>Assets</h2>
            <label class="upload-button" for="asset-upload">Upload</label>
            <input id="asset-upload" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,.gif" hidden />
          </div>
          <ul id="asset-list" class="asset-list"></ul>
        </section>
      </div>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <label class="stack-field toolbar-field compact">
          <span>Projects</span>
          <select id="project-select" class="toolbar-select"></select>
        </label>
        <div class="project-actions">
          <button id="new-project-btn" type="button">+ Project</button>
          <button id="archive-project-btn" type="button">Archive</button>
          <button id="delete-project-btn" type="button" class="danger">Remove</button>
        </div>
        <details id="project-info-menu" class="toolbar-menu">
          <summary class="icon-button" aria-label="Project info" title="Project info">ⓘ</summary>
          <div class="toolbar-popover project-popover">
            <label class="stack-field toolbar-field">
              <span>Project Name</span>
              <input id="project-title" class="project-title" type="text" placeholder="Project title" />
            </label>
            <label class="stack-field toolbar-field">
              <span>Primary Theme</span>
              <select id="theme-preset" class="toolbar-select"></select>
            </label>
          </div>
        </details>
        <div class="toolbar-spacer"></div>
        <button id="compile-btn" type="button" class="icon-button" aria-label="Compile Book" title="Compile Book">⚙</button>
        <button id="validate-links-btn" type="button" class="icon-button" aria-label="Check Links" title="Check Links">🔗</button>
        <button id="help-btn" type="button" class="icon-button" aria-label="Help" title="Help">?</button>
        <details id="user-menu" class="toolbar-menu hidden">
          <summary id="current-user-label" class="current-user"></summary>
          <div class="toolbar-popover user-popover">
            <button id="profile-btn" type="button">Profile</button>
            <button id="admin-btn" type="button" class="hidden">Admin</button>
            <button id="logout-btn" type="button">Logout</button>
          </div>
        </details>
      </header>

      <nav id="doc-tabs" class="doc-tabs" aria-label="Open documents"></nav>
      <section id="editor-toolbar" class="editor-toolbar" aria-label="Editor tools"></section>

      <section id="editor-grid" class="editor-grid visual-mode">
        <section class="editor-column">
          <h2>Code</h2>
          <textarea id="code-editor" class="editor-pane" spellcheck="false"></textarea>
        </section>
        <section class="editor-column">
          <h2>Preview</h2>
          <article id="visual-editor" class="editor-pane rendered-preview" contenteditable="false"></article>
        </section>
      </section>

      <section class="compile-panel">
        <header class="section-header compact">
          <h2>Compilation Output</h2>
        </header>
        <div id="compile-output" class="compile-output"></div>
      </section>

      <footer class="status-bar" id="save-status">● Saved</footer>
    </section>
  </main>

  <section id="overlay" class="overlay hidden" aria-hidden="true">
    <div class="overlay-card" role="dialog" aria-modal="true" aria-labelledby="overlay-title">
      <header class="overlay-header">
        <h2 id="overlay-title">Overlay</h2>
        <button id="overlay-close-btn" type="button" aria-label="Close">×</button>
      </header>
      <div id="overlay-body" class="overlay-body"></div>
    </div>
  </section>

  <script src="app.js"></script>
</body>
</html>
