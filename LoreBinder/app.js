const COMMON_GAMES = [
  'Dungeons & Dragons',
  'Pathfinder',
  'Call of Cthulhu',
  'Cyberpunk RED',
  'Star Trek Adventures',
  'Star Wars RPG',
  'Shadowrun',
  'Vampire: The Masquerade',
];

const INTERFACE_THEME_STYLES = {
  midnight: {
    '--app-bg': '#131419',
    '--app-panel': '#1a1d29',
    '--app-panel-alt': '#161d31',
    '--app-surface': '#121829',
    '--app-border': '#33415f',
    '--app-text': '#eff2ff',
    '--app-text-soft': '#aebce0',
    '--app-accent': '#5f7cc4',
    '--app-accent-strong': '#7b97dd',
    '--app-danger': '#5a2631',
    '--app-danger-border': '#8e4452',
  },
  forest: {
    '--app-bg': '#121711',
    '--app-panel': '#182319',
    '--app-panel-alt': '#1d2b20',
    '--app-surface': '#111a13',
    '--app-border': '#35553c',
    '--app-text': '#edf6ef',
    '--app-text-soft': '#adc4b2',
    '--app-accent': '#5da46d',
    '--app-accent-strong': '#7dc48b',
    '--app-danger': '#5e2f2d',
    '--app-danger-border': '#93524e',
  },
  ember: {
    '--app-bg': '#1a1411',
    '--app-panel': '#241a16',
    '--app-panel-alt': '#2d211b',
    '--app-surface': '#18110d',
    '--app-border': '#5a4335',
    '--app-text': '#fff0e8',
    '--app-text-soft': '#d3b8aa',
    '--app-accent': '#d97e43',
    '--app-accent-strong': '#ef9b66',
    '--app-danger': '#6a2c22',
    '--app-danger-border': '#a15444',
  },
};

const BASE_CSS_FILE_NAME = 'base.css';
const MAX_URL_DECODE_PASSES = 3;
const GOOGLE_FONT_NAMES = [
  'Open Sans',
  'Roboto',
  'Roboto Mono',
  'Unica One',
  'Audiowide',
  'Michroma',
  'Quantico',
  'Oswald',
  'Bebas Neue',
  'Great Vibes',
  'Cookie',
  'Pattaya',
  'Bungee',
  'Chango',
  'Architects Daughter',
  'Tangerine',
  'Amatic SC',
];

const state = {
  project: null,
  projects: [],
  assets: [],
  selectedNodeId: null,
  mode: 'visual',
  saveTimer: null,
  isDirty: false,
  authUser: null,
  helpHtml: '',
  overlayClosable: true,
  themePresets: [],
  interfaceThemes: {},
  fontSyncTimer: null,
  loadedGoogleFonts: new Set(),
};

const ui = {
  layout: document.querySelector('.layout'),
  toggleSidebarBtn: document.getElementById('toggle-sidebar-btn'),
  fileTree: document.getElementById('file-tree'),
  assetList: document.getElementById('asset-list'),
  assetUpload: document.getElementById('asset-upload'),
  projectSelect: document.getElementById('project-select'),
  newProjectBtn: document.getElementById('new-project-btn'),
  archiveProjectBtn: document.getElementById('archive-project-btn'),
  deleteProjectBtn: document.getElementById('delete-project-btn'),
  projectInfoMenu: document.getElementById('project-info-menu'),
  themePreset: document.getElementById('theme-preset'),
  projectTitle: document.getElementById('project-title'),
  docTabs: document.getElementById('doc-tabs'),
  editorToolbar: document.getElementById('editor-toolbar'),
  editorGrid: document.getElementById('editor-grid'),
  visualEditor: document.getElementById('visual-editor'),
  codeEditor: document.getElementById('code-editor'),
  compileOutput: document.getElementById('compile-output'),
  saveStatus: document.getElementById('save-status'),
  compileBtn: document.getElementById('compile-btn'),
  validateLinksBtn: document.getElementById('validate-links-btn'),
  newDocBtn: document.getElementById('new-doc-btn'),
  newFolderBtn: document.getElementById('new-folder-btn'),
  renameNodeBtn: document.getElementById('rename-node-btn'),
  deleteNodeBtn: document.getElementById('delete-node-btn'),
  helpBtn: document.getElementById('help-btn'),
  userMenu: document.getElementById('user-menu'),
  currentUserLabel: document.getElementById('current-user-label'),
  profileBtn: document.getElementById('profile-btn'),
  adminBtn: document.getElementById('admin-btn'),
  logoutBtn: document.getElementById('logout-btn'),
  overlay: document.getElementById('overlay'),
  overlayTitle: document.getElementById('overlay-title'),
  overlayBody: document.getElementById('overlay-body'),
  overlayCloseBtn: document.getElementById('overlay-close-btn'),
};

bootstrap().catch((error) => {
  console.error(error);
  ui.compileOutput.textContent = `Failed to load LoreBinder state: ${error.message}`;
});

async function bootstrap() {
  bindEvents();
  renderEditorToolbar();
  await refreshAuthState();

  const params = new URLSearchParams(window.location.search);
  const resetToken = params.get('reset');
  const sharedProfile = params.get('profile');

  if (resetToken) {
    setWorkspaceEnabled(false);
    showPasswordResetOverlay(resetToken);
    return;
  }

  if (!state.authUser) {
    setWorkspaceEnabled(false);
    if (sharedProfile) {
      await showPublicProfileOverlay(sharedProfile, false);
      return;
    }
    showAuthOverlay();
    return;
  }

  await loadProjectState();

  if (sharedProfile) {
    await showPublicProfileOverlay(sharedProfile, true);
  }
}

function bindEvents() {
  ui.projectTitle.addEventListener('input', () => {
    if (!state.project) {
      return;
    }
    state.project.title = ui.projectTitle.value;
    markDirty();
  });

  ui.projectSelect.addEventListener('change', async () => {
    const nextProjectId = ui.projectSelect.value;
    if (!nextProjectId || nextProjectId === state.project?.id) {
      return;
    }
    await switchProject(nextProjectId);
  });

  ui.newProjectBtn.addEventListener('click', () => showCreateProjectOverlay());
  ui.archiveProjectBtn.addEventListener('click', () => toggleArchiveProject());
  ui.deleteProjectBtn.addEventListener('click', () => removeCurrentProject());
  ui.toggleSidebarBtn?.addEventListener('click', () => toggleSidebar());

  ui.themePreset.addEventListener('change', () => {
    if (!state.project) {
      return;
    }

    const selectedPreset = getThemePreset(ui.themePreset.value);
    if (!selectedPreset || selectedPreset.key === state.project.themeKey) {
      return;
    }

    const currentPreset = getThemePreset(state.project.themeKey);
    const currentCss = (state.project.styleCss || '').trim();
    const defaultCss = (currentPreset?.css || '').trim();
    const hasCustomCss = currentCss !== '' && currentCss !== defaultCss;
    const shouldReplace = !hasCustomCss || window.confirm('Applying a new primary theme replaces the current project CSS overrides. Continue?');
    if (!shouldReplace) {
      ui.themePreset.value = state.project.themeKey || '';
      return;
    }

    state.project.themeKey = selectedPreset.key;
    state.project.styleCss = selectedPreset.css;
    const styleDoc = getProjectStyleDocument(state.project);
    if (styleDoc) {
      styleDoc.content = state.project.styleCss;
      styleDoc.updatedAt = new Date().toISOString();
    }
    renderTree();
    syncEditorFromActiveDocument();
    applyProjectCss();
    markDirty();
  });

  ui.codeEditor.addEventListener('input', () => {
    updateActiveDocument(ui.codeEditor.value);
    renderActiveDocumentPreview();
  });

  ui.compileBtn.addEventListener('click', () => {
    if (!state.project) {
      return;
    }
    const compiled = compileProject();
    ui.compileOutput.textContent = compiled.output;
  });

  ui.validateLinksBtn.addEventListener('click', () => {
    if (!state.project) {
      return;
    }
    const validation = validateProjectLinks();
    ui.compileOutput.textContent = validation.length
      ? validation.map((line) => `⚠ ${line}`).join('\n')
      : '✓ All internal links resolved.';
  });

  ui.newDocBtn.addEventListener('click', () => createNode('document'));
  ui.newFolderBtn.addEventListener('click', () => createNode('folder'));
  ui.renameNodeBtn.addEventListener('click', renameSelectedNode);
  ui.deleteNodeBtn.addEventListener('click', deleteSelectedNode);

  ui.assetUpload.addEventListener('change', uploadAsset);
  ui.helpBtn.addEventListener('click', () => {
    showHelpOverlay().catch((error) => {
      console.error(error);
      alert(error.message || 'Unable to open help.');
    });
  });

  ui.profileBtn.addEventListener('click', () => {
    showProfileOverlay().catch((error) => {
      console.error(error);
      alert(error.message || 'Unable to open profile settings.');
    });
  });

  ui.adminBtn.addEventListener('click', () => {
    showAdminOverlay().catch((error) => {
      console.error(error);
      alert(error.message || 'Unable to open admin panel.');
    });
  });

  ui.logoutBtn.addEventListener('click', () => {
    logout().catch((error) => {
      console.error(error);
      alert(error.message || 'Logout failed.');
    });
  });

  ui.overlayCloseBtn.addEventListener('click', () => {
    if (!state.overlayClosable) {
      return;
    }
    closeOverlay();
  });

  ui.overlay.addEventListener('click', (event) => {
    if (!state.overlayClosable) {
      return;
    }
    if (event.target === ui.overlay) {
      closeOverlay();
    }
  });
}

