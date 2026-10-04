<?php

namespace Jankx\Extensions\AiChatbox\Factory;

use Jankx\Extensions\AiChatbox\Contracts\AiProviderInterface;
use Jankx\Extensions\AiChatbox\Providers\GeminiProvider;
use Jankx\Extensions\AiChatbox\Providers\OpenAiProvider;

/**
 * AiProviderFactory – tạo đúng provider dựa theo WordPress settings.
 *
 * Để thêm provider mới, chỉ cần:
 *   1. Tạo class implement AiProviderInterface (hoặc extend AbstractAiProvider).
 *   2. Đăng ký tại filter 'jankx_ai_chatbox_providers'.
 *   3. Thêm case vào make() hoặc để filter handle.
 */
class AiProviderFactory
{
    /**
     * Tạo provider theo $providerName và config từ WP options.
     *
     * @param string|null $providerName  Tên provider ('gemini', 'openai'...).
     *                                   Nếu null, đọc từ option `jankx_ai_chatbox_provider`.
     *
     * @return AiProviderInterface
     * @throws \RuntimeException  Khi provider không được hỗ trợ.
     */
    public static function make(?string $providerName = null): AiProviderInterface
    {
        $providerName = $providerName ?? get_option('jankx_ai_chatbox_provider', 'gemini');

        /**
         * Filter cho phép plugin/theme bên ngoài đăng ký thêm provider.
         *
         * @param AiProviderInterface|null $provider      Provider đã tạo (null = chưa xử lý).
         * @param string                   $providerName  Tên provider được yêu cầu.
         */
        $externalProvider = apply_filters('jankx_ai_chatbox_make_provider', null, $providerName);

        if ($externalProvider instanceof AiProviderInterface) {
            return $externalProvider;
        }

        switch ($providerName) {
            case 'gemini':
                return self::makeGemini();

            case 'openai':
                return self::makeOpenAi();

            case 'ollama':
                // Ollama dùng chuẩn OpenAI với custom base URL và không cần API key
                return new OpenAiProvider(
                    apiKey:  get_option('jankx_ai_chatbox_ollama_api_key', 'ollama'),
                    model:   get_option('jankx_ai_chatbox_ollama_model', 'llama3'),
                    baseUrl: get_option('jankx_ai_chatbox_ollama_base_url', 'http://localhost:11434/v1')
                );

            default:
                throw new \RuntimeException(
                    sprintf('AI provider "%s" không được hỗ trợ.', $providerName)
                );
        }
    }

    /**
     * Trả về danh sách tất cả provider đã đăng ký.
     * Dùng cho admin settings dropdown.
     *
     * @return array<string, string>  ['provider_id' => 'Label']
     */
    public static function getRegisteredProviders(): array
    {
        $defaults = [
            'gemini' => 'Google Gemini (Miễn phí)',
            'openai' => 'OpenAI (GPT-4o, GPT-4o-mini...)',
            'ollama' => 'Ollama (Local – Miễn phí)',
        ];

        /**
         * Filter để thêm provider tùy chỉnh vào danh sách.
         *
         * @param array $providers  Danh sách provider hiện tại.
         */
        return apply_filters('jankx_ai_chatbox_registered_providers', $defaults);
    }

    // -------------------------------------------------------------------------
    // Private factory helpers
    // -------------------------------------------------------------------------

    private static function makeGemini(): GeminiProvider
    {
        return new GeminiProvider(
            apiKey: get_option('jankx_ai_chatbox_gemini_api_key', ''),
            model:  get_option('jankx_ai_chatbox_gemini_model', 'gemini-3.8-flash')
        );
    }

    private static function makeOpenAi(): OpenAiProvider
    {
        return new OpenAiProvider(
            apiKey:  get_option('jankx_ai_chatbox_openai_api_key', ''),
            model:   get_option('jankx_ai_chatbox_openai_model', 'gpt-4o-mini'),
            baseUrl: get_option('jankx_ai_chatbox_openai_base_url', 'https://api.openai.com/v1')
        );
    }
}
