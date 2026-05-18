const state = {
  project: null,
  assets: [],
  selectedNodeId: null,
  mode: 'visual',
  saveTimer: null,
  isDirty: false,
};

const ui = {
  fileTree: document.getElementById('file-tree'),
  assetList: document.getElementById('asset-list'),
  assetUpload: document.getElementById('asset-upload'),
  projectTitle: document.getElementById('project-title'),
  docTabs: document.getElementById('doc-tabs'),
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
};

bootstrap().catch((error) => {
  console.error(error);
  ui.compileOutput.textContent = `Failed to load LoreBinder state: ${error.message}`;
});

async function bootstrap() {
  const response = await fetch('api.php?action=state');
  const payload = await response.json();
  state.project = payload.project;
  state.assets = payload.assets || [];

  if (!state.project.activeDocumentId) {
    const firstDoc = collectDocumentNodes(state.project.tree)[0];
    if (firstDoc) {
      state.project.activeDocumentId = firstDoc.docId;
      state.project.openTabs = [firstDoc.docId];
    }
  }

  bindEvents();
  renderAll();
}

function bindEvents() {
  ui.projectTitle.addEventListener('input', () => {
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
    state.project.styleCss = ui.cssEditor.value;
    applyProjectCss();
    markDirty();
  });

  ui.compileBtn.addEventListener('click', () => {
    const compiled = compileProject();
    ui.compileOutput.textContent = compiled.output;
  });

  ui.validateLinksBtn.addEventListener('click', () => {
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
}

function createNode(type) {
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
  const links = [];
  const pattern = /\[[^\]]+\]\(([^)]+)\)/g;
  let match;
  while ((match = pattern.exec(markdown)) !== null) {
    links.push(match[1]);
  }
  return links;
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

  text = text.replace(/\[var:([a-zA-Z0-9_\-]+)\]/g, (_, key) => variables[key] ?? `[var:${key}]`);

  text = text.replace(/\{\{([a-zA-Z0-9_\-]+)(?:,([^\n]*?))?\n([\s\S]*?)\n\}\}/g, (_, klass, style, inner) => {
    const safeStyle = sanitizeStyle(style || '');
    return `<div class="${escapeHtml(klass)}" style="${safeStyle}">${renderInlineMarkdown(inner)}</div>`;
  });

  text = text.replace(/:::\s*([a-zA-Z0-9_\-\s]+)\n([\s\S]*?)\n:::/g, (_, classNames, inner) => {
    return `<div class="${escapeHtml(classNames.trim())}">${renderInlineMarkdown(inner)}</div>`;
  });

  const lines = text.split('\n');
  const html = [];
  let inList = false;

  lines.forEach((line) => {
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

function renderInlineMarkdown(text) {
  let out = escapeHtml(text);

  out = out.replace(/\{\{([a-zA-Z0-9_\-]+)(?:,([^\s}][^}]*?))?\s+([^}]+)\}\}/g, (_, klass, style, inner) => {
    return `<span class="${escapeHtml(klass)}" style="${sanitizeStyle(style || '')}">${escapeHtml(inner)}</span>`;
  });

  out = out.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" />');
  out = out.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
  out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  out = out.replace(/`([^`]+)`/g, '<code>$1</code>');

  return out;
}

function escapeHtml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function stripHtml(value) {
  const div = document.createElement('div');
  div.innerHTML = value;
  return div.textContent || '';
}

function sanitizeStyle(styleText) {
  return styleText
    .split(',')
    .map((token) => token.trim())
    .filter(Boolean)
    .filter((token) => /^[-a-zA-Z]+\s*:\s*[-#(),.%\sa-zA-Z0-9]+$/.test(token))
    .join('; ');
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
  styleTag.textContent = state.project.styleCss || '';
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
  const response = await fetch(`api.php?action=${action}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  const payload = await response.json();
  if (!response.ok) {
    alert(payload.error || 'Asset action failed.');
    return;
  }

  state.assets = payload.assets || [];
  renderAssets();
}

function markDirty() {
  state.isDirty = true;
  ui.saveStatus.textContent = '○ Editing...';

  if (state.saveTimer) {
    clearTimeout(state.saveTimer);
  }

  state.saveTimer = setTimeout(() => {
    saveState().catch((error) => {
      console.error(error);
      ui.saveStatus.textContent = '⚠ Save failed. Keeping local draft.';
      localStorage.setItem('lorebinder-backup', JSON.stringify(state.project));
    });
  }, 5000);
}

async function saveState() {
  if (!state.isDirty) {
    return;
  }

  ui.saveStatus.textContent = '↻ Autosaving...';

  const response = await fetch('api.php?action=save_state', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ project: state.project }),
  });

  const payload = await response.json();
  if (!response.ok || !payload.ok) {
    throw new Error(payload.error || 'Save request failed.');
  }

  state.isDirty = false;
  ui.saveStatus.textContent = '● Saved';
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