function renderEditorToolbar() {
  if (!ui.editorToolbar) {
    return;
  }

  ui.editorToolbar.innerHTML = '';
  editorMenuDefinitions().forEach((menu) => {
    const details = document.createElement('details');
    details.className = 'toolbar-menu';

    const summary = document.createElement('summary');
    summary.textContent = menu.label;
    details.appendChild(summary);

    const popover = document.createElement('div');
    popover.className = 'toolbar-popover';

    if (menu.hint) {
      const hint = document.createElement('div');
      hint.className = 'menu-hint';
      hint.textContent = menu.hint;
      popover.appendChild(hint);
    }

    menu.items.forEach((item) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = item.label;
      button.addEventListener('click', () => {
        closeToolbarMenus();
        item.action();
      });
      popover.appendChild(button);
    });

    details.appendChild(popover);
    ui.editorToolbar.appendChild(details);
  });
}

function editorMenuDefinitions() {
  return [
    {
      label: 'EDITOR',
      hint: 'Layout and cross-link helpers.',
      items: [
        { label: 'Column count', action: insertColumnLayoutSnippet },
        { label: 'Column Break', action: () => insertSnippet('::: column-break\n\n:::\n') },
        { label: 'New Page Break', action: () => insertSnippet('::: page-break\n\n:::\n') },
        { label: 'Vertical Spacing', action: () => insertSnippet('::: v-space\n\n:::\n') },
        { label: 'Horizontal Spacing', action: () => insertSnippet('`     `') },
        { label: 'A Wide Block', action: () => insertSnippet('::: wide-block\nAdd wide content here.\n:::\n', 'Add wide content here.') },
        { label: 'Link to Header', action: () => insertSnippet('[Jump to Header](#header-slug)') },
        { label: 'Link to Page', action: () => insertSnippet('[Jump to Page](doc:document-id)') },
        { label: 'Link to Page + Header', action: () => insertSnippet('[Jump to Section](doc:document-id#header-slug)') },
      ],
    },
    {
      label: 'FONTS',
      hint: 'Google Fonts snippets plus a custom family loader.',
      items: [
        ...GOOGLE_FONT_NAMES.map((fontName) => ({
          label: fontName,
          action: () => insertFontSnippet(fontName),
        })),
        { label: 'Custom Google Font…', action: promptForCustomFontSnippet },
      ],
    },
    {
      label: 'IMAGES',
      hint: 'Insert asset links or image scaffolds.',
      items: [
        { label: 'Image', action: showImageInsertOverlay },
        { label: 'Image Wrap Left', action: () => insertSnippet('::: wrap-left\n![Alt text](https://example.com/image.webp)\n:::\n', 'https://example.com/image.webp') },
        { label: 'Image Wrap Right', action: () => insertSnippet('::: wrap-right\n![Alt text](https://example.com/image.webp)\n:::\n', 'https://example.com/image.webp') },
        { label: 'Background Image', action: () => insertSnippet('::: wide-block\nBackground image URL: https://example.com/background.webp\nAdd overlay content here.\n:::\n', 'https://example.com/background.webp') },
        { label: 'Watermark', action: () => insertSnippet('::: note-block\nWatermark image URL: https://example.com/watermark.webp\nAdd page content here.\n:::\n', 'https://example.com/watermark.webp') },
      ],
    },
    {
      label: 'TABLES',
      hint: 'Sample markdown and styled block snippets.',
      items: [
        { label: 'Table', action: () => insertSnippet(buildSampleTable(3, 3)) },
        { label: 'Wide Table', action: () => insertSnippet(`::: wide-block\n${buildSampleTable(3, 3)}:::\n`) },
        { label: 'Split Table', action: () => insertSnippet(buildSplitTableSnippet()) },
        { label: 'Martial Class Table', action: () => insertSnippet(buildClassTableSnippet('Martial Class Table')) },
        { label: 'Martial Class Table (Unframed)', action: () => insertSnippet(buildClassTableSnippet('Martial Class Table', true)) },
        { label: 'Full Caster Class Table', action: () => insertSnippet(buildCasterTableSnippet('Full Caster Class Table')) },
        { label: 'Full Caster Class Table (Unframed)', action: () => insertSnippet(buildCasterTableSnippet('Full Caster Class Table', true)) },
        { label: 'Half Caster Class Table', action: () => insertSnippet(buildCasterTableSnippet('Half Caster Class Table')) },
        { label: 'Half Caster Class Table (Unframed)', action: () => insertSnippet(buildCasterTableSnippet('Half Caster Class Table', true)) },
        { label: 'Spell', action: () => insertSnippet(buildBlockSnippet('spell-block', 'Spell Name\n*1st-level evocation*\n\nDescribe the spell here.')) },
        { label: 'Class Feature', action: () => insertSnippet(buildBlockSnippet('class-feature-block', 'Class Feature\nDescribe the feature here.')) },
        { label: 'Quote', action: () => insertSnippet(buildBlockSnippet('quote-block', '"Quoted lore or dramatic text."')) },
        { label: 'Note', action: () => insertSnippet(buildBlockSnippet('note-block', 'Useful GM or player note.')) },
        { label: 'Descriptive Text Box', action: () => insertSnippet(buildBlockSnippet('description-block', 'Boxed descriptive text for the scene.')) },
        { label: 'Opponent Stat Block', action: () => insertSnippet(buildBlockSnippet('monster-stat-block', '**Armor Class** 15\n**Hit Points** 45 (6d8 + 18)\n**Speed** 30 ft.')) },
        { label: 'Opponent Stat Block (Unframed)', action: () => insertSnippet(buildBlockSnippet('monster-stat-block unframed-block', '**Armor Class** 15\n**Hit Points** 45 (6d8 + 18)\n**Speed** 30 ft.')) },
        { label: 'Opponent Stat Block Wide', action: () => insertSnippet(buildBlockSnippet('monster-stat-block wide-block', '**Armor Class** 15\n**Hit Points** 45 (6d8 + 18)\n**Speed** 30 ft.')) },
        { label: 'Front Cover Page', action: () => insertSnippet(buildBlockSnippet('cover-page', '# Front Cover\nSubtitle or tagline\n\nAuthor Name')) },
        { label: 'Inside Cover Page', action: () => insertSnippet(buildBlockSnippet('inside-cover-page', '# Inside Cover\nCredits, legal copy, or quick summary.')) },
        { label: 'Item', action: () => insertSnippet(buildBlockSnippet('item-block', '## Item Name\n*Wondrous item, rare*\n\nDescribe the item here.')) },
      ],
    },
  ];
}

function closeToolbarMenus() {
  document.querySelectorAll('.toolbar-menu[open]').forEach((menu) => {
    menu.open = false;
  });
}

function insertSnippet(snippet, selectionText = '') {
  if (!state.project || !getActiveDocument()) {
    return;
  }

  const editor = ui.codeEditor;
  const start = editor.selectionStart ?? editor.value.length;
  const end = editor.selectionEnd ?? start;
  editor.setRangeText(snippet, start, end, 'end');

  if (selectionText) {
    const selectStart = editor.value.indexOf(selectionText, start);
    if (selectStart >= 0) {
      editor.focus();
      editor.setSelectionRange(selectStart, selectStart + selectionText.length);
    }
  } else {
    editor.focus();
    const cursor = start + snippet.length;
    editor.setSelectionRange(cursor, cursor);
  }

  editor.dispatchEvent(new Event('input', { bubbles: true }));
}

function insertColumnLayoutSnippet() {
  const columnCount = prompt('Column count', '2');
  if (!columnCount) {
    return;
  }
  if (!/^[1-4]$/.test(columnCount)) {
    alert('Column count must be a number from 1 to 4.');
    return;
  }
  insertSnippet(`::: columns-${columnCount}\nAdd column content here.\n:::\n`, 'Add column content here.');
}

function insertFontSnippet(fontName) {
  ensureGoogleFontLink([fontName]);
  insertSnippet(`{{font-inline,font-family: ${fontName}\nReplace this text\n}}\n`, 'Replace this text');
}

function promptForCustomFontSnippet() {
  const fontName = prompt('Google font family name', 'Roboto');
  if (!fontName) {
    return;
  }
  if (!/^[a-z0-9][a-z0-9\s-]{0,79}$/i.test(fontName.trim())) {
    alert('Enter a valid Google Font family name using letters, numbers, spaces, or hyphens.');
    return;
  }
  ensureGoogleFontLink([fontName]);
  insertFontSnippet(fontName.trim());
}

function showImageInsertOverlay() {
  const assetButtons = state.assets.length
    ? state.assets.map((asset) => `<button type="button" class="insert-asset-btn" data-src="${escapeHtml(getAssetUrl(asset))}" data-alt="${escapeHtml(generateAltTextFromFilename(asset.name))}">${escapeHtml(asset.name)}</button>`).join('')
    : '<p class="empty-state">No uploaded assets for this project yet.</p>';

  openOverlay(
    'Insert Image',
    `
      <div class="overlay-block">
        <h3>Project Assets</h3>
        <div class="overlay-actions">${assetButtons}</div>
      </div>
      <form id="insert-image-url-form" class="overlay-block">
        <h3>Image URL</h3>
        <label for="insert-image-url">Image URL</label>
        <input id="insert-image-url" name="url" type="url" placeholder="https://example.com/image.webp" />
        <label for="insert-image-alt">Alt text</label>
        <input id="insert-image-alt" name="alt" placeholder="Image description" />
        <div class="overlay-actions">
          <button type="submit">Insert Image</button>
        </div>
      </form>
    `,
    true,
  );

  document.querySelectorAll('.insert-asset-btn').forEach((button) => {
    button.addEventListener('click', () => {
      insertSnippet(`![${button.dataset.alt || 'Image'}](${button.dataset.src || ''})`);
      closeOverlay();
    });
  });

  document.getElementById('insert-image-url-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const url = String(formData.get('url') || '').trim();
    const alt = String(formData.get('alt') || 'Image').trim() || 'Image';
    if (!url) {
      return;
    }
    insertSnippet(`![${alt}](${url})`);
    closeOverlay();
  });
}

