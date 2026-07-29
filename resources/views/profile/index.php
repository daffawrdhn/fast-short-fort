<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Profile - FORT') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <?php require __DIR__ . '/../layouts/navbar.php'; ?>
    <div class="container mt-4">
        <h1>Profile</h1>
        <?php if (!empty($flash['success'])): ?><div class="alert alert-success"><?= htmlspecialchars($flash['success']) ?></div><?php endif; ?>
        <?php if (!empty($flash['error'])): ?><div class="alert alert-danger"><?= htmlspecialchars($flash['error']) ?></div><?php endif; ?>
        <form method="POST" action="/profile">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Core\Session::getInstance()->csrfToken()) ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user->name ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user->email ?? '') ?>">
            </div>
            <hr>
            <h4>Change Password</h4>
            <div class="mb-3">
                <label for="current_password" class="form-label">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">New Password</label>
                <input type="password" class="form-control" id="password" name="password">
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</body>
</html>
