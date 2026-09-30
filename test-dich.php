<?php

/**
 * Hàm dịch tiếng Anh sang tiếng Hàn kèm kiểm tra giới hạn ký tự
 * 
 * @param string $english_text Chữ tiếng Anh cần dịch
 * @param int $max_length Giới hạn số lượng ký tự tối đa
 * @return string Bản dịch tiếng Hàn hợp lệ
 */
function translate_to_korean_with_limit($english_text, $max_length)
{
    $is_valid = false;
    $attempt = 1;
    $max_attempts = 5; // Giới hạn tối đa 5 lần thử để tránh bị kẹt trong vòng lặp vô hạn (treo máy)
    $final_translation = "";

    // Lặp đến khi nào thỏa mãn độ dài, hoặc hết số lần thử cho phép
    while (!$is_valid && $attempt <= $max_attempts) {

        // 1. Gọi hàm lấy kết quả dịch (Bạn sẽ phải dùng API thật ở đây)
        $korean_text = call_translation_api($english_text, $attempt);

        // Lấy số lượng ký tự của chuỗi tiếng Hàn (phải dùng mb_strlen vì tiếng Hàn là ký tự Unicode)
        $current_length = mb_strlen($korean_text, 'UTF-8');

        // 2. Câu lệnh if/else kiểm tra giới hạn
        if ($current_length <= $max_length) {
            // Nếu kết quả nằm trong giới hạn -> Thỏa mãn, lưu lại và thoát vòng lặp
            $final_translation = $korean_text;
            $is_valid = true;
        } else {
            // Nếu bị vượt quá ký tự -> Tăng số lần thử lên 1 và chạy lại vòng lặp để lấy từ khác
            $attempt++;
        }
    }

    if (!$is_valid) {
        return "Lỗi: Không thể tìm được bản dịch nào dưới {$max_length} ký tự.";
    }

    return $final_translation;
}

/**
 * Hàm GIẢ LẬP gọi API dịch thuật (Google / OpenAI)
 */
function call_translation_api($text, $attempt)
{
    // Nếu dùng OpenAI API, bạn có thể truyền số $attempt vào câu prompt: 
    // "Dịch câu này sang tiếng Hàn. Nếu attempt > 1, hãy tìm từ đồng nghĩa ngắn hơn".

    // Dữ liệu giả lập để minh họa:
    if ($attempt == 1)
        return "이것은 매우 긴 한국어 번역 문장입니다."; // Dài 21 ký tự (Lần 1)
    if ($attempt == 2)
        return "이것은 짧은 번역입니다."; // Dài 12 ký tự (Lần 2)
    return "짧은 번역"; // Dài 5 ký tự (Lần 3)
}

// --- TEST CHẠY THỬ LỆNH ---
$english_word = "This is a text that needs to be translated";
$db_character_limit = 15; // Giả sử database của bạn chỉ cho phép tối đa 15 ký tự

$result = translate_to_korean_with_limit($english_word, $db_character_limit);

echo "Kết quả dịch cuối cùng lưu vào DB: " . $result;

?>