function buildSampleTable(columns, rows) {
  const headers = Array.from({ length: columns }, (_, index) => `Header ${index + 1}`);
  const separator = Array.from({ length: columns }, () => '---');
  const body = Array.from({ length: rows }, (_, rowIndex) => Array.from({ length: columns }, (_, columnIndex) => `Row ${rowIndex + 1}.${columnIndex + 1}`));
  return [
    `| ${headers.join(' | ')} |`,
    `| ${separator.join(' | ')} |`,
    ...body.map((row) => `| ${row.join(' | ')} |`),
    '',
  ].join('\n');
}

function buildSplitTableSnippet() {
  return `::: split-columns\n${buildSampleTable(2, 3)}\n${buildSampleTable(2, 3)}:::\n`;
}

function buildClassTableSnippet(title, unframed = false) {
  const table = [
    `### ${title}`,
    '| Level | Proficiency Bonus | Features | Notes |',
    '| --- | --- | --- | --- |',
    '| 1 | +2 | Feature One | Sample benefit |',
    '| 2 | +2 | Feature Two | Sample benefit |',
    '| 3 | +2 | Feature Three | Sample benefit |',
  ].join('\n');
  return unframed ? `${table}\n\n` : `::: wide-block\n${table}\n:::\n`;
}

function buildCasterTableSnippet(title, unframed = false) {
  const table = [
    `### ${title}`,
    '| Level | Cantrips | 1st | 2nd | 3rd | Features |',
    '| --- | --- | --- | --- | --- | --- |',
    '| 1 | 2 | 2 | — | — | Feature One |',
    '| 2 | 2 | 3 | — | — | Feature Two |',
    '| 3 | 2 | 4 | 2 | — | Feature Three |',
  ].join('\n');
  return unframed ? `${table}\n\n` : `::: wide-block\n${table}\n:::\n`;
}

function buildBlockSnippet(className, body) {
  return `::: ${className}\n${body}\n:::\n`;
}

function getAssetUrl(asset) {
  return asset?.url || `storage/assets/${encodeURIComponent(state.project?.id || '')}/${encodeURIComponent(asset?.name || '')}`;
}

function isProjectStyleDocument(doc) {
  return String(doc?.name || '').trim().toLowerCase() === BASE_CSS_FILE_NAME;
}

async function refreshAuthState() {
  try {
    const payload = await apiRequest('auth_state', {}, false);
    state.authUser = payload.user || null;
    state.themePresets = payload.themePresets || [];
    state.interfaceThemes = payload.interfaceThemes || {};
  } catch (error) {
    state.authUser = null;
  }
  renderAuthControls();
  applyInterfacePreferences();
}

async function loadProjectState() {
  const payload = await apiRequest('state');
  applyStatePayload(payload);
  state.selectedNodeId = null;
  renderAll();
  setWorkspaceEnabled(Boolean(state.project));
}

function applyStatePayload(payload) {
  state.project = payload.project || null;
  state.projects = payload.projects || [];
  state.assets = payload.assets || [];
  state.authUser = payload.user || state.authUser;
  state.themePresets = payload.themePresets || state.themePresets;
  state.interfaceThemes = payload.interfaceThemes || state.interfaceThemes;
  if (state.project) {
    ensureProjectStyleDocument(state.project);
  }
  renderAuthControls();
  applyInterfacePreferences();
}

function setWorkspaceEnabled(enabled) {
  const controls = [
    ui.fileTree,
    ui.assetUpload,
    ui.projectSelect,
    ui.newProjectBtn,
    ui.archiveProjectBtn,
    ui.deleteProjectBtn,
    ui.themePreset,
    ui.projectTitle,
    ui.docTabs,
    ui.visualEditor,
    ui.codeEditor,
    ui.compileBtn,
    ui.validateLinksBtn,
    ui.newDocBtn,
    ui.newFolderBtn,
    ui.renameNodeBtn,
    ui.deleteNodeBtn,
  ];

  controls.forEach((node) => {
    if (!node) {
      return;
    }
    if ('disabled' in node) {
      node.disabled = !enabled;
    }
  });

  ui.visualEditor.contentEditable = 'false';
  ui.codeEditor.readOnly = !enabled;

  if (!enabled && !state.authUser) {
    ui.saveStatus.textContent = 'Login required.';
  } else if (!enabled) {
    ui.saveStatus.textContent = 'Create a project to begin.';
  }
}

function renderAuthControls() {
  if (!state.authUser) {
    ui.userMenu.classList.add('hidden');
    ui.currentUserLabel.textContent = '';
    ui.profileBtn.classList.add('hidden');
    ui.adminBtn.classList.add('hidden');
    ui.logoutBtn.classList.add('hidden');
    return;
  }

  const displayName = state.authUser.realName ? `${state.authUser.username} • ${state.authUser.realName}` : state.authUser.username;
  ui.userMenu.classList.remove('hidden');
  ui.currentUserLabel.textContent = `${displayName} (${state.authUser.role}) ▾`;
  ui.profileBtn.classList.remove('hidden');
  ui.logoutBtn.classList.remove('hidden');
  ui.adminBtn.classList.toggle('hidden', state.authUser.role !== 'admin');
}

function renderAll() {
  renderProjectControls();
  syncGoogleFontsFromProject();
  if (!state.project) {
    clearWorkspace();
    return;
  }

  ui.projectTitle.value = state.project.title || '';
  applyProjectCss();
  renderTree();
  renderDocTabs();
  renderAssets();
  syncEditorFromActiveDocument();
  ui.saveStatus.textContent = state.isDirty ? '○ Editing...' : '● Saved';
}

function clearWorkspace() {
  ui.projectTitle.value = '';
  ui.fileTree.innerHTML = '<li class="empty-state">No project selected.</li>';
  ui.docTabs.innerHTML = '';
  ui.assetList.innerHTML = '<li class="empty-state">No project assets yet.</li>';
  ui.codeEditor.value = '';
  ui.visualEditor.innerHTML = '';
  ui.compileOutput.textContent = 'Create or switch to a project to compile.';
  const styleTag = document.getElementById('project-style-overrides');
  if (styleTag) {
    styleTag.textContent = '';
  }
}

function renderProjectControls() {
  ui.projectSelect.innerHTML = '';
  const activeProjects = state.projects.filter((project) => project.status !== 'archived');
  const archivedProjects = state.projects.filter((project) => project.status === 'archived');

  const appendProjectOptions = (label, projects) => {
    if (!projects.length) {
      return;
    }
    const group = document.createElement('optgroup');
    group.label = label;
    projects.forEach((project) => {
      const option = document.createElement('option');
      option.value = project.id;
      option.textContent = `${project.title} • ${themeLabel(project.themeKey)}`;
      group.appendChild(option);
    });
    ui.projectSelect.appendChild(group);
  };

  appendProjectOptions('Active Projects', activeProjects);
  appendProjectOptions('Archived Projects', archivedProjects);

  if (state.project) {
    ui.projectSelect.value = state.project.id;
    ui.archiveProjectBtn.textContent = state.project.status === 'archived' ? 'Restore' : 'Archive';
    ui.themePreset.innerHTML = state.themePresets
      .map((preset) => `<option value="${escapeHtml(preset.key)}">${escapeHtml(preset.label)}</option>`)
      .join('');
    ui.themePreset.value = state.project.themeKey || '';
  } else {
    ui.archiveProjectBtn.textContent = 'Archive';
    ui.themePreset.innerHTML = state.themePresets
      .map((preset) => `<option value="${escapeHtml(preset.key)}">${escapeHtml(preset.label)}</option>`)
      .join('');
  }
}

function themeLabel(key) {
  return getThemePreset(key)?.label || key || 'Theme';
}

function getThemePreset(key) {
  return state.themePresets.find((preset) => preset.key === key) || null;
}

function createNode(type) {
  if (!state.project) {
    return;
  }

  const name = prompt(type === 'folder' ? 'Folder name?' : 'Document name?', type === 'folder' ? 'New Folder' : 'New Document.md');
  if (!name) {
    return;
  }

  const node = {
    id: `node-${crypto.randomUUID()}`,
    type,
    name,
    includeInCompile: true,
  };

  if (type === 'folder') {
    node.children = [];
  }

  if (type === 'document') {
    const docId = `doc-${crypto.randomUUID()}`;
    node.docId = docId;
    state.project.documents[docId] = {
      id: docId,
      name,
      content: '# New Document\n',
      updatedAt: new Date().toISOString(),
    };
    openDocument(docId);
  }

  const parent = findNodeAndParent(state.project.tree, state.selectedNodeId)?.node;
  if (parent && parent.type === 'folder') {
    parent.children.push(node);
  } else {
    state.project.tree.push(node);
  }

  state.selectedNodeId = node.id;
  renderTree();
  renderDocTabs();
  syncEditorFromActiveDocument();
  markDirty();
}

function renameSelectedNode() {
  if (!state.project) {
    return;
  }

  const hit = findNodeAndParent(state.project.tree, state.selectedNodeId);
  if (!hit?.node) {
    return;
  }

  const renamed = prompt('Rename item', hit.node.name);
  if (!renamed) {
    return;
  }

  hit.node.name = renamed;
  if (hit.node.type === 'document' && hit.node.docId && state.project.documents[hit.node.docId]) {
    state.project.documents[hit.node.docId].name = renamed;
  }

  renderTree();
  renderDocTabs();
  markDirty();
}

function deleteSelectedNode() {
  if (!state.project) {
    return;
  }

  const hit = findNodeAndParent(state.project.tree, state.selectedNodeId);
  if (!hit || !confirm(`Delete ${hit.node.name}?`)) {
    return;
  }

  hit.container.splice(hit.index, 1);
  if (hit.node.type === 'document' && hit.node.docId) {
    delete state.project.documents[hit.node.docId];
    state.project.openTabs = state.project.openTabs.filter((id) => id !== hit.node.docId);
    if (state.project.activeDocumentId === hit.node.docId) {
      state.project.activeDocumentId = state.project.openTabs[0] || '';
    }
  }

  state.selectedNodeId = null;
  renderTree();
  renderDocTabs();
  syncEditorFromActiveDocument();
  markDirty();
}

