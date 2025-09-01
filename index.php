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
    .row { display: flex; gap: .6rem; flex-wrap: wrap; }
    .row > * { flex: 1; }
    .muted { color: #666; font-size: .9rem; }
    .error { color: #b00020; }
    .hidden { display: none; }
  </style>
</head>
<body>
  <h1>Notes (AJAX + PHP + .env)</h1>
  <p class="muted">Create, list, update, and delete notes without refreshing the page.</p>

  <form id="create-form">
    <div class="row">
      <input type="text" id="title" placeholder="Title" required />
    </div>
    <textarea id="body" rows="4" placeholder="Body"></textarea>
    <button type="submit">Add Note</button>
    <span id="create-error" class="error"></span>
  </form>

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
  const opts = { headers: { 'Content-Type': 'application/json' } };
  if (payload) { opts.method = 'POST'; opts.body = JSON.stringify(payload); }
  return fetch(`api.php?action=${encodeURIComponent(action)}`, opts).then(r => r.json());
};

const el = (sel, root=document) => root.querySelector(sel);

async function loadNotes() {
  const res = await api('list');
  const wrap = el('#notes');
  wrap.innerHTML = '';
  if (!res.ok) { wrap.innerHTML = '<p class="error">Failed to load.</p>'; return; }

  const tpl = el('#note-template');
  res.data.forEach(n => {
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
      const res2 = await api('update', { id: n.id, title, body });
      if (!res2.ok) { el('.error', node).textContent = res2.error || 'Update failed'; return; }
      n = res2.data;
      el('.title', node).textContent = n.title;
      el('.body', node).textContent = n.body;
      el('.edit', node).click(); // toggle back
    };
    el('.delete', node).onclick = async () => {
      if (!confirm('Delete this note?')) return;
      const res3 = await api('delete', { id: n.id });
      if (res3.ok) node.remove();
    };

    wrap.appendChild(card);
  });
}

el('#create-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  el('#create-error').textContent = '';
  const title = el('#title').value.trim();
  const body  = el('#body').value.trim();
  const res = await api('create', { title, body });
  if (!res.ok) { el('#create-error').textContent = res.error || 'Create failed'; return; }
  el('#title').value = ''; el('#body').value = '';
  await loadNotes();
});

loadNotes();
</script>
</body>
</html>