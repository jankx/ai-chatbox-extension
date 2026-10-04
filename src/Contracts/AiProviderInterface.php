<?php

namespace Jankx\Extensions\AiChatbox\Contracts;

/**
 * AiProviderInterface – Contract cho mọi AI provider.
 *
 * Bất kỳ provider nào (Gemini, OpenAI, Claude, Ollama...) đều phải
 * implement interface này để có thể hoán đổi qua Factory mà không
 * cần thay đổi code ở tầng trên (ChatService, REST controller...).
 */
interface AiProviderInterface
{
    /**
     * Gửi một lượt chat và nhận phản hồi dạng text thuần.
     *
     * @param string $message       Tin nhắn hiện tại của người dùng.
     * @param array  $history       Lịch sử hội thoại. Mỗi phần tử là:
     *                              ['role' => 'user'|'assistant', 'text' => string]
     * @param string $systemPrompt  Hướng dẫn hành vi cho AI (system instruction).
     * @param array  $context       Metadata bổ sung: tên trang, URL, post_type...
     *
     * @return string|\WP_Error  Nội dung phản hồi hoặc WP_Error nếu thất bại.
     */
    public function chat(
        string $message,
        array $history = [],
        string $systemPrompt = '',
        array $context = []
    );

    /**
     * Trả về tên định danh của provider (ví dụ: 'gemini', 'openai', 'claude').
     */
    public function getProviderName(): string;

    /**
     * Kiểm tra xem provider đã được cấu hình đầy đủ chưa (có API key...).
     */
    public function isConfigured(): bool;
}
