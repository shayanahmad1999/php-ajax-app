<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PHP AJAX + .env (Notes)</title>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem; }
    h1 { font-weight: 700; }
    form, .note { border: 1px solid #ddd; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; }
    input, textarea { width: 100%; padding: .6rem; margin: .4rem 0; border: 1px solid #ccc; border-radius: 8px; }
    button { padding: .6rem .9rem; border-radius: 8px; border: 1px solid #333; background: #333; color: #fff; cursor: pointer; }
    button.secondary { background:#fff; color:#333; margin-left:.5rem; }
    button:disabled { opacity: 0.6; cursor: not-allowed; }
    .row { display: flex; gap: .6rem; flex-wrap: wrap; }
    .row > * { flex: 1; }
    .muted { color: #666; font-size: .9rem; }
    .error { color: #b00020; }
    .success { color: #008000; }
    .hidden { display: none; }
    .loading { display: inline-block; width: 20px; height: 20px; border: 3px solid #f3f3f3; border-top: 3px solid #333; border-radius: 50%; animation: spin 1s linear infinite; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .search-container { margin-bottom: 1rem; }
    .search-container input { margin-bottom: 0; }
  </style>
</head>
<body>
  <h1>Notes (AJAX + PHP + .env)</h1>
  <p class="muted">Create, list, update, and delete notes without refreshing the page.</p>

  <div class="search-container">
    <input type="text" id="search" placeholder="Search notes..." />
  </div>

  <form id="create-form">
    <div class="row">
      <input type="text" id="title" placeholder="Title" required />
    </div>
    <textarea id="body" rows="4" placeholder="Body"></textarea>
    <button type="submit">Add Note</button>
    <span id="create-error" class="error"></span>
    <span id="create-success" class="success"></span>
  </form>

  <div id="loading" class="hidden">
    <span class="loading"></span> Loading...
  </div>

  <div id="notes"></div>

  <template id="note-template">
    <div class="note" data-id="">
      <div><strong class="view title"></strong></div>
      <div class="view body muted"></div>
      <div class="muted view created"></div>
      <div class="row">
        <button class="edit">Edit</button>
        <button class="delete secondary">Delete</button>
      </div>
      <div class="edit-form hidden">
        <input class="edit-title" type="text" />
        <textarea class="edit-body" rows="4"></textarea>
        <button class="save">Save</button>
        <button class="cancel secondary">Cancel</button>
        <span class="error"></span>
      </div>
    </div>
  </template>

<script>
const api = async (action, payload=null) => {
  const loadingEl = document.getElementById('loading');
  if (loadingEl) loadingEl.classList.remove('hidden');
  
  try {
    const opts = { headers: { 'Content-Type': 'application/json' } };
    if (payload) { opts.method = 'POST'; opts.body = JSON.stringify(payload); }
    const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, opts);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('API Error:', error);
    return { ok: false, error: 'Network error occurred' };
  } finally {
    if (loadingEl) loadingEl.classList.add('hidden');
  }
};

const el = (sel, root=document) => root.querySelector(sel);

async function loadNotes(searchTerm = '') {
  const res = await api('list');
  const wrap = el('#notes');
  wrap.innerHTML = '';
  if (!res.ok) {
    wrap.innerHTML = '<p class="error">Failed to load notes: ' + (res.error || 'Unknown error') + '</p>';
    return;
  }

  // Filter notes based on search term if provided
  let notes = res.data;
  if (searchTerm) {
    notes = notes.filter(n =>
      n.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      n.body.toLowerCase().includes(searchTerm.toLowerCase())
    );
  }

  if (notes.length === 0) {
    wrap.innerHTML = '<p>No notes found.</p>';
    return;
  }

  const tpl = el('#note-template');
  notes.forEach(n => {
    const card = tpl.content.cloneNode(true);
    const node = card.querySelector('.note');
    node.dataset.id = n.id;
    el('.title', node).textContent = n.title;
    el('.body', node).textContent = n.body;
    el('.created', node).textContent = 'Created at: ' + n.created_at;

    el('.edit', node).onclick = () => {
      el('.edit-form', node).classList.remove('hidden');
      el('.view.title', node).classList.add('hidden');
      el('.view.body', node).classList.add('hidden');
      el('.edit-title', node).value = n.title;
      el('.edit-body', node).value = n.body;
    };
    el('.cancel', node).onclick = () => {
      el('.edit-form', node).classList.add('hidden');
      el('.view.title', node).classList.remove('hidden');
      el('.view.body', node).classList.remove('hidden');
      el('.error', node).textContent = '';
    };
    el('.save', node).onclick = async () => {
      const title = el('.edit-title', node).value.trim();
      const body = el('.edit-body', node).value.trim();
      
      // Frontend validation
      if (!title) {
        el('.error', node).textContent = 'Title is required';
        return;
      }
      
      const res2 = await api('update', { id: n.id, title, body });
      if (!res2.ok) {
        el('.error', node).textContent = res2.error || 'Update failed';
        return;
      }
      n = res2.data;
      el('.title', node).textContent = n.title;
      el('.body', node).textContent = n.body;
      el('.edit', node).click(); // toggle back
      el('.error', node).textContent = '';
      el('#create-success').textContent = 'Note updated successfully';
      setTimeout(() => el('#create-success').textContent = '', 3000);
    };
    el('.delete', node).onclick = async () => {
      if (!confirm('Delete this note?')) return;
      const res3 = await api('delete', { id: n.id });
      if (res3.ok) {
        node.remove();
        el('#create-success').textContent = 'Note deleted successfully';
        setTimeout(() => el('#create-success').textContent = '', 3000);
      } else {
        alert('Failed to delete note: ' + (res3.error || 'Unknown error'));
      }
    };

    wrap.appendChild(card);
  });
}

el('#create-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  el('#create-error').textContent = '';
  el('#create-success').textContent = '';
  
  const title = el('#title').value.trim();
  const body  = el('#body').value.trim();
  
  // Frontend validation
  if (!title) {
    el('#create-error').textContent = 'Title is required';
    return;
  }
  
  const submitBtn = el('#create-form button[type="submit"]');
  const originalText = submitBtn.textContent;
  submitBtn.textContent = 'Adding...';
  submitBtn.disabled = true;
  
  try {
    const res = await api('create', { title, body });
    if (!res.ok) {
      el('#create-error').textContent = res.error || 'Create failed';
      return;
    }
    el('#title').value = '';
    el('#body').value = '';
    el('#create-success').textContent = 'Note added successfully';
    await loadNotes();
    setTimeout(() => el('#create-success').textContent = '', 3000);
  } finally {
    submitBtn.textContent = originalText;
    submitBtn.disabled = false;
  }
});

// Add search functionality
let searchTimeout;
el('#search').addEventListener('input', (e) => {
  const searchTerm = e.target.value.trim();
  
  // Debounce search to avoid too many requests
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadNotes(searchTerm);
  }, 300);
});

// Load notes on page load
loadNotes();
</script>
</body>
</html>