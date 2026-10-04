<?php

namespace Jankx\Extensions\AiChatbox\Providers;

use Jankx\Extensions\AiChatbox\Contracts\AiProviderInterface;

/**
 * AbstractAiProvider – Base class chứa shared logic cho mọi provider.
 *
 * Concrete providers chỉ cần override callApi() và getProviderName().
 */
abstract class AbstractAiProvider implements AiProviderInterface
{
    /** @var string API key của provider */
    protected string $apiKey;

    /** @var int Số lượt hội thoại tối đa giữ trong context */
    protected int $maxHistoryTurns = 10;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * {@inheritdoc}
     */
    public function chat(
        string $message,
        array $history = [],
        string $systemPrompt = '',
        array $context = []
    ) {
        if (!$this->isConfigured()) {
            return new \WP_Error(
                'provider_not_configured',
                sprintf(
                    /* translators: %s: provider name */
                    __('Provider "%s" chưa được cấu hình (thiếu API key).', 'jankx'),
                    $this->getProviderName()
                )
            );
        }

        $enrichedMessage = $this->enrichMessageWithContext($message, $context);
        $trimmedHistory  = array_slice($history, -$this->maxHistoryTurns);

        return $this->callApi($enrichedMessage, $trimmedHistory, $systemPrompt);
    }

    /**
     * Gọi API của provider cụ thể. Mỗi provider tự implement phần này.
     *
     * @return string|\WP_Error
     */
    abstract protected function callApi(
        string $message,
        array $history,
        string $systemPrompt
    );

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Gắn thêm thông tin trang hiện tại vào đầu tin nhắn nếu có context.
     */
    protected function enrichMessageWithContext(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $parts = [];

        if (!empty($context['title'])) {
            $parts[] = 'Trang đang xem: ' . $context['title'];
        }
        if (!empty($context['type'])) {
            $parts[] = 'Loại nội dung: ' . $context['type'];
        }
        if (!empty($context['url'])) {
            $parts[] = 'URL: ' . $context['url'];
        }

        if (empty($parts)) {
            return $message;
        }

        return '[' . implode(' | ', $parts) . "]\n\n" . $message;
    }

    /**
     * Normalise history role: 'assistant' → tên role của provider.
     *
     * @param string $role       'user' hoặc 'assistant'
     * @param string $modelRole  Tên role 'model' phía provider dùng (default 'assistant')
     */
    protected function normalizeRole(string $role, string $modelRole = 'assistant'): string
    {
        return $role === 'assistant' ? $modelRole : 'user';
    }
}
