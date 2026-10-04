<?php

namespace Jankx\Extensions\AiChatbox\Admin;

use Jankx\Extensions\AiChatbox\Factory\AiProviderFactory;

/**
 * Trang cài đặt AI Chatbox trong WordPress Admin.
 */
class SettingsPage
{
    protected string $optionGroup = 'jankx_ai_chatbox_settings';
    protected string $pageSlug    = 'jankx-ai-chatbox-settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_options_page(
            __('AI Chatbox Settings', 'jankx'),
            __('AI Chatbox', 'jankx'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        $text    = ['type' => 'string',  'sanitize_callback' => 'sanitize_text_field'];
        $textarea= ['type' => 'string',  'sanitize_callback' => 'sanitize_textarea_field'];
        $bool    = ['type' => 'boolean'];
        $url     = ['type' => 'string',  'sanitize_callback' => 'esc_url_raw'];

        $options = [
            // General
            'jankx_ai_chatbox_enabled'        => $bool    + ['default' => true],
            'jankx_ai_chatbox_bot_name'       => $text    + ['default' => 'Trợ lý AI'],
            'jankx_ai_chatbox_pill_label'     => $text    + ['default' => 'Mua cùng AI'],
            'jankx_ai_chatbox_subtitle'       => $text    + ['default' => ''],
            'jankx_ai_chatbox_system_prompt'  => $textarea+ ['default' => 'Bạn là trợ lý AI của nibitour.vn – website tour du lịch. Trả lời ngắn gọn, thân thiện bằng tiếng Việt.'],
            // Provider selector
            'jankx_ai_chatbox_provider'       => $text    + ['default' => 'gemini'],
            // Gemini
            'jankx_ai_chatbox_gemini_api_key' => $text    + ['default' => ''],
            'jankx_ai_chatbox_gemini_model'   => $text    + ['default' => 'gemini-1.5-flash'],
            // OpenAI
            'jankx_ai_chatbox_openai_api_key' => $text    + ['default' => ''],
            'jankx_ai_chatbox_openai_model'   => $text    + ['default' => 'gpt-4o-mini'],
            'jankx_ai_chatbox_openai_base_url'=> $url     + ['default' => 'https://api.openai.com/v1'],
            // Ollama
            'jankx_ai_chatbox_ollama_model'   => $text    + ['default' => 'llama3'],
            'jankx_ai_chatbox_ollama_base_url'=> $url     + ['default' => 'http://localhost:11434/v1'],
        ];

        foreach ($options as $name => $args) {
            register_setting($this->optionGroup, $name, $args);
        }
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $current_provider = get_option('jankx_ai_chatbox_provider', 'gemini');
        $providers        = AiProviderFactory::getRegisteredProviders();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Chatbox Settings', 'jankx'); ?></h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Đã lưu cài đặt thành công!', 'jankx'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields($this->optionGroup); ?>

                <!-- ======================================================== -->
                <h2 class="title"><?php esc_html_e('Cài đặt chung', 'jankx'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Kích hoạt chatbox', 'jankx'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="jankx_ai_chatbox_enabled" value="1"
                                    <?php checked(get_option('jankx_ai_chatbox_enabled', 1)); ?>>
                                <?php esc_html_e('Hiển thị chatbox trên frontend', 'jankx'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cb_bot_name"><?php esc_html_e('Tên trợ lý AI', 'jankx'); ?></label></th>
                        <td>
                            <input type="text" id="cb_bot_name" name="jankx_ai_chatbox_bot_name" class="regular-text"
                                value="<?php echo esc_attr(get_option('jankx_ai_chatbox_bot_name', 'Trợ lý AI')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cb_pill_label"><?php esc_html_e('Pill label', 'jankx'); ?></label></th>
                        <td>
                            <input type="text" id="cb_pill_label" name="jankx_ai_chatbox_pill_label" class="regular-text"
                                value="<?php echo esc_attr(get_option('jankx_ai_chatbox_pill_label', 'Mua cùng AI')); ?>">
                            <p class="description"><?php esc_html_e('Văn bản hiển thị trên pill (ví dụ: "Mua cùng AI").', 'jankx'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cb_subtitle"><?php esc_html_e('Subtitle', 'jankx'); ?></label></th>
                        <td>
                            <input type="text" id="cb_subtitle" name="jankx_ai_chatbox_subtitle" class="large-text"
                                value="<?php echo esc_attr(get_option('jankx_ai_chatbox_subtitle', '')); ?>">
                            <p class="description"><?php esc_html_e('Dòng chữ nhỏ bên dưới header (bỏ trống = dùng tên blog).', 'jankx'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="cb_system_prompt"><?php esc_html_e('System Prompt', 'jankx'); ?></label></th>
                        <td>
                            <textarea id="cb_system_prompt" name="jankx_ai_chatbox_system_prompt"
                                class="large-text" rows="5"><?php echo esc_textarea(get_option('jankx_ai_chatbox_system_prompt')); ?></textarea>
                            <p class="description"><?php esc_html_e('Định nghĩa nhân cách và nhiệm vụ của trợ lý AI.', 'jankx'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- ======================================================== -->
                <h2 class="title"><?php esc_html_e('AI Provider', 'jankx'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="cb_provider"><?php esc_html_e('Chọn AI Provider', 'jankx'); ?></label></th>
                        <td>
                            <select id="cb_provider" name="jankx_ai_chatbox_provider"
                                onchange="document.querySelectorAll('.cb-provider-section').forEach(s => s.style.display='none'); document.getElementById('cb-section-'+this.value).style.display=''">
                                <?php foreach ($providers as $id => $label): ?>
                                    <option value="<?php echo esc_attr($id); ?>" <?php selected($current_provider, $id); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Gemini -->
                <div id="cb-section-gemini" class="cb-provider-section"
                    style="<?php echo $current_provider !== 'gemini' ? 'display:none' : ''; ?>">
                    <h3><?php esc_html_e('Google Gemini', 'jankx'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cb_gemini_key"><?php esc_html_e('API Key', 'jankx'); ?></label></th>
                            <td>
                                <input type="password" id="cb_gemini_key" name="jankx_ai_chatbox_gemini_api_key" class="regular-text" autocomplete="new-password"
                                    value="<?php echo esc_attr(get_option('jankx_ai_chatbox_gemini_api_key', '')); ?>">
                                <p class="description">
                                    <?php printf(esc_html__('Lấy miễn phí tại %s', 'jankx'), '<a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cb_gemini_model"><?php esc_html_e('Model', 'jankx'); ?></label></th>
                            <td>
                                <select id="cb_gemini_model" name="jankx_ai_chatbox_gemini_model">
                                    <?php
                                    $cur = get_option('jankx_ai_chatbox_gemini_model', 'gemini-1.5-flash');
                                    foreach ([
                                        'gemini-1.5-flash'  => 'Gemini 1.5 Flash (Nhanh · Miễn phí)',
                                        'gemini-1.5-pro'    => 'Gemini 1.5 Pro (Mạnh hơn)',
                                        'gemini-2.0-flash'  => 'Gemini 2.0 Flash (Mới nhất)',
                                    ] as $val => $lbl):
                                        printf('<option value="%s"%s>%s</option>',
                                            esc_attr($val), selected($cur, $val, false), esc_html($lbl));
                                    endforeach;
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- OpenAI -->
                <div id="cb-section-openai" class="cb-provider-section"
                    style="<?php echo $current_provider !== 'openai' ? 'display:none' : ''; ?>">
                    <h3><?php esc_html_e('OpenAI', 'jankx'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cb_openai_key"><?php esc_html_e('API Key', 'jankx'); ?></label></th>
                            <td>
                                <input type="password" id="cb_openai_key" name="jankx_ai_chatbox_openai_api_key" class="regular-text" autocomplete="new-password"
                                    value="<?php echo esc_attr(get_option('jankx_ai_chatbox_openai_api_key', '')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cb_openai_model"><?php esc_html_e('Model', 'jankx'); ?></label></th>
                            <td>
                                <select id="cb_openai_model" name="jankx_ai_chatbox_openai_model">
                                    <?php
                                    $cur = get_option('jankx_ai_chatbox_openai_model', 'gpt-4o-mini');
                                    foreach ([
                                        'gpt-4o-mini' => 'GPT-4o Mini (Rẻ · Nhanh)',
                                        'gpt-4o'      => 'GPT-4o (Mạnh nhất)',
                                        'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Tiết kiệm)',
                                    ] as $val => $lbl):
                                        printf('<option value="%s"%s>%s</option>',
                                            esc_attr($val), selected($cur, $val, false), esc_html($lbl));
                                    endforeach;
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cb_openai_url"><?php esc_html_e('Base URL', 'jankx'); ?></label></th>
                            <td>
                                <input type="url" id="cb_openai_url" name="jankx_ai_chatbox_openai_base_url" class="regular-text"
                                    value="<?php echo esc_attr(get_option('jankx_ai_chatbox_openai_base_url', 'https://api.openai.com/v1')); ?>">
                                <p class="description"><?php esc_html_e('Thay đổi để dùng API tương thích OpenAI khác (Ollama qua OpenAI mode, Groq...).', 'jankx'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Ollama -->
                <div id="cb-section-ollama" class="cb-provider-section"
                    style="<?php echo $current_provider !== 'ollama' ? 'display:none' : ''; ?>">
                    <h3><?php esc_html_e('Ollama (Local)', 'jankx'); ?></h3>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><label for="cb_ollama_url"><?php esc_html_e('Base URL', 'jankx'); ?></label></th>
                            <td>
                                <input type="url" id="cb_ollama_url" name="jankx_ai_chatbox_ollama_base_url" class="regular-text"
                                    value="<?php echo esc_attr(get_option('jankx_ai_chatbox_ollama_base_url', 'http://localhost:11434/v1')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cb_ollama_model"><?php esc_html_e('Model', 'jankx'); ?></label></th>
                            <td>
                                <input type="text" id="cb_ollama_model" name="jankx_ai_chatbox_ollama_model" class="regular-text"
                                    value="<?php echo esc_attr(get_option('jankx_ai_chatbox_ollama_model', 'llama3')); ?>">
                                <p class="description"><?php esc_html_e('Tên model đã pull về Ollama (vd: llama3, mistral, gemma2...).', 'jankx'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
            </form>
        </div>
        <?php
    }
}
