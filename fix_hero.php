<?php
$mysqli = new mysqli("localhost", "root", "", "my-site");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

$result = $mysqli->query("SELECT meta_value FROM wp_postmeta WHERE post_id = 49 AND meta_key = '_elementor_data'");
$row = $result->fetch_assoc();
if (!$row) die("No data");

$data = json_decode($row['meta_value'], true);

$hero = &$data[0];

$image_url = "";
$image_id = "";
if (isset($hero['elements'][1])) {
    $right_col = $hero['elements'][1];
    foreach ($right_col['elements'] as $widget) {
        if (isset($widget['widgetType']) && $widget['widgetType'] === 'image') {
            $image_url = $widget['settings']['image']['url'] ?? '';
            $image_id = $widget['settings']['image']['id'] ?? '';
            break;
        }
    }
}

if (!$image_url) {
    // If not found inside 'image' widget, check if the column itself has background
    $image_url = "http://192.168.1.19/my-site/wp-content/uploads/2022/04/digital-download-store-hero-image.jpg"; // Fallback URL
}

$hero['settings']['content_width'] = 'full';
$hero['settings']['background_background'] = 'classic';
$hero['settings']['background_image'] = [ 'url' => $image_url, 'id' => $image_id ];
$hero['settings']['background_position'] = 'center center';
$hero['settings']['background_size'] = 'cover';
$hero['settings']['background_repeat'] = 'no-repeat';

$hero['settings']['padding'] = [
    'unit' => 'px',
    'top' => '150',
    'right' => '50',
    'bottom' => '100',
    'left' => '50',
    'isLinked' => false
];

$hero['settings']['content_width'] = 'full';
$hero['settings']['align_items'] = 'center';
$hero['settings']['height'] = 'min-height';
$hero['settings']['custom_height'] = [ 'unit' => 'vh', 'size' => 100 ];

$hero['settings']['background_overlay_background'] = 'classic';
$hero['settings']['background_overlay_color'] = '#000000';
$hero['settings']['background_overlay_opacity'] = [ 'unit' => 'px', 'size' => 0.6 ];

if (isset($hero['elements'][1])) {
    unset($hero['elements'][1]);
    $hero['elements'] = array_values($hero['elements']);
}

if (isset($hero['elements'][0]['elements'])) {
    foreach ($hero['elements'][0]['elements'] as &$widget) {
        if (isset($widget['widgetType']) && ($widget['widgetType'] === 'heading' || $widget['widgetType'] === 'text-editor')) {
            $widget['settings']['title_color'] = '#ffffff';
            $widget['settings']['text_color'] = '#ffffff';
        }
    }
}

$new_meta = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$stmt = $mysqli->prepare("UPDATE wp_postmeta SET meta_value = ? WHERE post_id = 49 AND meta_key = '_elementor_data'");
$stmt->bind_param("s", $new_meta);
$stmt->execute();
$stmt->close();

echo "Hero section updated successfully.";
?>
