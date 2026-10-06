<?php

namespace Jankx\Extensions\AiChatbox\Services;

/**
 * Nguồn dữ liệu gợi ý câu hỏi theo loại trang.
 *
 * Tách riêng khỏi controller vì endpoint này được phục vụ bởi cả hai
 * transport: REST (/wp-json/jankx/v1/ai-chat/suggestions) và Fast AJAX
 * (/jankx-ajax/ai/chat/suggestions). Để map nằm trong controller thì chỉ cần
 * sửa một bên là hai bên lệch nhau.
 */
class SuggestionService
{
    /**
     * Gợi ý theo loại trang.
     */
    public static function map(): array
    {
        return [
            'product' => [
                ['label' => 'Giá & Ưu đãi',       'query' => 'Tour này giá bao nhiêu và có ưu đãi gì?'],
                ['label' => 'Lịch khởi hành',      'query' => 'Cho tôi biết các ngày khởi hành gần nhất'],
                ['label' => 'Chính sách đặt tour', 'query' => 'Điều kiện và chính sách đặt tour này là gì?'],
            ],
            'tour' => [
                ['label' => 'Lịch trình tour', 'query' => 'Mô tả lịch trình chi tiết tour này'],
                ['label' => 'Giá tour',         'query' => 'Tour này có các mức giá nào?'],
                ['label' => 'Đặt tour ngay',    'query' => 'Tôi muốn đặt tour này, hướng dẫn tôi các bước'],
            ],
            'destination' => [
                ['label' => 'Tour đến đây',       'query' => 'Có những tour nào đến điểm đến này?'],
                ['label' => 'Thời điểm đẹp nhất', 'query' => 'Thời điểm nào trong năm đẹp nhất để đi?'],
                ['label' => 'Gợi ý hành trình',   'query' => 'Gợi ý hành trình tự túc tại đây'],
            ],
        ];
    }

    /**
     * Gợi ý mặc định khi page_type không nằm trong map.
     */
    public static function defaults(): array
    {
        return [
            ['label' => 'Hỏi về trang này', 'query' => 'Tóm tắt nội dung trang này cho tôi'],
            ['label' => 'Tour liên quan',  'query' => 'Gợi ý các tour du lịch liên quan'],
        ];
    }

    /**
     * Gợi ý cho một loại trang, đã đi qua filter cho phép tùy chỉnh.
     *
     * @param string $pageType Loại trang (post type), vd: tour, product.
     * @param int    $postId   ID bài viết đang xem, 0 nếu không có.
     */
    public static function forPage(string $pageType, int $postId = 0): array
    {
        $suggestions = self::map()[$pageType] ?? self::defaults();

        /**
         * Filter để tùy chỉnh suggestions theo context.
         *
         * @param array  $suggestions Danh sách gợi ý.
         * @param string $pageType    Loại trang.
         * @param int    $postId      ID bài viết.
         */
        return (array) apply_filters('jankx_ai_chatbox_suggestions', $suggestions, $pageType, $postId);
    }
}
