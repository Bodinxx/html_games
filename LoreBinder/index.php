<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LoreBinder</title>
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
          <label class="upload-button" for="asset-upload">Upload Asset</label>
          <input id="asset-upload" type="file" accept=".png,.jpg,.jpeg,.webp,.svg,.gif" hidden />
        </div>
        <ul id="file-tree" class="file-tree"></ul>
      </div>
    </aside>

    <section class="workspace">
      <header class="topbar">
        <select id="project-select" class="toolbar-select topbar-select" aria-label="Project"></select>
        <button id="new-project-btn" type="button" class="icon-button" aria-label="Add Project" title="Add Project">➕</button>
        <button id="archive-project-btn" type="button" class="icon-button" aria-label="Archive" title="Archive">📂</button>
        <button id="delete-project-btn" type="button" class="icon-button danger" aria-label="Remove" title="Remove">🚫</button>
        <button id="compile-btn" type="button" class="icon-button" aria-label="Compile Book" title="Compile Book">⚙️</button>
        <button id="download-pdf-btn" type="button" class="icon-button" aria-label="Download PDF" title="Download PDF">📄</button>
        <button id="validate-links-btn" type="button" class="icon-button" aria-label="Check Links" title="Check Links">🔗</button>
        <button id="help-btn" type="button" class="icon-button" aria-label="Help" title="Help">ℹ️</button>
        <details id="user-menu" class="toolbar-menu hidden">
          <summary id="current-user-label" class="current-user" aria-label="User menu">👤</summary>
          <div class="toolbar-popover user-popover">
            <button id="profile-btn" type="button">Profile</button>
            <button id="admin-btn" type="button" class="hidden">Admin</button>
            <button id="logout-btn" type="button">Logout</button>
          </div>
        </details>
        <div class="hidden-toolbar-fields" aria-hidden="true">
          <input id="project-title" class="project-title" type="text" />
          <select id="theme-preset" class="toolbar-select"></select>
        </div>
      </header>

      <nav id="workspace-tabs" class="workspace-tabs" aria-label="Workspace tabs">
        <button type="button" class="workspace-tab active" data-panel="editor">Editor / Preview</button>
        <button type="button" class="workspace-tab" data-panel="compile">Compilation Output</button>
      </nav>

      <section class="workspace-panels">
        <section id="editor-workspace" class="workspace-panel active">
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
        </section>

        <section id="compile-workspace" class="workspace-panel">
          <section class="compile-panel">
            <div id="compile-output" class="compile-output"></div>
          </section>
        </section>
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
