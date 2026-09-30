<?php
$mysqli = new mysqli("localhost", "root", "", "my-site");
if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error;
    exit();
}

$result = $mysqli->query("SELECT post_id, meta_value FROM wp_postmeta WHERE meta_key = '_elementor_data'");

while ($row = $result->fetch_assoc()) {
    $post_id = $row['post_id'];
    $meta_value = $row['meta_value'];
    
    // Mảng các cụm từ cần thay thế
    $replacements = [
        "Join over 12,653,898 of customers that already building amazing websites" => "9BI VIET NAM과 함께 성공적인 비즈니스를 시작하세요.",
        "Powering 12,653,898+ of websites" => "9BI VIET NAM - 최고의 IT 파트너",
        "WP Vantage" => "9BI VIET NAM",
        "Convallis sit etiam ultrices odio at in ut adipiscing ipsum." => "최신 기술과 트렌드를 반영하여 완벽한 디지털 경험을 제공합니다.",
        "프리미엄 테마" => "프리미엄 웹사이트 제작",
        "Logoipsum" => "고객사",
        "워드프레스" => "9BI VIET NAM 디지털 솔루션",
        "지식 기반" => "서비스 안내",
        "제품 둘러보기" => "포트폴리오 보기",
        "지금 멋진 웹사이트 구축을 시작하세요" => "지금 9BI VIET NAM과 프로젝트를 시작하세요",
        "Theme" => "Portfolio",
        "Plugin" => "Quotation"
    ];
    
    $new_meta = $meta_value;
    foreach ($replacements as $old => $new) {
        $new_meta = str_replace($old, $new, $new_meta);
    }
    
    if ($new_meta !== $meta_value) {
        $stmt = $mysqli->prepare("UPDATE wp_postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = '_elementor_data'");
        $stmt->bind_param("si", $new_meta, $post_id);
        $stmt->execute();
        $stmt->close();
    }
}
echo "Updated Elementor content directly in database.";
?>
