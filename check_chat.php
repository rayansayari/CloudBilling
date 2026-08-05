<?php
require __DIR__.'/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dsn = $_ENV['DATABASE_URL'] ?? '';
preg_match('/mysql:\/\/([^:]+):([^@]+)@([^:\/]+)(?::(\d+))?\/([^\?]+)/', $dsn, $m);
$pdo = new PDO('mysql:host='.($m[3]??'localhost').';port='.($m[4]??'3306').';dbname='.($m[5]??'cloud_billing').';charset=utf8', $m[1]??'root', $m[2]??'');

echo "=== CHAT MESSAGES ===\n";
$stmt = $pdo->query('SELECT id, sender_id, recipient_id, channel, LEFT(content,30) as content, is_read, created_at FROM chat_message ORDER BY id DESC LIMIT 20');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID:{$r['id']} | sender:{$r['sender_id']} | recipient:".($r['recipient_id']??'NULL')." | channel:".($r['channel']??'NULL')." | read:{$r['is_read']} | msg:{$r['content']}\n";
}

echo "\n=== USERS ===\n";
$stmt = $pdo->query('SELECT id, email, roles FROM user');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "ID:{$r['id']} | email:{$r['email']} | roles:{$r['roles']}\n";
}

echo "\n=== UNREAD per recipient ===\n";
$stmt = $pdo->query('SELECT recipient_id, COUNT(*) as unread FROM chat_message WHERE is_read=0 AND recipient_id IS NOT NULL GROUP BY recipient_id');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "recipient_id:{$r['recipient_id']} | unread:{$r['unread']}\n";
}
