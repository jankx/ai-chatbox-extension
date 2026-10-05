<?php

namespace Jankx\Extensions\AiChatbox\Providers;

/**
 * GeminiProvider – Google Gemini API implementation.
 *
 * Docs: https://ai.google.dev/api/generate-content
 */
class GeminiProvider extends AbstractAiProvider
{
    protected string $model;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(string $apiKey, string $model = 'gemini-3.8-flash')
    {
        parent::__construct($apiKey);
        $this->model = $model;
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    /**
     * {@inheritdoc}
     */
    protected function callApi(string $message, array $history, string $systemPrompt)
    {
        $contents = $this->buildContents($message, $history);

        $generationConfig = ['maxOutputTokens' => 1024];

        // Gemini 3.x đã deprecate các tham số sampling (temperature, top_p, top_k).
        if (strpos($this->model, 'gemini-3') !== 0) {
            $generationConfig['temperature'] = 0.7;
        }

        $body = [
            'contents'         => $contents,
            'generationConfig' => $generationConfig,
        ];

        if (!empty($systemPrompt)) {
            $body['systemInstruction'] = [
                'parts' => [['text' => $systemPrompt]],
            ];
        }

        $url      = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            $msg = $data['error']['message'] ?? __('Lỗi không xác định từ Gemini API.', 'jankx');
            return new \WP_Error('gemini_error', $msg, ['status' => $code]);
        }

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($text)) {
            return new \WP_Error('gemini_empty', __('Gemini API trả về phản hồi rỗng.', 'jankx'));
        }

        return $text;
    }

    /**
     * Chuyển đổi history sang định dạng Gemini (role: user/model).
     */
    private function buildContents(string $message, array $history): array
    {
        $contents = [];

        foreach ($history as $turn) {
            $role       = $this->normalizeRole($turn['role'] ?? 'user', 'model');
            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => $turn['text'] ?? '']],
            ];
        }

        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $message]],
        ];

        return $contents;
    }
}
