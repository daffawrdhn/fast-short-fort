<?php

return [
    'email_verification' => ($_ENV['FEATURE_EMAIL_VERIFICATION'] ?? 'false') === 'true',
    'twofa' => ($_ENV['FEATURE_TWOFA'] ?? 'false') === 'true',
    'geolocation' => ($_ENV['FEATURE_GEOLOCATION'] ?? 'false') === 'true',
    'safe_browsing' => ($_ENV['FEATURE_SAFE_BROWSING'] ?? 'false') === 'true',
    'webhooks' => ($_ENV['FEATURE_WEBHOOKS'] ?? 'false') === 'true',
    'link_cloaking' => ($_ENV['FEATURE_LINK_CLOAKING'] ?? 'false') === 'true',
];