function renderTree() {
  ui.fileTree.innerHTML = '';
  if (!state.project?.tree?.length) {
    ui.fileTree.innerHTML = '<li class="empty-state">No documents yet.</li>';
    return;
  }

  state.project.tree.forEach((node) => {
    ui.fileTree.appendChild(renderTreeNode(node));
  });
}

function renderTreeNode(node) {
  const li = document.createElement('li');
  li.draggable = true;
  li.dataset.nodeId = node.id;

  const row = document.createElement('div');
  row.className = 'tree-node';
  if (state.selectedNodeId === node.id) {
    row.classList.add('selected');
  }

  const icon = document.createElement('span');
  icon.textContent = node.type === 'folder' ? '📁' : '📄';

  const name = document.createElement('span');
  name.className = 'name';
  name.textContent = node.name;

  const include = document.createElement('input');
  include.type = 'checkbox';
  include.checked = node.includeInCompile !== false;
  include.title = 'Include in compilation';
  include.addEventListener('click', (event) => {
    event.stopPropagation();
    node.includeInCompile = include.checked;
    markDirty();
  });

  row.append(icon, name, include);

  row.addEventListener('click', () => {
    state.selectedNodeId = node.id;
    renderTree();
  });

  if (node.type === 'document' && node.docId) {
    row.addEventListener('dblclick', () => {
      openDocument(node.docId);
    });
  }

  li.addEventListener('dragstart', (event) => {
    event.dataTransfer?.setData('text/plain', node.id);
  });

  li.addEventListener('dragover', (event) => event.preventDefault());
  li.addEventListener('drop', (event) => {
    event.preventDefault();
    const draggedId = event.dataTransfer?.getData('text/plain');
    if (!draggedId || draggedId === node.id) {
      return;
    }
    moveNode(draggedId, node.id);
  });

  li.appendChild(row);

  if (node.type === 'folder') {
    const children = document.createElement('ul');
    (node.children || []).forEach((child) => children.appendChild(renderTreeNode(child)));
    li.appendChild(children);
  }

  return li;
}

function moveNode(draggedId, targetId) {
  const dragged = findNodeAndParent(state.project.tree, draggedId);
  const target = findNodeAndParent(state.project.tree, targetId);
  if (!dragged || !target) {
    return;
  }
  if (dragged.node.type === 'folder' && containsNodeId(dragged.node, targetId)) {
    return;
  }

  dragged.container.splice(dragged.index, 1);
  if (target.node.type === 'folder') {
    target.node.children = target.node.children || [];
    target.node.children.push(dragged.node);
  } else {
    target.container.splice(target.index + 1, 0, dragged.node);
  }

  renderTree();
  markDirty();
}

function renderDocTabs() {
  ui.docTabs.innerHTML = '';
  if (!state.project) {
    return;
  }

  state.project.openTabs.forEach((docId) => {
    const doc = state.project.documents[docId];
    if (!doc) {
      return;
    }

    const tab = document.createElement('div');
    tab.className = 'doc-tab';
    if (state.project.activeDocumentId === docId) {
      tab.classList.add('active');
    }

    const openButton = document.createElement('button');
    openButton.type = 'button';
    openButton.textContent = doc.name || docId;
    openButton.addEventListener('click', () => {
      state.project.activeDocumentId = docId;
      renderDocTabs();
      syncEditorFromActiveDocument();
      markDirty();
    });

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.textContent = '×';
    closeButton.addEventListener('click', (event) => {
      event.stopPropagation();
      closeTab(docId);
    });

    tab.append(openButton, closeButton);
    ui.docTabs.appendChild(tab);
  });
}

function openDocument(docId) {
  if (!state.project.openTabs.includes(docId)) {
    state.project.openTabs.push(docId);
  }
  state.project.activeDocumentId = docId;
  renderDocTabs();
  syncEditorFromActiveDocument();
  markDirty();
}

function closeTab(docId) {
  state.project.openTabs = state.project.openTabs.filter((id) => id !== docId);
  if (state.project.activeDocumentId === docId) {
    state.project.activeDocumentId = state.project.openTabs[0] || '';
  }
  renderDocTabs();
  syncEditorFromActiveDocument();
  markDirty();
}

function updateActiveDocument(content) {
  const doc = getActiveDocument();
  if (!doc || !state.project) {
    return;
  }

  doc.content = content;
  doc.updatedAt = new Date().toISOString();
  if (isProjectStyleDocument(doc)) {
    state.project.styleCss = content;
    applyProjectCss();
  }
  scheduleGoogleFontSync();
  markDirty();
}

function syncEditorFromActiveDocument() {
  if (state.project) {
    ensureProjectStyleDocument(state.project);
  }
  const doc = getActiveDocument();
  const content = doc?.content || '';
  ui.codeEditor.value = content;
  renderActiveDocumentPreview();
}

function renderActiveDocumentPreview() {
  const doc = getActiveDocument();
  if (!doc) {
    ui.visualEditor.innerHTML = '<p class="empty-state">Select a document to preview.</p>';
    return;
  }

  if (isProjectStyleDocument(doc)) {
    ui.visualEditor.innerHTML = `<pre class="preview-source">${escapeHtml(doc.content || '')}</pre>`;
    return;
  }

  const rendered = renderMarkdown(doc.content || '');
  ui.visualEditor.innerHTML = rendered.html || '<p class="empty-state">Nothing to preview yet.</p>';
}

function getActiveDocument() {
  const docId = state.project?.activeDocumentId;
  if (!docId) {
    return null;
  }
  return state.project.documents[docId] || null;
}

function containsNodeId(rootNode, targetId) {
  if (!rootNode) {
    return false;
  }
  if (rootNode.id === targetId) {
    return true;
  }
  if (rootNode.type !== 'folder' || !Array.isArray(rootNode.children)) {
    return false;
  }
  return rootNode.children.some((child) => containsNodeId(child, targetId));
}

function findNodeAndParent(nodes, targetId, container = null) {
  if (!targetId || !Array.isArray(nodes)) {
    return null;
  }

  for (let index = 0; index < nodes.length; index += 1) {
    const node = nodes[index];
    if (node.id === targetId) {
      return { node, container: container || nodes, index };
    }
    if (node.type === 'folder' && Array.isArray(node.children)) {
      const found = findNodeAndParent(node.children, targetId, node.children);
      if (found) {
        return found;
      }
    }
  }

  return null;
}

function getProjectStyleDocument(project) {
  if (!project?.documents) {
    return null;
  }

  return Object.values(project.documents)
    .filter((doc) => doc && typeof doc === 'object')
    .find((doc) => String(doc.name || '').trim().toLowerCase() === BASE_CSS_FILE_NAME) || null;
}

function hasDocNode(nodes, docId) {
  return (nodes || []).some((node) => {
    if (node.type === 'document' && node.docId === docId) {
      return true;
    }
    if (node.type === 'folder') {
      return hasDocNode(node.children || [], docId);
    }
    return false;
  });
}

function ensureProjectStyleDocument(project) {
  if (!project || !project.documents) {
    return null;
  }

  let styleDoc = getProjectStyleDocument(project);
  if (!styleDoc) {
    const docId = `doc-${crypto.randomUUID()}`;
    const now = new Date().toISOString();
    styleDoc = {
      id: docId,
      name: BASE_CSS_FILE_NAME,
      content: project.styleCss || '',
      updatedAt: now,
    };
    project.documents[docId] = styleDoc;
  }
  if (!styleDoc.content && project.styleCss) {
    styleDoc.content = project.styleCss;
  }
  project.styleCss = styleDoc.content || '';
  if (!hasDocNode(project.tree || [], styleDoc.id)) {
    project.tree = project.tree || [];
    project.tree.unshift({
      id: `node-${crypto.randomUUID()}`,
      type: 'document',
      name: styleDoc.name,
      includeInCompile: false,
      docId: styleDoc.id,
    });
  }

  return styleDoc;
}

function toggleSidebar() {
  if (!ui.layout || !ui.toggleSidebarBtn) {
    return;
  }
  const collapsed = ui.layout.classList.toggle('sidebar-collapsed');
  ui.toggleSidebarBtn.textContent = collapsed ? '⟩' : '⟨';
  ui.toggleSidebarBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
}

function compileProject() {
  const chunks = [];
  orderedIncludedDocuments().forEach((doc) => {
    const rendered = renderMarkdown(doc.content || '');
    chunks.push(`# ${doc.name}\n\n${stripHtml(rendered.html)}`);
  });

  return {
    output: chunks.join('\n\n-----\n\n') || 'No included documents to compile.',
  };
}

function orderedIncludedDocuments() {
  const list = [];

  const walk = (nodes, includeParent = true) => {
    (nodes || []).forEach((node) => {
      const includeCurrent = includeParent && node.includeInCompile !== false;
      if (!includeCurrent) {
        return;
      }
      if (node.type === 'document' && node.docId) {
        const doc = state.project.documents[node.docId];
        if (doc) {
          list.push(doc);
        }
      }
      if (node.type === 'folder') {
        walk(node.children || [], includeCurrent);
      }
    });
  };

  walk(state.project?.tree || [], true);
  return list;
}

function validateProjectLinks() {
  const docs = orderedIncludedDocuments();
  const ids = new Set(docs.map((doc) => doc.id));
  const anchorsByDoc = new Map();

  docs.forEach((doc) => {
    anchorsByDoc.set(doc.id, extractAnchors(doc.content || ''));
  });

  const issues = [];
  docs.forEach((doc) => {
    extractMarkdownLinks(doc.content || '').forEach((href) => {
      if (href.startsWith('#')) {
        const localAnchor = href.slice(1);
        if (localAnchor && !anchorsByDoc.get(doc.id)?.has(localAnchor)) {
          issues.push(`${doc.name}: Missing local anchor #${localAnchor}`);
        }
        return;
      }

      if (href.startsWith('doc:')) {
        const target = href.slice(4);
        const [targetDocId, targetAnchor] = target.split('#');

        if (!ids.has(targetDocId)) {
          issues.push(`${doc.name}: Missing target document ${targetDocId}`);
          return;
        }

        if (targetAnchor) {
          const targetAnchors = anchorsByDoc.get(targetDocId) || new Set();
          if (!targetAnchors.has(targetAnchor)) {
            issues.push(`${doc.name}: Missing anchor #${targetAnchor} in ${targetDocId}`);
          }
        }
      }
    });
  });

  return issues;
}

