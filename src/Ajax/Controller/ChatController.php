<?php

namespace Jankx\Extensions\AiChatbox\Ajax\Controller;

use Jankx\Ajax\Controller\AbstractController;
use Jankx\Extensions\AiChatbox\Services\SuggestionService;

/**
 * Fast AJAX controller của AI Chatbox.
 *
 * Namespace `ai` + controller `chat` đến từ manifest.json
 * (`ajax_slug` / `ajax_namespace`), nên URL là:
 *
 *   GET /jankx-ajax/ai/chat/suggestions?page_type=tour
 *
 * Route này không dùng NonceMiddleware: endpoint chỉ đọc dữ liệu tĩnh và
 * permission_callback của bản REST cũng là __return_true, nên bắt buộc CSRF
 * chỉ làm thêm một round-trip cho tất cả khách.
 */
class ChatController extends AbstractController
{
    protected array $middlewares = [];

    /**
     * GET /jankx-ajax/ai/chat/suggestions?page_type=tour&post_id=123
     */
    public function suggestions(array $params = []): void
    {
        $pageType = (string) $this->input('page_type', 'default');
        $postId   = (int) $this->input('post_id', 0);

        $this->success([
            'suggestions' => SuggestionService::forPage($pageType, $postId),
        ]);
    }
}
