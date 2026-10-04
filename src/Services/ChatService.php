<?php

namespace Jankx\Extensions\AiChatbox\Services;

use Jankx\Extensions\AiChatbox\Factory\AiProviderFactory;
use Jankx\Extensions\AiChatbox\Contracts\AiProviderInterface;

/**
 * ChatService – Orchestration layer.
 *
 * Lớp này không phụ thuộc vào bất kỳ AI provider nào cụ thể.
 * Nó nhận provider qua Factory, xử lý business logic, và delegate
 * việc gọi API cho provider.
 */
class ChatService
{
    protected AiProviderInterface $provider;
    protected string $systemPrompt;

    public function __construct(?AiProviderInterface $provider = null)
    {
        $this->provider     = $provider ?? AiProviderFactory::make();
        $this->systemPrompt = get_option(
            'jankx_ai_chatbox_system_prompt',
            'Bạn là trợ lý AI của nibitour.vn – website tour du lịch Việt Nam. ' .
            'Hãy giúp khách hàng tìm hiểu về các tour du lịch, điểm đến, giá tour và đơn hàng. ' .
            'Trả lời ngắn gọn, thân thiện bằng tiếng Việt.'
        );
    }

    /**
     * Xử lý một lượt chat.
     *
     * @param string $message  Tin nhắn người dùng.
     * @param array  $history  Lịch sử hội thoại.
     * @param array  $context  Context trang hiện tại (title, url, type...).
     * @param array  $filters  Bộ lọc tour (destination, budget, duration...).
     *
     * @return string|\WP_Error
     */
    public function sendMessage(
        string $message,
        array $history = [],
        array $context = [],
        array $filters = []
    ) {
        $enrichedPrompt = $this->buildSystemPromptWithFilters($filters);

        /**
         * Filter để can thiệp vào tin nhắn trước khi gửi lên AI.
         *
         * @param string $message  Tin nhắn gốc.
         * @param array  $context  Context trang.
         * @param array  $filters  Bộ lọc đang áp dụng.
         */
        $message = apply_filters('jankx_ai_chatbox_before_send', $message, $context, $filters);

        $result = $this->provider->chat($message, $history, $enrichedPrompt, $context);

        if (is_wp_error($result)) {
            return $result;
        }

        /**
         * Filter để xử lý hoặc format phản hồi từ AI.
         *
         * @param string $result   Phản hồi từ AI.
         * @param string $message  Tin nhắn người dùng đã gửi.
         */
        return apply_filters('jankx_ai_chatbox_after_response', $result, $message);
    }

    /**
     * Trả về thông tin provider đang được sử dụng.
     */
    public function getProviderInfo(): array
    {
        return [
            'name'          => $this->provider->getProviderName(),
            'is_configured' => $this->provider->isConfigured(),
        ];
    }

    /**
     * Bổ sung thông tin bộ lọc vào system prompt.
     */
    protected function buildSystemPromptWithFilters(array $filters): string
    {
        $prompt = $this->systemPrompt;

        $activeFilters = array_filter($filters);
        if (empty($activeFilters)) {
            return $prompt;
        }

        $filterLines = [];

        $labelMap = [
            'destination' => 'Điểm đến',
            'for_whom'    => 'Dành cho',
            'budget'      => 'Ngân sách',
            'duration'    => 'Thời gian',
            'discount'    => 'Giảm giá',
            'tour_type'   => 'Loại hình tour',
        ];

        foreach ($activeFilters as $key => $value) {
            $label         = $labelMap[$key] ?? $key;
            $filterLines[] = "- {$label}: {$value}";
        }

        return $prompt . "\n\nBộ lọc khách hàng đang áp dụng:\n" . implode("\n", $filterLines) .
               "\nHãy ưu tiên gợi ý tour phù hợp với các tiêu chí trên.";
    }
}