function extractMarkdownLinks(markdown) {
  return Array.from(markdown.matchAll(/\[[^\]]+\]\(([^)]+)\)/g), (match) => match[1]);
}

function extractAnchors(markdown) {
  const anchors = new Set();
  markdown.split('\n').forEach((line) => {
    const hit = line.match(/^#{1,6}\s+(.+)/);
    if (!hit) {
      return;
    }
    const slug = slugify(hit[1]);
    if (slug) {
      anchors.add(slug);
    }
  });
  return anchors;
}

function renderMarkdown(markdown) {
  let text = markdown || '';
  const variables = {};
  text = text.replace(/^\[var:([a-zA-Z0-9_\-]+)="([^"]*)"\]\s*$/gm, (_, key, value) => {
    variables[key] = value;
    return '';
  });

  text = text.replace(/\[var:([a-zA-Z0-9_\-]+)\]/g, (_, key) => sanitizeVariableValue(variables[key] ?? `[var:${key}]`));

  const blockMap = new Map();
  let blockIndex = 0;

  text = text.replace(/\{\{([a-zA-Z0-9_\-]+)(?:,([^\n]*?))?\n([\s\S]*?)\n\}\}/g, (_, klass, style, inner) => {
    const safeStyle = sanitizeStyle(style || '');
    const token = `__BLOCK_${blockIndex += 1}__`;
    blockMap.set(token, `<div class="${escapeHtml(klass)}" style="${safeStyle}">${renderBlockBody(inner)}</div>`);
    return token;
  });

  text = text.replace(/:::\s*([a-zA-Z0-9_\-\s]+)\n([\s\S]*?)\n:::/g, (_, classNames, inner) => {
    const token = `__BLOCK_${blockIndex += 1}__`;
    blockMap.set(token, `<div class="${escapeHtml(classNames.trim())}">${renderBlockBody(inner)}</div>`);
    return token;
  });

  const lines = text.split('\n');
  const html = [];
  let inList = false;
  let index = 0;

  while (index < lines.length) {
    const line = lines[index];
    const maybeBlock = blockMap.get(line.trim());
    if (maybeBlock) {
      if (inList) {
        html.push('</ul>');
        inList = false;
      }
      html.push(maybeBlock);
      index += 1;
      continue;
    }

    if (/^\s*[-*]\s+/.test(line)) {
      if (!inList) {
        html.push('<ul>');
        inList = true;
      }
      html.push(`<li>${renderInlineMarkdown(line.replace(/^\s*[-*]\s+/, ''))}</li>`);
      index += 1;
      continue;
    }

    if (inList) {
      html.push('</ul>');
      inList = false;
    }

    if (/^\s*$/.test(line)) {
      html.push('');
      index += 1;
      continue;
    }

    const heading = line.match(/^(#{1,6})\s+(.+)/);
    if (heading) {
      const level = heading[1].length;
      const title = renderInlineMarkdown(heading[2]);
      const id = slugify(stripHtml(title));
      html.push(`<h${level} id="${id}">${title}</h${level}>`);
      index += 1;
      continue;
    }

    if (/^\s*---+\s*$/.test(line)) {
      html.push('<hr />');
      index += 1;
      continue;
    }

    const table = tryRenderMarkdownTable(lines, index);
    if (table) {
      html.push(table.html);
      index = table.nextIndex;
      continue;
    }

    html.push(`<p>${renderInlineMarkdown(line)}</p>`);
    index += 1;
  }

  if (inList) {
    html.push('</ul>');
  }

  return { html: html.join('\n') };
}

function renderBlockBody(text) {
  return renderMarkdown(String(text || '')).html;
}

function tryRenderMarkdownTable(lines, startIndex) {
  const line = lines[startIndex] || '';
  const separator = lines[startIndex + 1] || '';
  if (!/^\|.+\|\s*$/.test(line) || !/^\|\s*[-:| ]+\|\s*$/.test(separator)) {
    return null;
  }

  const rows = [];
  let index = startIndex;
  while (index < lines.length && /^\|.+\|\s*$/.test(lines[index] || '')) {
    rows.push(lines[index]);
    index += 1;
  }

  if (rows.length < 2) {
    return null;
  }

  const headerCells = splitMarkdownTableRow(rows[0]).map((cell) => `<th>${renderInlineMarkdown(cell)}</th>`).join('');
  const bodyRows = rows.slice(2)
    .map((row) => splitMarkdownTableRow(row).map((cell) => `<td>${renderInlineMarkdown(cell)}</td>`).join(''))
    .map((cells) => `<tr>${cells}</tr>`)
    .join('');

  return {
    html: `<table><thead><tr>${headerCells}</tr></thead><tbody>${bodyRows}</tbody></table>`,
    nextIndex: index,
  };
}

function splitMarkdownTableRow(row) {
  return String(row || '')
    .trim()
    .replace(/^\||\|$/g, '')
    .split('|')
    .map((cell) => cell.trim());
}

function renderInlineMarkdown(text) {
  let out = escapeHtml(text);

  out = out.replace(/\{\{([a-zA-Z0-9_\-]+)(?:,([^\s}][^}]*?))?\s+([^}]+)\}\}/g, (_, klass, style, inner) => {
    return `<span class="${escapeHtml(klass)}" style="${sanitizeStyle(style || '')}">${escapeHtml(inner)}</span>`;
  });

  out = out.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (_, alt, src) => {
    const safeSrc = sanitizeImageSrc(src);
    return safeSrc ? `<img src="${safeSrc}" alt="${escapeHtml(alt)}" />` : '';
  });

  out = out.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_, label, href) => {
    const safeHref = sanitizeHref(href);
    if (!safeHref) {
      return label;
    }
    const isExternal = /^https?:\/\//i.test(safeHref);
    return `<a href="${safeHref}"${isExternal ? ' target="_blank" rel="noopener noreferrer"' : ''}>${escapeHtml(label)}</a>`;
  });

  out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  out = out.replace(/`([^`]+)`/g, '<code>$1</code>');
  return out;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function stripHtml(value) {
  const parser = new DOMParser();
  const parsed = parser.parseFromString(String(value), 'text/html');
  return parsed.body.textContent || '';
}

function sanitizeStyle(styleText) {
  const allowedProperties = new Set([
    'background', 'background-color', 'border', 'border-color', 'border-radius', 'box-shadow',
    'break-after', 'color', 'column-count', 'column-gap', 'display', 'float', 'font-family', 'font-size', 'font-style', 'font-weight', 'height', 'line-height',
    'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'max-height', 'max-width',
    'min-height', 'min-width', 'opacity', 'padding', 'padding-top', 'padding-right', 'padding-bottom',
    'padding-left', 'text-align', 'width',
  ]);

  return styleText
    .split(',')
    .map((token) => token.trim())
    .filter(Boolean)
    .filter((token) => {
      const [rawProperty, ...valueParts] = token.split(':');
      if (!rawProperty || valueParts.length === 0) {
        return false;
      }
      const property = rawProperty.trim().toLowerCase();
      const value = valueParts.join(':').trim();
      if (!allowedProperties.has(property)) {
        return false;
      }
      if (!value || /url\s*\(|expression\s*\(/i.test(value)) {
        return false;
      }
      return /^[-#(),.%\sa-zA-Z0-9]+$/.test(value);
    })
    .join('; ');
}

function sanitizeVariableValue(value) {
  return String(value).replace(/[<>]/g, '');
}

function sanitizeHref(value) {
  const href = value.trim();
  if (!href) {
    return '';
  }
  if (href.startsWith('#') || href.startsWith('doc:')) {
    return escapeHtml(href);
  }
  if (/^(https?:\/\/|mailto:)/i.test(href)) {
    return escapeHtml(href);
  }
  return sanitizeRelativeAssetPath(href);
}

function sanitizeImageSrc(value) {
  const src = value.trim();
  if (!src) {
    return '';
  }
  if (/^(https?:\/\/)/i.test(src)) {
    return escapeHtml(src);
  }
  return sanitizeRelativeAssetPath(src);
}

function sanitizeRelativeAssetPath(value) {
  const rawPath = String(value || '').trim();
  if (!/^(assets\/|storage\/assets\/|\.{0,2}\/|[a-zA-Z0-9._/%-]+$)/.test(rawPath)) {
    return '';
  }

  let decodedPath = rawPath;
  try {
    for (let count = 0; count < MAX_URL_DECODE_PASSES; count += 1) {
      const nextDecodedPath = decodeURIComponent(decodedPath);
      if (nextDecodedPath === decodedPath) {
        break;
      }
      decodedPath = nextDecodedPath;
    }
  } catch (error) {
    return '';
  }

  const normalizedPath = decodedPath.replace(/\\/g, '/');
  if (
    normalizedPath.startsWith('/')
    || normalizedPath.includes('\0')
    || normalizedPath.split('/').some((segment) => segment === '..')
  ) {
    return '';
  }

  return escapeHtml(rawPath);
}

function slugify(value) {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

function applyProjectCss() {
  let styleTag = document.getElementById('project-style-overrides');
  if (!styleTag) {
    styleTag = document.createElement('style');
    styleTag.id = 'project-style-overrides';
    document.head.appendChild(styleTag);
  }
  styleTag.textContent = state.project?.styleCss || '';
}

function syncGoogleFontsFromProject() {
  if (state.fontSyncTimer) {
    clearTimeout(state.fontSyncTimer);
    state.fontSyncTimer = null;
  }

  const docs = Object.values(state.project?.documents || {});
  const fontMatches = new Set();
  const sourceText = [
    state.project?.styleCss || '',
    ...docs.map((doc) => doc?.content || ''),
  ].join('\n');

  Array.from(sourceText.matchAll(/font-family\s*:\s*([^;\n]{1,120})/gi)).forEach((match) => {
    String(match[1] || '')
      .split(',')
      .map((token) => token.replace(/["']/g, '').trim())
      .filter(Boolean)
      .forEach((name) => {
        if (/^[a-z0-9][a-z0-9\s-]*$/i.test(name)) {
          fontMatches.add(name);
        }
      });
  });

  ensureGoogleFontLink([...fontMatches]);
}

function scheduleGoogleFontSync() {
  if (state.fontSyncTimer) {
    clearTimeout(state.fontSyncTimer);
  }
  state.fontSyncTimer = setTimeout(() => {
    state.fontSyncTimer = null;
    syncGoogleFontsFromProject();
  }, 150);
}

function ensureGoogleFontLink(fontNames) {
  const uniqueFonts = [...new Set(fontNames.map((name) => String(name || '').trim()).filter(Boolean))];
  if (!uniqueFonts.length) {
    return;
  }

  uniqueFonts.forEach((fontName) => state.loadedGoogleFonts.add(fontName));
  let link = document.getElementById('dynamic-google-fonts');
  if (!link) {
    link = document.createElement('link');
    link.id = 'dynamic-google-fonts';
    link.rel = 'stylesheet';
    document.head.appendChild(link);
  }
  const families = [...state.loadedGoogleFonts]
    .map((name) => `family=${name.trim().replace(/\s+/g, '+')}`)
    .join('&');
  link.href = `https://fonts.googleapis.com/css2?${families}&display=swap`;
}

