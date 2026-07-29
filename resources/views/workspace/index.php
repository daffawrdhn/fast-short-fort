<?php $this->extend('layouts.app'); ?>

<?php $this->section('content'); ?>
<div class="workspace-container">
  <div class="page-header">
    <h1 class="page-title">Workspaces</h1>
    <p class="page-subtitle text-muted">Manage your workspaces and team members</p>
  </div>

  <div class="bento-grid">
    <!-- Active Workspace Details -->
    <div class="bento-card bento-col-6">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600;">Active Workspace</h2>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if ($activeWorkspace): ?>
          <div style="margin-bottom:1.5rem;">
            <div style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:0.25rem;">Workspace Name</div>
            <div style="font-size:1.25rem; font-weight:700; color:var(--text-primary);"><?= htmlspecialchars($activeWorkspace->name, ENT_QUOTES, 'UTF-8') ?></div>
          </div>
          <div style="margin-bottom:1.5rem; display:flex; gap:2rem;">
            <div>
              <div style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:0.25rem;">Slug</div>
              <div style="font-family:var(--font-mono); font-size:0.875rem; font-weight:600; color:var(--text-primary);">/<?= htmlspecialchars($activeWorkspace->slug, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div>
              <div style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:0.25rem;">Plan</div>
              <div style="font-size:0.875rem; font-weight:600;"><span class="badge badge-primary" style="text-transform:capitalize;"><?= htmlspecialchars($activeWorkspace->plan, ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
          </div>
          <div style="font-size:0.75rem; color:var(--text-muted);">
            Created on <?= htmlspecialchars($activeWorkspace->created_at ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php else: ?>
          <p class="text-muted">No active workspace selected.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Workspace Switcher -->
    <div class="bento-card bento-col-6">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600;">Switch Workspace</h2>
      </div>
      <div class="card-body" style="padding:0; display:flex; flex-direction:column; height:100%; justify-content:center;">
        <form action="/workspace/switch" method="POST" style="display:flex; flex-direction:column; gap:1.25rem;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-group" style="margin:0;">
            <label for="workspace_id" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:0.5rem;">Select Workspace</label>
            <select name="workspace_id" id="workspace_id" class="form-control" style="width:100%; padding:0.625rem 0.875rem; border-radius:var(--radius); border:1px solid var(--border-color); background:var(--bg-primary); color:var(--text-primary);">
              <optgroup label="Workspaces You Own">
                <?php foreach ($ownedWorkspaces as $ws): ?>
                  <option value="<?= $ws->id ?>" <?= $activeWorkspace && $activeWorkspace->id === $ws->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ws->name, ENT_QUOTES, 'UTF-8') ?> (Owner)
                  </option>
                <?php endforeach; ?>
              </optgroup>
              <?php if (!empty($memberWorkspaces)): ?>
                <optgroup label="Joined Workspaces">
                  <?php foreach ($memberWorkspaces as $ws): ?>
                    <option value="<?= $ws['id'] ?>" <?= $activeWorkspace && $activeWorkspace->id === (int)$ws['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($ws['name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($ws['role'], ENT_QUOTES, 'UTF-8') ?>)
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endif; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-block" style="padding:0.625rem 1rem;">Switch</button>
        </form>
      </div>
    </div>

    <!-- Create Workspace -->
    <div class="bento-card bento-col-6">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600;">Create Workspace</h2>
      </div>
      <div class="card-body" style="padding:0;">
        <form action="/workspace/create" method="POST" style="display:flex; flex-direction:column; gap:1.25rem;">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="form-group" style="margin:0;">
            <label for="new_ws_name" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:0.5rem;">Workspace Name</label>
            <input type="text" name="name" id="new_ws_name" class="form-control" placeholder="e.g. Acme Marketing" required style="width:100%; padding:0.625rem 0.875rem;">
          </div>
          <div class="form-group" style="margin:0;">
            <label for="new_ws_slug" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:0.5rem;">Workspace Slug (Optional)</label>
            <input type="text" name="slug" id="new_ws_slug" class="form-control" placeholder="e.g. acme-marketing" style="width:100%; padding:0.625rem 0.875rem;">
          </div>
          <button type="submit" class="btn btn-secondary btn-block" style="padding:0.625rem 1rem;">Create</button>
        </form>
      </div>
    </div>

    <!-- Workspace Members -->
    <div class="bento-card bento-col-6">
      <div class="card-header" style="border:none; padding:0; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title" style="font-size:1.1rem; font-weight:600; margin:0;">Members</h2>
      </div>
      <div class="card-body" style="padding:0; display:flex; flex-direction:column; justify-content:space-between; height:100%;">
        <div class="table-responsive" style="border:none; background:transparent; max-height:220px; overflow-y:auto; margin-bottom:1.5rem;">
          <table class="table">
            <thead>
              <tr>
                <th style="padding-left:0;">Name</th>
                <th>Role</th>
                <th style="padding-right:0; text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($activeWorkspace): ?>
                <!-- Owner Entry -->
                <tr>
                  <td style="padding-left:0;">
                    <div style="font-weight:600; color:var(--text-primary);">Owner</div>
                  </td>
                  <td><span class="badge badge-success">Owner</span></td>
                  <td style="padding-right:0; text-align:right;"><span class="text-muted" style="font-size:0.75rem;">-</span></td>
                </tr>
                <!-- Member Entries -->
                <?php foreach ($members as $member): ?>
                  <tr>
                    <td style="padding-left:0;">
                      <div style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($member['name'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8') ?></div>
                    </td>
                    <td><span class="badge badge-info" style="text-transform:capitalize;"><?= htmlspecialchars($member['role'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td style="padding-right:0; text-align:right;">
                      <?php if ($activeWorkspace->owner_id === (int)\App\Core\Session::getInstance()->get('user_id')): ?>
                        <form action="/workspace/remove-member" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this member?');">
                          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                          <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                          <button type="submit" class="btn btn-sm btn-ghost" style="color:var(--color-danger-500); padding:0.25rem 0.5rem; text-transform:none;">Remove</button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted" style="font-size:0.75rem;">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Invite Member Form -->
        <?php if ($activeWorkspace && $activeWorkspace->owner_id === (int)\App\Core\Session::getInstance()->get('user_id')): ?>
          <form action="/workspace/invite" method="POST" style="display:flex; gap:0.75rem; align-items:flex-end; border-top:1px solid var(--border-color); padding-top:1.25rem;">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div style="flex-grow:1;">
              <label for="invite_email" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:0.25rem;">Invite Member (Email)</label>
              <input type="email" name="email" id="invite_email" placeholder="user@example.com" required class="form-control" style="width:100%; padding:0.45rem 0.75rem;">
            </div>
            <div>
              <label for="invite_role" style="font-size:0.75rem; text-transform:uppercase; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:0.25rem;">Role</label>
              <select name="role" id="invite_role" class="form-control" style="padding:0.45rem 2rem 0.45rem 0.75rem;">
                <option value="viewer">Viewer</option>
                <option value="editor">Editor</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem;">Invite</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php $this->endSection(); ?>
