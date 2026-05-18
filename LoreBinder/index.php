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
    <aside class="sidebar">
      <header class="section-header">
        <h1>LoreBinder</h1>
        <button id="new-doc-btn" type="button">+ Doc</button>
      </header>
      <div class="tree-tools">
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
    </aside>

    <section class="workspace">
      <header class="topbar">
        <input id="project-title" class="project-title" type="text" placeholder="Project title" />
        <button id="compile-btn" type="button">Compile Book</button>
        <button id="validate-links-btn" type="button">Check Links</button>
        <button id="help-btn" type="button">Help</button>
        <span id="current-user-label" class="current-user hidden"></span>
        <button id="admin-btn" type="button" class="hidden">Admin</button>
        <button id="logout-btn" type="button" class="hidden">Logout</button>
      </header>

      <nav id="doc-tabs" class="doc-tabs" aria-label="Open documents"></nav>

      <div class="mode-tabs">
        <button id="mode-visual" type="button" class="active">Visual Layout</button>
        <button id="mode-code" type="button">Plain Text</button>
      </div>

      <section id="editor-grid" class="editor-grid">
        <article id="visual-editor" class="editor-pane" contenteditable="true"></article>
        <textarea id="code-editor" class="editor-pane" spellcheck="false"></textarea>
        <section class="preview-pane">
          <h2>Preview</h2>
          <div id="rendered-preview" class="rendered-preview"></div>
        </section>
      </section>

      <section class="css-panel">
        <header class="section-header compact">
          <h2>Project CSS Overrides</h2>
        </header>
        <textarea id="css-editor" spellcheck="false"></textarea>
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
