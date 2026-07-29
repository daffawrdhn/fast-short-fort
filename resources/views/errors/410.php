<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($_COOKIE['theme'] ?? 'light', ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>410 - Gone | FORT (Fast Short)</title>
  <link rel="stylesheet" href="/assets/css/app.css">
  <link rel="icon" href="/favicon.ico" type="image/x-icon">
</head>
<body class="error-page">
  <div class="error-container">
    <div class="error-code">410</div>
    <h1 class="error-title">Link Unavailable</h1>
    <p class="error-message">This short link is no longer available. It may be inactive, expired, or have reached its click limit.</p>
    <a href="/" class="btn btn-primary">Back to Home</a>
  </div>
</body>
</html>