function cleanupProjectBackup(projectId) {
  if (!projectId) {
    return;
  }
  try {
    localStorage.removeItem(`lorebinder-backup-${projectId}`);
  } catch (error) {
    console.error(error);
  }
}

function applyInterfacePreferences() {
  const preferences = state.authUser?.preferences || {};
  const themeKey = preferences.interfaceTheme || 'midnight';
  const theme = INTERFACE_THEME_STYLES[themeKey] || INTERFACE_THEME_STYLES.midnight;
  Object.entries(theme).forEach(([variable, value]) => {
    document.documentElement.style.setProperty(variable, value);
  });
  document.documentElement.style.setProperty('--font-scale', String(preferences.fontScale || 1));
}

async function uploadAsset() {
  const file = ui.assetUpload.files?.[0];
  if (!file || !state.project) {
    return;
  }

  const form = new FormData();
  form.append('asset', file);
  form.append('projectId', state.project.id);

  const response = await fetch('api.php?action=upload_asset', {
    method: 'POST',
    body: form,
  });

  if (response.status === 401) {
    await handleUnauthorized();
    return;
  }

  const payload = await response.json();
  if (!response.ok) {
    alert(payload.error || 'Asset upload failed.');
    return;
  }

  state.assets = payload.assets || [];
  renderAssets();
  ui.assetUpload.value = '';
}

function renderAssets() {
  ui.assetList.innerHTML = '';
  if (!state.assets.length) {
    ui.assetList.innerHTML = '<li class="empty-state">No assets uploaded for this project.</li>';
    return;
  }

  state.assets.forEach((asset) => {
    const li = document.createElement('li');
    li.className = 'asset-item';

    const label = document.createElement('span');
    label.textContent = `${asset.name} (${formatBytes(asset.size)})`;

    const actions = document.createElement('div');
    actions.className = 'asset-actions';

    const copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Copy Link';
    copy.addEventListener('click', async () => {
      const altText = generateAltTextFromFilename(asset.name);
      const markdown = `![${altText}](${getAssetUrl(asset)})`;
      try {
        await navigator.clipboard.writeText(markdown);
      } catch (error) {
        console.error(error);
        alert('Could not copy the asset markdown link.');
      }
    });

    const rename = document.createElement('button');
    rename.type = 'button';
    rename.textContent = 'Rename';
    rename.addEventListener('click', async () => {
      const nextName = prompt('New asset filename', asset.name);
      if (!nextName) {
        return;
      }
      await postAssetAction('rename_asset', { oldName: asset.name, newName: nextName });
    });

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'danger';
    remove.textContent = 'Delete';
    remove.addEventListener('click', async () => {
      if (!confirm(`Delete ${asset.name}?`)) {
        return;
      }
      await postAssetAction('delete_asset', { filename: asset.name });
    });

    actions.append(copy, rename, remove);
    li.append(label, actions);
    ui.assetList.appendChild(li);
  });
}

async function postAssetAction(action, body) {
  const payload = await apiRequest(action, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...body, projectId: state.project?.id || '' }),
  });

  state.assets = payload.assets || [];
  renderAssets();
}

function markDirty() {
  if (!state.authUser || !state.project) {
    return;
  }

  state.isDirty = true;
  ui.saveStatus.textContent = '○ Editing...';

  if (state.saveTimer) {
    clearTimeout(state.saveTimer);
  }

  state.saveTimer = setTimeout(() => {
    saveState().catch((error) => {
      console.error(error);
      ui.saveStatus.textContent = '⚠ Save failed. Keeping local draft.';
      try {
        localStorage.setItem(`lorebinder-backup-${state.project.id}`, JSON.stringify(state.project));
      } catch (storageError) {
        console.error(storageError);
        ui.saveStatus.textContent = '⚠ Save failed and local backup could not be written.';
      }
    });
  }, 5000);
}

async function saveState() {
  if (!state.isDirty || !state.project) {
    return;
  }

  ui.saveStatus.textContent = '↻ Autosaving...';
  const payload = await apiRequest('save_state', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project: state.project }),
  });

  applyStatePayload(payload);
  state.isDirty = false;
  cleanupProjectBackup(state.project?.id);
  ui.saveStatus.textContent = '● Saved';
  renderProjectControls();
}

async function switchProject(projectId) {
  if (state.isDirty) {
    await saveState();
  }

  const payload = await apiRequest('switch_project', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ projectId }),
  });

  state.isDirty = false;
  applyStatePayload(payload);
  state.selectedNodeId = null;
  renderAll();
}

async function toggleArchiveProject() {
  if (!state.project) {
    return;
  }

  if (state.isDirty) {
    await saveState();
  }

  const payload = await apiRequest('archive_project', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ projectId: state.project.id }),
  });

  applyStatePayload(payload);
  state.isDirty = false;
  renderAll();
}

async function removeCurrentProject() {
  if (!state.project) {
    return;
  }
  if (!confirm(`Delete project "${state.project.title}"? This also removes its assets.`)) {
    return;
  }

  const deletedProjectId = state.project.id;
  const payload = await apiRequest('delete_project', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ projectId: state.project.id }),
  });

  cleanupProjectBackup(deletedProjectId);
  applyStatePayload(payload);
  state.isDirty = false;
  state.selectedNodeId = null;
  renderAll();
}

async function apiRequest(action, options = {}, handle401 = true) {
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);

  if (response.status === 401 && handle401) {
    await handleUnauthorized();
    throw new Error('Login required.');
  }

  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || 'Request failed.');
  }

  return payload;
}

async function handleUnauthorized() {
  state.authUser = null;
  state.project = null;
  state.projects = [];
  state.assets = [];
  renderAuthControls();
  applyInterfacePreferences();
  setWorkspaceEnabled(false);
  clearWorkspace();
  showAuthOverlay();
}

function openOverlay(title, contentHtml, closable = true) {
  state.overlayClosable = closable;
  ui.overlayTitle.textContent = title;
  ui.overlayBody.innerHTML = contentHtml;
  ui.overlay.classList.remove('hidden');
  ui.overlay.setAttribute('aria-hidden', 'false');
  ui.overlayCloseBtn.classList.toggle('hidden', !closable);
}

function closeOverlay() {
  ui.overlay.classList.add('hidden');
  ui.overlay.setAttribute('aria-hidden', 'true');
  ui.overlayBody.innerHTML = '';
}

function showAuthOverlay() {
  openOverlay(
    'Account Access',
    `
      <form id="login-form" class="overlay-block">
        <h3>Login</h3>
        <label for="login-username">Username</label>
        <input id="login-username" name="username" required autocomplete="username" />
        <label for="login-password">Password</label>
        <input id="login-password" name="password" type="password" required autocomplete="current-password" />
        <div class="overlay-actions">
          <button type="submit">Login</button>
        </div>
      </form>
      <form id="request-account-form" class="overlay-block">
        <h3>Request Account</h3>
        <label for="request-username">User Name</label>
        <input id="request-username" name="username" required minlength="3" maxlength="40" />
        <label for="request-real-name">Real Name</label>
        <input id="request-real-name" name="realName" required minlength="2" maxlength="80" />
        <label for="request-email">Email Address</label>
        <input id="request-email" name="email" type="email" required maxlength="160" />
        <label for="request-password">Password</label>
        <input id="request-password" name="password" type="password" required minlength="8" maxlength="120" />
        <label for="request-role">Requested Role</label>
        <select id="request-role" name="role">
          <option value="sub_author">Sub-Author</option>
          <option value="primary_author">Primary Author</option>
          <option value="reviewer">Reviewer</option>
        </select>
        <div class="overlay-actions">
          <button type="submit">Submit Account Request</button>
        </div>
      </form>
      <form id="forgot-password-form" class="overlay-block">
        <h3>Password Reset Email</h3>
        <p class="form-hint">Request a reset link for the email address on file.</p>
        <label for="forgot-password-email">Email Address</label>
        <input id="forgot-password-email" name="email" type="email" required maxlength="160" />
        <div class="overlay-actions">
          <button type="submit">Send Reset Email</button>
        </div>
      </form>
    `,
    false,
  );

  const loginForm = document.getElementById('login-form');
  const requestForm = document.getElementById('request-account-form');
  const forgotForm = document.getElementById('forgot-password-form');

  loginForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(loginForm);

    try {
      const payload = await apiRequest('login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: String(formData.get('username') || ''),
          password: String(formData.get('password') || ''),
        }),
      }, false);

      state.authUser = payload.user || null;
      renderAuthControls();
      applyInterfacePreferences();
      closeOverlay();
      await loadProjectState();
    } catch (error) {
      alert(error.message || 'Login failed.');
    }
  });

  requestForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(requestForm);

    try {
      await apiRequest('request_account', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: String(formData.get('username') || ''),
          realName: String(formData.get('realName') || ''),
          email: String(formData.get('email') || ''),
          password: String(formData.get('password') || ''),
          role: String(formData.get('role') || 'sub_author'),
        }),
      }, false);

      alert('Account request submitted. An admin must approve it.');
      requestForm.reset();
    } catch (error) {
      alert(error.message || 'Account request failed.');
    }
  });

  forgotForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(forgotForm);

    try {
      await apiRequest('request_password_reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: String(formData.get('email') || '') }),
      }, false);
      alert('If that email address exists, a password reset message has been queued.');
      forgotForm.reset();
    } catch (error) {
      alert(error.message || 'Password reset request failed.');
    }
  });
}

