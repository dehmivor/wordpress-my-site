<?php
$mysqli = new mysqli('localhost', 'root', '', 'my-site');
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}
$res = $mysqli->query('SELECT meta_value FROM wp_postmeta WHERE post_id = 49 AND meta_key = "_elementor_data"');
$row = $res->fetch_assoc();
$data = json_decode($row['meta_value'], true);
echo json_encode($data[0]['settings'] ?? [], JSON_PRETTY_PRINT);
?>
