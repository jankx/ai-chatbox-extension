<?php

namespace Jankx\Extensions\AiChatbox\Providers;

/**
 * OpenAiProvider – OpenAI ChatCompletion API implementation.
 *
 * Docs: https://platform.openai.com/docs/api-reference/chat
 * Tương thích với bất kỳ API nào theo chuẩn OpenAI (Ollama, LM Studio...).
 */
class OpenAiProvider extends AbstractAiProvider
{
    protected string $model;
    protected string $baseUrl;

    public function __construct(
        string $apiKey,
        string $model   = 'gpt-4o-mini',
        string $baseUrl = 'https://api.openai.com/v1'
    ) {
        parent::__construct($apiKey);
        $this->model   = $model;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    /**
     * {@inheritdoc}
     */
    protected function callApi(string $message, array $history, string $systemPrompt)
    {
        $messages = $this->buildMessages($message, $history, $systemPrompt);

        $response = wp_remote_post($this->baseUrl . '/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->apiKey,
            ],
            'body'    => wp_json_encode([
                'model'       => $this->model,
                'messages'    => $messages,
                'temperature' => 0.7,
                'max_tokens'  => 1024,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? __('Lỗi không xác định từ OpenAI API.', 'jankx');
            return new \WP_Error('openai_error', $msg, ['status' => $code]);
        }

        $text = $data['choices'][0]['message']['content'] ?? '';

        if (empty($text)) {
            return new \WP_Error('openai_empty', __('OpenAI API trả về phản hồi rỗng.', 'jankx'));
        }

        return $text;
    }

    /**
     * Chuyển đổi history sang định dạng OpenAI messages array.
     */
    private function buildMessages(string $message, array $history, string $systemPrompt): array
    {
        $messages = [];

        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($history as $turn) {
            $role       = $this->normalizeRole($turn['role'] ?? 'user', 'assistant');
            $messages[] = ['role' => $role, 'content' => $turn['text'] ?? ''];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }
}
