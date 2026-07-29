<?php
$db = new PDO('sqlite:' . __DIR__ . '/storage/fort.sqlite');
$stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE email = 'wardhanadty@gmail.com'");
$stmt->execute();
echo "Updated " . $stmt->rowCount() . " row(s).\n";
$check = $db->query("SELECT id, email, is_admin FROM users WHERE email = 'wardhanadty@gmail.com'")->fetch(PDO::FETCH_ASSOC);
print_r($check);