function showPasswordResetOverlay(token) {
  openOverlay(
    'Reset Password',
    `
      <form id="password-reset-form" class="overlay-block">
        <p class="form-hint">Choose a new password for your LoreBinder account.</p>
        <label for="reset-password">New Password</label>
        <input id="reset-password" name="password" type="password" required minlength="8" maxlength="120" />
        <label for="reset-password-confirm">Confirm Password</label>
        <input id="reset-password-confirm" name="passwordConfirm" type="password" required minlength="8" maxlength="120" />
        <div class="overlay-actions">
          <button type="submit">Update Password</button>
          <button id="reset-back-to-login" type="button">Back to Login</button>
        </div>
      </form>
    `,
    true,
  );

  const form = document.getElementById('password-reset-form');
  const backButton = document.getElementById('reset-back-to-login');

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);
    const password = String(formData.get('password') || '');
    const passwordConfirm = String(formData.get('passwordConfirm') || '');

    if (password !== passwordConfirm) {
      alert('Passwords must match.');
      return;
    }

    try {
      await apiRequest('complete_password_reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password }),
      }, false);
      window.history.replaceState({}, document.title, window.location.pathname);
      alert('Password updated. You can now sign in.');
      showAuthOverlay();
    } catch (error) {
      alert(error.message || 'Could not complete password reset.');
    }
  });

  backButton?.addEventListener('click', () => {
    window.history.replaceState({}, document.title, window.location.pathname);
    showAuthOverlay();
  });
}

async function logout() {
  await apiRequest('logout', { method: 'POST' }, false);
  state.authUser = null;
  state.project = null;
  state.projects = [];
  state.assets = [];
  state.isDirty = false;
  renderAuthControls();
  applyInterfacePreferences();
  setWorkspaceEnabled(false);
  clearWorkspace();
  showAuthOverlay();
}

function showCreateProjectOverlay() {
  openOverlay(
    'Create Project',
    `
      <form id="create-project-form" class="overlay-block">
        <label for="create-project-title">Project Name</label>
        <input id="create-project-title" name="title" required maxlength="120" />
        <label for="create-project-theme">Primary Theme</label>
        <select id="create-project-theme" name="themeKey">
          ${state.themePresets.map((preset) => `<option value="${escapeHtml(preset.key)}">${escapeHtml(preset.label)}</option>`).join('')}
        </select>
        <div class="overlay-actions">
          <button type="submit">Create Project</button>
        </div>
      </form>
    `,
    true,
  );

  const form = document.getElementById('create-project-form');
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(form);

    try {
      const payload = await apiRequest('create_project', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title: String(formData.get('title') || ''),
          themeKey: String(formData.get('themeKey') || ''),
        }),
      });

      applyStatePayload(payload);
      state.isDirty = false;
      state.selectedNodeId = null;
      closeOverlay();
      renderAll();
      setWorkspaceEnabled(true);
    } catch (error) {
      alert(error.message || 'Could not create project.');
    }
  });
}

