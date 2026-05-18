const state = {
  project: null,
  assets: [],
  selectedNodeId: null,
  mode: 'visual',
  saveTimer: null,
  isDirty: false,
  authUser: null,
  helpHtml: '',
  overlayClosable: true,
};

const ui = {
  fileTree: document.getElementById('file-tree'),
  assetList: document.getElementById('asset-list'),
  assetUpload: document.getElementById('asset-upload'),
  projectTitle: document.getElementById('project-title'),
  docTabs: document.getElementById('doc-tabs'),
  editorGrid: document.getElementById('editor-grid'),
  visualEditor: document.getElementById('visual-editor'),
  codeEditor: document.getElementById('code-editor'),
  cssEditor: document.getElementById('css-editor'),
  renderedPreview: document.getElementById('rendered-preview'),
  compileOutput: document.getElementById('compile-output'),
  saveStatus: document.getElementById('save-status'),
  modeVisual: document.getElementById('mode-visual'),
  modeCode: document.getElementById('mode-code'),
  compileBtn: document.getElementById('compile-btn'),
  validateLinksBtn: document.getElementById('validate-links-btn'),
  newDocBtn: document.getElementById('new-doc-btn'),
  newFolderBtn: document.getElementById('new-folder-btn'),
  renameNodeBtn: document.getElementById('rename-node-btn'),
  deleteNodeBtn: document.getElementById('delete-node-btn'),
  helpBtn: document.getElementById('help-btn'),
  currentUserLabel: document.getElementById('current-user-label'),
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
  await refreshAuthState();

  if (!state.authUser) {
    setWorkspaceEnabled(false);
    showAuthOverlay();
    return;
  }

  await loadProjectState();
}

