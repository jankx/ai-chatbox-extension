<?php

namespace Jankx\Extensions\AiChatbox\Api;

use Jankx\Extensions\AiChatbox\Services\ChatService;
use Jankx\Extensions\AiChatbox\Factory\AiProviderFactory;

/**
 * REST Controller xử lý toàn bộ API endpoints của AI Chatbox.
 */
class ChatRestController
{
    protected string $namespace = 'jankx/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // Gửi tin nhắn chat
        register_rest_route($this->namespace, '/ai-chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleChat'],
            'permission_callback' => '__return_true',
            'args'                => [
                'message' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'history' => [
                    'type'    => 'array',
                    'default' => [],
                ],
                'context' => [
                    'type'    => 'object',
                    'default' => [],
                ],
                'filters' => [
                    'type'    => 'object',
                    'default' => [],
                ],
            ],
        ]);

        // Lấy câu hỏi gợi ý theo loại trang
        register_rest_route($this->namespace, '/ai-chat/suggestions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSuggestions'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page_type' => ['type' => 'string', 'default' => 'default'],
                'post_id'   => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        // Lấy options bộ lọc tour
        register_rest_route($this->namespace, '/ai-chat/filter-options', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getFilterOptions'],
            'permission_callback' => '__return_true',
        ]);

        // Thông tin provider đang dùng
        register_rest_route($this->namespace, '/ai-chat/provider-info', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getProviderInfo'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);
    }

    // -------------------------------------------------------------------------

    public function handleChat(\WP_REST_Request $request): \WP_REST_Response
    {
        $message = trim($request->get_param('message'));

        if (empty($message)) {
            return new \WP_REST_Response(
                ['error' => __('Tin nhắn không được để trống.', 'jankx')],
                400
            );
        }

        try {
            $chatService = new ChatService();
            $result      = $chatService->sendMessage(
                $message,
                (array) $request->get_param('history'),
                (array) $request->get_param('context'),
                (array) $request->get_param('filters')
            );
        } catch (\RuntimeException $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }

        if (is_wp_error($result)) {
            return new \WP_REST_Response(
                ['error' => $result->get_error_message()],
                500
            );
        }

        return new \WP_REST_Response([
            'reply'     => $result,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function getSuggestions(\WP_REST_Request $request): \WP_REST_Response
    {
        $page_type = $request->get_param('page_type');
        $post_id   = (int) $request->get_param('post_id');

        $map = [
            'product' => [
                ['label' => 'Giá & Ưu đãi',      'query' => 'Tour này giá bao nhiêu và có ưu đãi gì?'],
                ['label' => 'Lịch khởi hành',     'query' => 'Cho tôi biết các ngày khởi hành gần nhất'],
                ['label' => 'Chính sách đặt tour', 'query' => 'Điều kiện và chính sách đặt tour này là gì?'],
            ],
            'tour' => [
                ['label' => 'Lịch trình tour',    'query' => 'Mô tả lịch trình chi tiết tour này'],
                ['label' => 'Giá tour',            'query' => 'Tour này có các mức giá nào?'],
                ['label' => 'Đặt tour ngay',       'query' => 'Tôi muốn đặt tour này, hướng dẫn tôi các bước'],
            ],
            'destination' => [
                ['label' => 'Tour đến đây',        'query' => 'Có những tour nào đến điểm đến này?'],
                ['label' => 'Thời điểm đẹp nhất',  'query' => 'Thời điểm nào trong năm đẹp nhất để đi?'],
                ['label' => 'Gợi ý hành trình',    'query' => 'Gợi ý hành trình tự túc tại đây'],
            ],
        ];

        $suggestions = $map[$page_type] ?? [
            ['label' => 'Hỏi về trang này',  'query' => 'Tóm tắt nội dung trang này cho tôi'],
            ['label' => 'Tour liên quan',     'query' => 'Gợi ý các tour du lịch liên quan'],
        ];

        /**
         * Filter để tùy chỉnh suggestions theo context.
         */
        $suggestions = apply_filters('jankx_ai_chatbox_suggestions', $suggestions, $page_type, $post_id);

        return new \WP_REST_Response(['suggestions' => $suggestions]);
    }

    public function getFilterOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $options = [
            'destination' => [
                'label'   => 'Điểm đến',
                'options' => $this->getDestinationOptions(),
            ],
            'for_whom' => [
                'label'   => 'Dành cho',
                'options' => [
                    ''         => 'Tất cả',
                    'family'   => 'Gia đình',
                    'couple'   => 'Cặp đôi',
                    'solo'     => 'Đi một mình',
                    'group'    => 'Nhóm bạn',
                    'company'  => 'Công ty / Team building',
                ],
            ],
            'budget' => [
                'label'   => 'Ngân sách',
                'options' => [
                    ''              => 'Tất cả',
                    'under_5m'      => 'Dưới 5 triệu',
                    '5m_10m'        => '5 – 10 triệu',
                    '10m_20m'       => '10 – 20 triệu',
                    'above_20m'     => 'Trên 20 triệu',
                ],
            ],
            'duration' => [
                'label'   => 'Thời gian',
                'options' => [
                    ''      => 'Tất cả',
                    '1d'    => '1 ngày',
                    '2d1n'  => '2 ngày 1 đêm',
                    '3d2n'  => '3 ngày 2 đêm',
                    '4d3n'  => '4 ngày 3 đêm',
                    '5d+'   => '5 ngày trở lên',
                ],
            ],
            'discount' => [
                'label'   => 'Giảm giá',
                'options' => [
                    ''    => 'Tất cả',
                    'yes' => 'Đang có khuyến mãi',
                    'hot' => 'Tour hot / Bán chạy',
                ],
            ],
            'tour_type' => [
                'label'   => 'Loại hình tour',
                'options' => [
                    ''          => 'Tất cả',
                    'beach'     => 'Biển đảo',
                    'mountain'  => 'Núi rừng',
                    'cultural'  => 'Văn hóa / Di sản',
                    'adventure' => 'Mạo hiểm',
                    'city'      => 'Thành phố / Mua sắm',
                ],
            ],
        ];

        /**
         * Filter để thêm hoặc chỉnh sửa các filter options.
         */
        $options = apply_filters('jankx_ai_chatbox_filter_options', $options);

        return new \WP_REST_Response($options);
    }

    public function getProviderInfo(\WP_REST_Request $request): \WP_REST_Response
    {
        try {
            $service = new ChatService();
            $info    = $service->getProviderInfo();
            $info['available_providers'] = AiProviderFactory::getRegisteredProviders();
            return new \WP_REST_Response($info);
        } catch (\RuntimeException $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Lấy danh sách điểm đến từ taxonomy hoặc fallback list.
     */
    protected function getDestinationOptions(): array
    {
        $defaults = ['' => 'Tất cả'];

        // Thử lấy từ taxonomy 'destination' nếu có
        if (taxonomy_exists('destination')) {
            $terms = get_terms([
                'taxonomy'   => 'destination',
                'hide_empty' => true,
                'number'     => 50,
            ]);
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $defaults[$term->slug] = $term->name;
                }
                return $defaults;
            }
        }

        // Fallback hardcoded
        return array_merge($defaults, [
            'ha-noi'        => 'Hà Nội',
            'ho-chi-minh'   => 'TP. Hồ Chí Minh',
            'da-nang'       => 'Đà Nẵng',
            'ha-long'       => 'Hạ Long',
            'hoi-an'        => 'Hội An',
            'nha-trang'     => 'Nha Trang',
            'phu-quoc'      => 'Phú Quốc',
            'sa-pa'         => 'Sa Pa',
            'da-lat'        => 'Đà Lạt',
            'quoc-te'       => 'Quốc tế',
        ]);
    }
}