async function showProfileOverlay() {
  if (!state.authUser) {
    return;
  }

  const profile = state.authUser.profile || { aboutMe: '', website: '', gamesPlayed: [] };
  const selectedGames = Array.isArray(profile.gamesPlayed) ? profile.gamesPlayed : [];
  const preferences = state.authUser.preferences || { fontScale: 1, interfaceTheme: 'midnight' };
  const ownedProjects = state.projects || [];
  const shareLink = `${window.location.origin}${window.location.pathname}?profile=${encodeURIComponent(state.authUser.username)}`;

  openOverlay(
    'Profile & Preferences',
    `
      <div class="overlay-block">
        <h3>Shareable Profile</h3>
        <p class="form-hint">Share this link with other LoreBinder users or readers.</p>
        <div class="overlay-actions">
          <input id="profile-share-link" value="${escapeHtml(shareLink)}" readonly />
          <button id="copy-profile-link" type="button">Copy Link</button>
        </div>
      </div>
      <form id="profile-form" class="overlay-block">
        <h3>Public Profile</h3>
        <p><strong>User Name:</strong> ${escapeHtml(state.authUser.username)}</p>
        <p><strong>Projects Involved With:</strong> ${ownedProjects.length ? ownedProjects.map((project) => escapeHtml(project.title)).join(', ') : 'None yet'}</p>
        <fieldset class="profile-games-grid">
          <legend>Games Played</legend>
          ${COMMON_GAMES.map((game) => `
            <label class="checkbox-row">
              <input type="checkbox" name="gamesPlayed" value="${escapeHtml(game)}" ${selectedGames.includes(game) ? 'checked' : ''} />
              <span>${escapeHtml(game)}</span>
            </label>
          `).join('')}
        </fieldset>
        <label for="profile-games-custom">Other Games (comma separated)</label>
        <input id="profile-games-custom" name="gamesCustom" value="${escapeHtml(selectedGames.filter((game) => !COMMON_GAMES.includes(game)).join(', '))}" />
        <label for="profile-about">About Me</label>
        <textarea id="profile-about" name="aboutMe" rows="5" maxlength="800">${escapeHtml(profile.aboutMe || '')}</textarea>
        <label for="profile-website">Website Link</label>
        <input id="profile-website" name="website" type="url" value="${escapeHtml(profile.website || '')}" />
        <div class="overlay-actions">
          <button type="submit">Save Profile</button>
        </div>
      </form>
      <form id="preferences-form" class="overlay-block">
        <h3>Main Interface Preferences</h3>
        <label for="pref-font-scale">Main Font Size</label>
        <input id="pref-font-scale" name="fontScale" type="range" min="0.85" max="1.4" step="0.05" value="${escapeHtml(String(preferences.fontScale || 1))}" />
        <p class="form-hint">Current scale: <span id="font-scale-value">${escapeHtml(String(preferences.fontScale || 1))}</span>x</p>
        <label for="pref-interface-theme">Interface Colours</label>
        <select id="pref-interface-theme" name="interfaceTheme">
          ${Object.entries(state.interfaceThemes).map(([key, label]) => `<option value="${escapeHtml(key)}" ${preferences.interfaceTheme === key ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}
        </select>
        <div class="overlay-actions">
          <button type="submit">Save Preferences</button>
        </div>
      </form>
      <div class="overlay-block">
        <h3>Security</h3>
        <p class="form-hint">Need to change your password? Request a reset email at the address on record: <strong>${escapeHtml(state.authUser.email || 'No email on file')}</strong>.</p>
        <div class="overlay-actions">
          <button id="request-current-password-reset" type="button">Email Me a Reset Link</button>
        </div>
      </div>
    `,
    true,
  );

  document.getElementById('copy-profile-link')?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(shareLink);
    } catch (error) {
      console.error(error);
      alert('Could not copy the profile link.');
    }
  });

  document.getElementById('pref-font-scale')?.addEventListener('input', (event) => {
    const fontScaleValue = event.target.value;
    const label = document.getElementById('font-scale-value');
    if (label) {
      label.textContent = fontScaleValue;
    }
  });

  document.getElementById('profile-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const games = formData.getAll('gamesPlayed').map(String);
    const customGames = String(formData.get('gamesCustom') || '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);

    try {
      const payload = await apiRequest('update_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          aboutMe: String(formData.get('aboutMe') || ''),
          website: String(formData.get('website') || ''),
          gamesPlayed: [...new Set([...games, ...customGames])],
        }),
      });
      state.authUser = payload.user || state.authUser;
      renderAuthControls();
      alert('Profile saved.');
      await showProfileOverlay();
    } catch (error) {
      alert(error.message || 'Unable to save profile.');
    }
  });

  document.getElementById('preferences-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);

    try {
      const payload = await apiRequest('update_preferences', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          fontScale: Number(formData.get('fontScale') || 1),
          interfaceTheme: String(formData.get('interfaceTheme') || 'midnight'),
        }),
      });
      state.authUser = payload.user || state.authUser;
      renderAuthControls();
      applyInterfacePreferences();
      alert('Preferences saved.');
    } catch (error) {
      alert(error.message || 'Unable to save preferences.');
    }
  });

  document.getElementById('request-current-password-reset')?.addEventListener('click', async () => {
    try {
      await apiRequest('request_password_reset_current', { method: 'POST' });
      alert('A password reset message has been queued for your account email.');
    } catch (error) {
      alert(error.message || 'Unable to queue password reset.');
    }
  });
}

async function showPublicProfileOverlay(username, loggedIn) {
  const response = await fetch(`api.php?action=public_profile&username=${encodeURIComponent(username)}`);
  const payload = await response.json();
  if (!response.ok) {
    throw new Error(payload.error || 'Unable to load profile.');
  }

  const profile = payload.profile || {};
  openOverlay(
    `${profile.username || username} Profile`,
    `
      <div class="overlay-block public-profile">
        <p><strong>User Name:</strong> ${escapeHtml(profile.username || username)}</p>
        <p><strong>Games Played:</strong> ${(profile.gamesPlayed || []).length ? profile.gamesPlayed.map(escapeHtml).join(', ') : 'No games listed yet.'}</p>
        <p><strong>Projects Involved With:</strong> ${(profile.projects || []).length ? profile.projects.map((project) => `${escapeHtml(project.title)} (${escapeHtml(project.status)})`).join(', ') : 'No projects listed yet.'}</p>
        <p><strong>About Me:</strong> ${escapeHtml(profile.aboutMe || 'No bio provided yet.')}</p>
        <p><strong>Website:</strong> ${profile.website ? `<a href="${escapeHtml(profile.website)}" target="_blank" rel="noopener noreferrer">${escapeHtml(profile.website)}</a>` : 'No website listed.'}</p>
        ${loggedIn ? '' : '<div class="overlay-actions"><button id="shared-profile-login" type="button">Sign In</button></div>'}
      </div>
    `,
    true,
  );

  document.getElementById('shared-profile-login')?.addEventListener('click', () => showAuthOverlay());
}

async function showAdminOverlay() {
  if (!state.authUser || state.authUser.role !== 'admin') {
    alert('Admin access required.');
    return;
  }

  const [usersPayload, requestsPayload, projectsPayload] = await Promise.all([
    apiRequest('admin_list_users'),
    apiRequest('admin_list_requests'),
    apiRequest('admin_list_projects'),
  ]);

  const users = usersPayload.users || [];
  const requests = requestsPayload.requests || [];
  const projects = projectsPayload.projects || [];
  const currentAdminId = state.authUser.id || '';

  openOverlay(
    'Admin Console',
    `
      <div class="overlay-tabs" id="admin-tabs">
        <button class="overlay-tab active" data-panel="admin-requests-panel" type="button">Requests</button>
        <button class="overlay-tab" data-panel="admin-users-panel" type="button">Users</button>
        <button class="overlay-tab" data-panel="admin-projects-panel" type="button">Projects</button>
      </div>
      <section id="admin-requests-panel" class="overlay-panel active">
        <div class="overlay-block">
          <h3>Pending Account Requests</h3>
          <ul class="admin-list" id="admin-requests-list"></ul>
        </div>
      </section>
      <section id="admin-users-panel" class="overlay-panel">
        <div class="overlay-block">
          <h3>Users</h3>
          <ul class="admin-list" id="admin-users-list"></ul>
        </div>
      </section>
      <section id="admin-projects-panel" class="overlay-panel">
        <div class="overlay-block">
          <h3>Projects</h3>
          <ul class="admin-list" id="admin-projects-list"></ul>
        </div>
      </section>
    `,
    true,
  );

  bindOverlayTabs();

  const requestsList = document.getElementById('admin-requests-list');
  const usersList = document.getElementById('admin-users-list');
  const projectsList = document.getElementById('admin-projects-list');

  if (!requests.length) {
    requestsList.innerHTML = '<li class="empty-state">No pending account requests.</li>';
  } else {
    requests.forEach((request) => {
      const li = document.createElement('li');
      li.className = 'admin-item';
      li.innerHTML = `
        <div>
          <strong>${escapeHtml(request.username)}</strong>
          <p>${escapeHtml(request.realName)} • ${escapeHtml(request.email)}</p>
          <p class="muted">Requested role: ${escapeHtml(request.role)} • ${escapeHtml(formatDateTime(request.createdAt))}</p>
        </div>
      `;

      const actions = document.createElement('div');
      actions.className = 'admin-actions';

      const approve = document.createElement('button');
      approve.type = 'button';
      approve.textContent = 'Approve';
      approve.addEventListener('click', async () => {
        await apiRequest('admin_approve_request', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ requestId: request.id, role: request.role }),
        });
        await showAdminOverlay();
      });

      const reject = document.createElement('button');
      reject.type = 'button';
      reject.className = 'danger';
      reject.textContent = 'Reject';
      reject.addEventListener('click', async () => {
        await apiRequest('admin_reject_request', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ requestId: request.id }),
        });
        await showAdminOverlay();
      });

      actions.append(approve, reject);
      li.appendChild(actions);
      requestsList.appendChild(li);
    });
  }

  users.forEach((user) => {
    const li = document.createElement('li');
    li.className = 'admin-item';
    li.innerHTML = `
      <div>
        <strong>${escapeHtml(user.username)}</strong>
        <p>${escapeHtml(user.realName)} • ${escapeHtml(user.email)}</p>
        <p class="muted">Role: ${escapeHtml(user.role)} • Status: ${escapeHtml(user.status)} • Last login: ${escapeHtml(user.lastLoginAt ? formatDateTime(user.lastLoginAt) : 'Never')}</p>
      </div>
    `;

    const actions = document.createElement('div');
    actions.className = 'admin-actions';

    if (user.id !== currentAdminId) {
      const promote = document.createElement('button');
      promote.type = 'button';
      promote.textContent = user.role === 'admin' ? 'Admin' : 'Make Admin';
      promote.disabled = user.role === 'admin';
      promote.addEventListener('click', async () => {
        await apiRequest('admin_set_user_role', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ userId: user.id, role: 'admin' }),
        });
        await showAdminOverlay();
      });

      const resetPassword = document.createElement('button');
      resetPassword.type = 'button';
      resetPassword.textContent = 'Reset Password';
      resetPassword.addEventListener('click', () => {
        showAdminResetPasswordDialog(user).catch((error) => {
          console.error(error);
          alert(error.message || 'Unable to reset password.');
        });
      });

      const toggleStatus = document.createElement('button');
      toggleStatus.type = 'button';
      toggleStatus.textContent = user.status === 'banned' ? 'Unban' : 'Ban';
      toggleStatus.addEventListener('click', async () => {
        await apiRequest('admin_set_user_status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            userId: user.id,
            status: user.status === 'banned' ? 'active' : 'banned',
          }),
        });
        await showAdminOverlay();
      });

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'danger';
      remove.textContent = 'Delete';
      remove.addEventListener('click', async () => {
        if (!confirm(`Delete user ${user.username}? Their owned projects will also be removed.`)) {
          return;
        }
        await apiRequest('admin_delete_user', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ userId: user.id }),
        });
        await showAdminOverlay();
      });

      actions.append(promote, resetPassword, toggleStatus, remove);
    }

    li.appendChild(actions);
    usersList.appendChild(li);
  });

  if (!projects.length) {
    projectsList.innerHTML = '<li class="empty-state">No projects found.</li>';
  } else {
    projects.forEach((project) => {
      const li = document.createElement('li');
      li.className = 'admin-item';
      li.innerHTML = `
        <div>
          <strong>${escapeHtml(project.title)}</strong>
          <p>${escapeHtml(project.ownerUsername)} • ${escapeHtml(themeLabel(project.themeKey))}</p>
          <p class="muted">Status: ${escapeHtml(project.status)} • Updated: ${escapeHtml(formatDateTime(project.updatedAt))}</p>
        </div>
      `;

      const actions = document.createElement('div');
      actions.className = 'admin-actions';

      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'danger';
      remove.textContent = 'Delete';
      remove.addEventListener('click', async () => {
        if (!confirm(`Delete project ${project.title}?`)) {
          return;
        }
        cleanupProjectBackup(project.id);
        await apiRequest('admin_delete_project', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ projectId: project.id }),
        });
        if (state.project?.id === project.id) {
          await loadProjectState();
        }
        await showAdminOverlay();
      });

      actions.append(remove);
      li.appendChild(actions);
      projectsList.appendChild(li);
    });
  }
}

function bindOverlayTabs() {
  ui.overlayBody.querySelectorAll('.overlay-tab').forEach((button) => {
    button.addEventListener('click', () => {
      const targetPanel = button.dataset.panel;
      ui.overlayBody.querySelectorAll('.overlay-tab').forEach((tab) => tab.classList.toggle('active', tab === button));
      ui.overlayBody.querySelectorAll('.overlay-panel').forEach((panel) => panel.classList.toggle('active', panel.id === targetPanel));
    });
  });
}

async function showAdminResetPasswordDialog(user) {
  openOverlay(
    `Reset Password: ${user.username}`,
    `
      <form id="admin-reset-password-form" class="overlay-block">
        <label for="admin-reset-password-input">New Password</label>
        <input id="admin-reset-password-input" name="password" type="password" required minlength="8" maxlength="120" />
        <div class="overlay-actions">
          <button type="submit">Update Password</button>
          <button id="admin-reset-password-cancel" type="button">Back</button>
        </div>
      </form>
    `,
    true,
  );

  document.getElementById('admin-reset-password-cancel')?.addEventListener('click', () => {
    showAdminOverlay().catch((error) => {
      console.error(error);
      alert(error.message || 'Unable to reopen admin panel.');
    });
  });

  document.getElementById('admin-reset-password-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    await apiRequest('admin_reset_user_password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ userId: user.id, password: String(formData.get('password') || '') }),
    });
    alert(`Password reset for ${user.username}.`);
    await showAdminOverlay();
  });
}

async function showHelpOverlay() {
  if (!state.helpHtml) {
    const response = await fetch('help.html');
    state.helpHtml = response.ok
      ? await response.text()
      : '<div class="overlay-help"><h3>Help unavailable</h3><p>Could not load help content.</p></div>';
  }
  openOverlay('LoreBinder Help', state.helpHtml, true);
}

function generateAltTextFromFilename(filename) {
  const baseName = String(filename || '').replace(/\.[^.]+$/, '');
  const readableName = baseName.replace(/[-_]+/g, ' ').trim();
  return readableName || 'Image';
}

function formatBytes(value) {
  if (value < 1024) {
    return `${value} B`;
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} KB`;
  }
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDateTime(value) {
  if (!value) {
    return 'Unknown';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString();
}
