<div class="admin-page-header">
  <h1>URL Blocklist</h1>
  <p class="text-muted">Manage blocked domains and patterns</p>
</div>

<div class="admin-grid">
  <div class="card">
    <div class="card-header">
      <h2>Add Pattern</h2>
    </div>
    <div class="card-body">
      <form method="post" action="/admin/blocklist/add">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
          <label for="pattern">Domain or Pattern</label>
          <input type="text" id="pattern" name="pattern" placeholder="e.g., spam.com, *.spam.com" required aria-required="true">
          <p class="form-help">Enter a domain or wildcard pattern to block.</p>
        </div>
        <button type="submit" class="btn btn-primary">Add to Blocklist</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Import Blocklist</h2>
    </div>
    <div class="card-body">
      <form method="post" action="/admin/blocklist/import">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-group">
          <label for="patterns">Patterns (one per line)</label>
          <textarea id="patterns" name="patterns" rows="8" placeholder="spam.com&#10;malware.net&#10;*.phishing.org" aria-label="Blocklist patterns"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
      </form>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Current Blocklist</h2>
    <span class="badge"><?= count($blocklist ?? []) ?> entries</span>
  </div>
  <div class="table-responsive">
    <table class="table" role="table" aria-label="Current blocklist">
      <thead>
        <tr>
          <th>ID</th>
          <th>Pattern</th>
          <th>Added</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($blocklist)): ?>
        <tr>
          <td colspan="4" class="text-center text-muted">Blocklist is empty.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($blocklist as $entry): ?>
        <tr>
          <td><?= htmlspecialchars((string)($entry['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td><code><?= htmlspecialchars($entry['pattern'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></td>
          <td><?= htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td>
            <form method="post" action="/admin/blocklist/<?= $entry['id'] ?? 0 ?>/remove" class="inline-form" onsubmit="return confirm('Remove this pattern from blocklist?');">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-sm btn-danger" aria-label="Remove pattern">Remove</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
