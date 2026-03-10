<?php
$env = file_get_contents(__DIR__ . '/.env');
preg_match('/DATABASE_URL=\"mysql:\/\/(.+):(.*)@(.+):[0-9]+\/(.+)\?/', $env, $m);
$pdo = new PDO('mysql:host=' . $m[3] . ';dbname=' . $m[4], $m[1], $m[2]);
$stmt = $pdo->query('DESCRIBE mybb_users');
$schema = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cols = ['allownotices', 'hideemail', 'receivepms', 'pmnotice', 'showsigs', 'showavatars', 'showquickreply', 'tpp', 'ppp', 'timezone', 'dst'];
$result = [];
foreach ($schema as $row) {
    if (in_array($row['Field'], $cols)) {
        $result[$row['Field']] = $row['Type'];
    }
}
file_put_contents(__DIR__ . '/schema.json', json_encode($result, JSON_PRETTY_PRINT));
echo "Done";