function bindEvents() {
  ui.projectTitle.addEventListener('input', () => {
    if (!state.project) {
      return;
    }
    state.project.title = ui.projectTitle.value;
    markDirty();
  });

  ui.modeVisual.addEventListener('click', () => setMode('visual'));
  ui.modeCode.addEventListener('click', () => setMode('code'));

  ui.codeEditor.addEventListener('input', () => {
    updateActiveDocument(ui.codeEditor.value);
    ui.visualEditor.textContent = ui.codeEditor.value;
    refreshPreview();
  });

  ui.visualEditor.addEventListener('input', () => {
    const markdown = ui.visualEditor.textContent;
    updateActiveDocument(markdown);
    ui.codeEditor.value = markdown;
    refreshPreview();
  });

  ui.cssEditor.addEventListener('input', () => {
    if (!state.project) {
      return;
    }
    state.project.styleCss = ui.cssEditor.value;
    applyProjectCss();
    markDirty();
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

async function refreshAuthState() {
  try {
    const payload = await apiRequest('auth_state');
    state.authUser = payload.user || null;
  } catch (error) {
    state.authUser = null;
  }
  renderAuthControls();
}

async function loadProjectState() {
  const payload = await apiRequest('state');
  state.project = payload.project;
  state.assets = payload.assets || [];
  state.authUser = payload.user || state.authUser;

  if (!state.project.activeDocumentId) {
    const firstDoc = collectDocumentNodes(state.project.tree)[0];
    if (firstDoc) {
      state.project.activeDocumentId = firstDoc.docId;
      state.project.openTabs = [firstDoc.docId];
    }
  }

  renderAuthControls();
  renderAll();
  setWorkspaceEnabled(true);
}

function setWorkspaceEnabled(enabled) {
  const controls = [
    ui.fileTree,
    ui.assetUpload,
    ui.projectTitle,
    ui.docTabs,
    ui.visualEditor,
    ui.codeEditor,
    ui.cssEditor,
    ui.compileBtn,
    ui.validateLinksBtn,
    ui.newDocBtn,
    ui.newFolderBtn,
    ui.renameNodeBtn,
    ui.deleteNodeBtn,
    ui.modeVisual,
    ui.modeCode,
  ];

  controls.forEach((node) => {
    if (!node) {
      return;
    }
    if ('disabled' in node) {
      node.disabled = !enabled;
    }
  });

  ui.visualEditor.contentEditable = enabled ? 'true' : 'false';
  ui.codeEditor.readOnly = !enabled;
  ui.cssEditor.readOnly = !enabled;

  if (!enabled) {
    ui.saveStatus.textContent = 'Login required.';
  }
}

function renderAuthControls() {
  if (!state.authUser) {
    ui.currentUserLabel.classList.add('hidden');
    ui.currentUserLabel.textContent = '';
    ui.adminBtn.classList.add('hidden');
    ui.logoutBtn.classList.add('hidden');
    return;
  }

  ui.currentUserLabel.classList.remove('hidden');
  ui.currentUserLabel.textContent = `${state.authUser.username} (${state.authUser.role})`;
  ui.logoutBtn.classList.remove('hidden');
  ui.adminBtn.classList.toggle('hidden', state.authUser.role !== 'admin');
}

function renderAll() {
  ui.projectTitle.value = state.project.title || '';
  ui.cssEditor.value = state.project.styleCss || '';
  applyProjectCss();
  renderTree();
  renderDocTabs();
  renderAssets();
  syncEditorFromActiveDocument();
  setMode(state.mode);
  refreshPreview();
}

function setMode(mode) {
  state.mode = mode;
  const visualActive = mode === 'visual';
  ui.modeVisual.classList.toggle('active', visualActive);
  ui.modeCode.classList.toggle('active', !visualActive);
  ui.visualEditor.classList.toggle('hidden', !visualActive);
  ui.codeEditor.classList.toggle('hidden', visualActive);
  ui.editorGrid.classList.toggle('visual-mode', visualActive);
  ui.editorGrid.classList.toggle('code-mode', !visualActive);
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
  refreshPreview();
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
  refreshPreview();
  markDirty();
}

function renderTree() {
  ui.fileTree.innerHTML = '';

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
      refreshPreview();
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
  refreshPreview();
  markDirty();
}

function closeTab(docId) {
  state.project.openTabs = state.project.openTabs.filter((id) => id !== docId);
  if (state.project.activeDocumentId === docId) {
    state.project.activeDocumentId = state.project.openTabs[0] || '';
  }
  renderDocTabs();
  syncEditorFromActiveDocument();
  refreshPreview();
  markDirty();
}

function updateActiveDocument(content) {
  const doc = getActiveDocument();
  if (!doc) {
    return;
  }

  doc.content = content;
  doc.updatedAt = new Date().toISOString();
  markDirty();
}

function syncEditorFromActiveDocument() {
  const doc = getActiveDocument();
  const content = doc?.content || '';
  ui.codeEditor.value = content;
  ui.visualEditor.textContent = content;
}

function getActiveDocument() {
  const docId = state.project.activeDocumentId;
  if (!docId) {
    return null;
  }
  return state.project.documents[docId] || null;
}

function collectDocumentNodes(tree) {
  const result = [];
  const walk = (nodes) => {
    nodes.forEach((node) => {
      if (node.type === 'document' && node.docId) {
        result.push(node);
      }
      if (node.type === 'folder' && Array.isArray(node.children)) {
        walk(node.children);
      }
    });
  };
  walk(tree || []);
  return result;
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
      return { node, parent: null, container: container || nodes, index };
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

function refreshPreview() {
  const markdown = ui.codeEditor.value;
  const rendered = renderMarkdown(markdown);
  ui.renderedPreview.innerHTML = rendered.html;
}

function compileProject() {
  const chunks = [];
  const docs = orderedIncludedDocuments();

  docs.forEach((doc) => {
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

  walk(state.project.tree, true);
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
    const links = extractMarkdownLinks(doc.content || '');
    links.forEach((href) => {
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

  lines.forEach((line) => {
    const maybeBlock = blockMap.get(line.trim());
    if (maybeBlock) {
      if (inList) {
        html.push('</ul>');
        inList = false;
      }
      html.push(maybeBlock);
      return;
    }

    if (/^\s*[-*]\s+/.test(line)) {
      if (!inList) {
        html.push('<ul>');
        inList = true;
      }
      html.push(`<li>${renderInlineMarkdown(line.replace(/^\s*[-*]\s+/, ''))}</li>`);
      return;
    }

    if (inList) {
      html.push('</ul>');
      inList = false;
    }

    if (/^\s*$/.test(line)) {
      html.push('');
      return;
    }

    const heading = line.match(/^(#{1,6})\s+(.+)/);
    if (heading) {
      const level = heading[1].length;
      const title = renderInlineMarkdown(heading[2]);
      const id = slugify(stripHtml(title));
      html.push(`<h${level} id="${id}">${title}</h${level}>`);
      return;
    }

    html.push(`<p>${renderInlineMarkdown(line)}</p>`);
  });

  if (inList) {
    html.push('</ul>');
  }

  return { html: html.join('\n') };
}

function renderBlockBody(text) {
  return String(text)
    .split('\n')
    .map((line) => {
      if (/^\s*$/.test(line)) {
        return '';
      }
      const heading = line.match(/^(#{1,6})\s+(.+)/);
      if (heading) {
        const level = heading[1].length;
        const title = renderInlineMarkdown(heading[2]);
        return `<h${level}>${title}</h${level}>`;
      }
      if (/^\s*[-*]\s+/.test(line)) {
        return `<p>• ${renderInlineMarkdown(line.replace(/^\s*[-*]\s+/, ''))}</p>`;
      }
      return `<p>${renderInlineMarkdown(line)}</p>`;
    })
    .join('\n');
}

function renderInlineMarkdown(text) {
  let out = escapeHtml(text);

  out = out.replace(/\{\{([a-zA-Z0-9_\-]+)(?:,([^\s}][^}]*?))?\s+([^}]+)\}\}/g, (_, klass, style, inner) => {
    return `<span class="${escapeHtml(klass)}" style="${sanitizeStyle(style || '')}">${escapeHtml(inner)}</span>`;
  });

  out = out.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, (_, alt, src) => {
    const safeSrc = sanitizeImageSrc(src);
    if (!safeSrc) {
      return '';
    }
    return `<img src="${safeSrc}" alt="${escapeHtml(alt)}" />`;
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
    'background',
    'background-color',
    'border',
    'border-color',
    'border-radius',
    'box-shadow',
    'color',
    'display',
    'float',
    'font-size',
    'font-style',
    'font-weight',
    'height',
    'line-height',
    'margin',
    'margin-top',
    'margin-right',
    'margin-bottom',
    'margin-left',
    'max-height',
    'max-width',
    'min-height',
    'min-width',
    'opacity',
    'padding',
    'padding-top',
    'padding-right',
    'padding-bottom',
    'padding-left',
    'text-align',
    'width',
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
  if (/^(assets\/|\.{0,2}\/|[a-zA-Z0-9._/-]+$)/.test(href)) {
    return escapeHtml(href);
  }
  return '';
}

function sanitizeImageSrc(value) {
  const src = value.trim();
  if (!src) {
    return '';
  }
  if (/^(https?:\/\/)/i.test(src)) {
    return escapeHtml(src);
  }
  if (/^(assets\/|\.{0,2}\/|[a-zA-Z0-9._/-]+$)/.test(src)) {
    return escapeHtml(src);
  }
  return '';
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

async function uploadAsset() {
  const file = ui.assetUpload.files?.[0];
  if (!file) {
    return;
  }

  const form = new FormData();
  form.append('asset', file);

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
      const markdown = `![Alt Text](assets/${asset.name})`;
      await navigator.clipboard.writeText(markdown);
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
    body: JSON.stringify(body),
  });

  state.assets = payload.assets || [];
  renderAssets();
}

function markDirty() {
  if (!state.authUser) {
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
        localStorage.setItem('lorebinder-backup', JSON.stringify(state.project));
      } catch (storageError) {
        console.error(storageError);
        ui.saveStatus.textContent = '⚠ Save failed and local backup could not be written.';
      }
    });
  }, 5000);
}

async function saveState() {
  if (!state.isDirty) {
    return;
  }

  ui.saveStatus.textContent = '↻ Autosaving...';

  const payload = await apiRequest('save_state', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project: state.project }),
  });

  if (!payload.ok) {
    throw new Error(payload.error || 'Save request failed.');
  }

  state.isDirty = false;
  ui.saveStatus.textContent = '● Saved';
}

async function apiRequest(action, options = {}) {
  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);

  if (response.status === 401) {
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
  renderAuthControls();
  setWorkspaceEnabled(false);
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
        <label>Username</label>
        <input name="username" required autocomplete="username" />
        <label>Password</label>
        <input name="password" type="password" required autocomplete="current-password" />
        <div class="overlay-actions">
          <button type="submit">Login</button>
        </div>
      </form>
      <form id="request-account-form" class="overlay-block">
        <h3>Request Account</h3>
        <label>User Name</label>
        <input name="username" required minlength="3" maxlength="40" />
        <label>Real Name</label>
        <input name="realName" required minlength="2" maxlength="80" />
        <label>Real Email Address</label>
        <input name="email" type="email" required maxlength="160" />
        <label>Password</label>
        <input name="password" type="password" required minlength="8" maxlength="120" />
        <label>Requested Role</label>
        <select name="role">
          <option value="sub_author">Sub-Author</option>
          <option value="primary_author">Primary Author</option>
          <option value="reviewer">Reviewer</option>
        </select>
        <div class="overlay-actions">
          <button type="submit">Submit Account Request</button>
        </div>
      </form>
    `,
    false,
  );

  const loginForm = document.getElementById('login-form');
  const requestForm = document.getElementById('request-account-form');

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
      });

      state.authUser = payload.user || null;
      renderAuthControls();
      closeOverlay();
      setWorkspaceEnabled(true);
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
      });

      alert('Account request submitted. An admin must approve it.');
      requestForm.reset();
    } catch (error) {
      alert(error.message || 'Account request failed.');
    }
  });
}

async function logout() {
  await apiRequest('logout', { method: 'POST' });
  state.authUser = null;
  state.project = null;
  state.assets = [];
  renderAuthControls();
  setWorkspaceEnabled(false);
  showAuthOverlay();
}

async function showAdminOverlay() {
  if (!state.authUser || state.authUser.role !== 'admin') {
    alert('Admin access required.');
    return;
  }

  const [usersPayload, requestsPayload] = await Promise.all([
    apiRequest('admin_list_users'),
    apiRequest('admin_list_requests'),
  ]);

  const users = usersPayload.users || [];
  const requests = requestsPayload.requests || [];

  openOverlay(
    'Admin Console',
    `
      <div class="overlay-block">
        <h3>Pending Account Requests</h3>
        <ul class="admin-list" id="admin-requests-list"></ul>
      </div>
      <div class="overlay-block">
        <h3>Users</h3>
        <ul class="admin-list" id="admin-users-list"></ul>
      </div>
      <div class="overlay-block">
        <h3>Project Administration</h3>
        <p>Delete project content and assets (cannot be undone).</p>
        <div class="overlay-actions">
          <button id="admin-delete-project-btn" class="danger" type="button">Delete Project Content</button>
        </div>
      </div>
    `,
    true,
  );

  const requestsList = document.getElementById('admin-requests-list');
  const usersList = document.getElementById('admin-users-list');

  if (requests.length === 0) {
    const li = document.createElement('li');
    li.textContent = 'No pending account requests.';
    requestsList?.appendChild(li);
  } else {
    requests.forEach((request) => {
      const li = document.createElement('li');
      li.className = 'admin-item';

      const details = document.createElement('span');
      details.textContent = `${request.username} | ${request.realName} | ${request.email} | role: ${request.role} | requested: ${request.createdAt}`;

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
      li.append(details, actions);
      requestsList?.appendChild(li);
    });
  }

  users.forEach((user) => {
    const li = document.createElement('li');
    li.className = 'admin-item';

    const details = document.createElement('span');
    details.textContent = `${user.username} | ${user.realName} | ${user.email} | status: ${user.status} | role: ${user.role} | last login: ${user.lastLoginAt || 'Never'}`;

    const actions = document.createElement('div');
    actions.className = 'admin-actions';

    if (user.username.toLowerCase() !== state.authUser.username.toLowerCase()) {
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
        if (!confirm(`Delete user ${user.username}?`)) {
          return;
        }
        await apiRequest('admin_delete_user', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ userId: user.id }),
        });
        await showAdminOverlay();
      });

      actions.append(toggleStatus, remove);
    }

    li.append(details, actions);
    usersList?.appendChild(li);
  });

  const deleteProjectBtn = document.getElementById('admin-delete-project-btn');
  deleteProjectBtn?.addEventListener('click', async () => {
    if (!confirm('Delete project content and assets? This cannot be undone.')) {
      return;
    }
    await apiRequest('admin_delete_project', { method: 'POST' });
    await loadProjectState();
    closeOverlay();
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

function formatBytes(value) {
  if (value < 1024) {
    return `${value} B`;
  }
  if (value < 1024 * 1024) {
    return `${(value / 1024).toFixed(1)} KB`;
  }
  return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}
