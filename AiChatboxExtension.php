<?php

namespace Jankx\Extensions\AiChatbox;

use Jankx\Extensions\AbstractExtension;
use Jankx\Extensions\AiChatbox\Api\ChatRestController;
use Jankx\Extensions\AiChatbox\Admin\SettingsPage;

/**
 * AI Chatbox Extension
 *
 * Tích hợp trợ lý AI sử dụng Google Gemini API vào nibitour.vn,
 * hỗ trợ khách hàng hỏi về sản phẩm, tour du lịch và đơn hàng.
 */
class AiChatboxExtension extends AbstractExtension
{
    protected static ?self $instance = null;

    public function __construct()
    {
        $this->registerAutoloader();
        parent::__construct();
    }

    protected function registerAutoloader(): void
    {
        spl_autoload_register(function ($class) {
            $prefix   = 'Jankx\\Extensions\\AiChatbox\\';
            $base_dir = __DIR__ . '/src/';
            $len      = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        // REST API endpoint để nhận tin nhắn và trả về phản hồi AI
        $chatController = new ChatRestController();
        $chatController->register();

        // Admin settings
        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();
        }

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // Inject chatbox HTML vào footer
        add_action('wp_footer', [$this, 'renderChatbox']);
    }

    public function enqueueFrontendAssets(): void
    {
        $ext_url = $this->getExtensionUrl();

        wp_enqueue_style(
            'jankx-ai-chatbox',
            $ext_url . '/assets/css/chatbox.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'jankx-ai-chatbox',
            $ext_url . '/assets/js/chatbox.js',
            [],
            '1.0.0',
            true
        );

        $api_key = get_option('jankx_ai_chatbox_gemini_api_key', '');
        $bot_name = get_option('jankx_ai_chatbox_bot_name', 'Trợ lý AI');
        $system_prompt = get_option(
            'jankx_ai_chatbox_system_prompt',
            'Bạn là trợ lý AI của nibitour.vn. Hãy giúp khách hàng tìm hiểu về các tour du lịch, sản phẩm và đơn hàng. Trả lời bằng tiếng Việt.'
        );

        wp_localize_script('jankx-ai-chatbox', 'jankxAiChatbox', [
            'restUrl'      => rest_url('jankx/v1/ai-chat'),
            // Fast AJAX. Rỗng khi theme chưa bật /jankx-ajax – JS sẽ tự
            // rơi về restUrl.
            'ajaxUrl'      => function_exists('jankx_ajax_url') ? jankx_ajax_url() : '',
            'ajaxNonce'    => wp_create_nonce('jankx_ajax'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'botName'      => esc_js($bot_name),
            'systemPrompt' => esc_js($system_prompt),
            'currentPage'  => [
                'title' => get_the_title(),
                'url'   => get_permalink(),
                'type'  => get_post_type(),
            ],
            'i18n' => [
                'placeholder'   => __('Hỏi sản phẩm, đơn hàng...', 'jankx'),
                'disclaimer'    => __('Trợ lý AI có thể trả lời chưa chính xác.', 'jankx'),
                'askAboutPage'  => __('Hỏi về trang này', 'jankx'),
                'relatedTours'  => __('Tour liên quan', 'jankx'),
                'greeting'      => __('Bạn đang xem trang này', 'jankx'),
                'greetingDesc'  => __('Bạn muốn tóm tắt nội dung hay tìm tour liên quan?', 'jankx'),
                'errorMessage'  => __('Xin lỗi, có lỗi xảy ra. Vui lòng thử lại.', 'jankx'),
                'thinking'      => __('Đang xử lý...', 'jankx'),
            ],
        ]);
    }

    /**
     * Trả về URL của extension directory.
     */
    protected function getExtensionUrl(): string
    {
        $theme_uri = get_stylesheet_directory_uri();
        return $theme_uri . '/extensions/ai-chatbox';
    }

    public function renderChatbox(): void
    {
        $template = __DIR__ . '/templates/chatbox.php';
        if (file_exists($template)) {
            include $template;
        }
    }
}
