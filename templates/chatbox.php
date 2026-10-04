<?php
/**
 * Template chatbox – inject vào wp_footer.
 * Chỉ render khi option enabled hoặc chưa có API key (vẫn hiển thị để admin test).
 */
$enabled  = get_option('jankx_ai_chatbox_enabled', 1);
$bot_name = get_option('jankx_ai_chatbox_bot_name', 'Trợ lý AI');
$subtitle = get_option('jankx_ai_chatbox_subtitle', get_bloginfo('name') . ' · Trợ lý thông minh');

if (!$enabled && !current_user_can('manage_options')) {
    return;
}
?>

<div id="jankx-ai-chatbox" class="jankx-chatbox" data-state="icon" role="dialog" aria-label="<?php echo esc_attr($bot_name); ?>" aria-hidden="true">

    <!-- ============================================================
         STATE 1: Icon button (collapsed)
    ============================================================ -->
    <button
        id="jankx-chatbox-icon-btn"
        class="jankx-chatbox__icon-btn"
        aria-label="<?php echo esc_attr($bot_name); ?>"
        title="<?php echo esc_attr($bot_name); ?>"
    >
        <span class="jankx-chatbox__icon-btn-inner" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L13.5 8.5L20 10L13.5 11.5L12 18L10.5 11.5L4 10L10.5 8.5L12 2Z" fill="currentColor"/>
                <path d="M19 16L19.75 18.25L22 19L19.75 19.75L19 22L18.25 19.75L16 19L18.25 18.25L19 16Z" fill="currentColor" opacity="0.7"/>
            </svg>
        </span>
        <span class="jankx-chatbox__pulse" aria-hidden="true"></span>
    </button>

    <!-- ============================================================
         STATE 2: Pill label ("Mua cùng AI")
    ============================================================ -->
    <div id="jankx-chatbox-pill" class="jankx-chatbox__pill" role="button" tabindex="0" aria-label="<?php echo esc_attr($bot_name); ?>">
        <span class="jankx-chatbox__pill-label">
            <?php echo esc_html(get_option('jankx_ai_chatbox_pill_label', 'Mua cùng AI')); ?>
        </span>
        <span class="jankx-chatbox__pill-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L13.5 8.5L20 10L13.5 11.5L12 18L10.5 11.5L4 10L10.5 8.5L12 2Z" fill="currentColor"/>
                <path d="M19 16L19.75 18.25L22 19L19.75 19.75L19 22L18.25 19.75L16 19L18.25 18.25L19 16Z" fill="currentColor" opacity="0.7"/>
            </svg>
        </span>
    </div>

    <!-- ============================================================
         STATE 3: Panel chatbox đầy đủ (expanded)
    ============================================================ -->
    <div id="jankx-chatbox-panel" class="jankx-chatbox__panel" aria-hidden="true">

        <!-- Header -->
        <div class="jankx-chatbox__header">
            <div class="jankx-chatbox__header-left">
                <div class="jankx-chatbox__avatar" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L13.5 8.5L20 10L13.5 11.5L12 18L10.5 11.5L4 10L10.5 8.5L12 2Z" fill="currentColor"/>
                        <path d="M19 16L19.75 18.25L22 19L19.75 19.75L19 22L18.25 19.75L16 19L18.25 18.25L19 16Z" fill="currentColor" opacity="0.7"/>
                    </svg>
                </div>
                <span class="jankx-chatbox__bot-name"><?php echo esc_html($bot_name); ?></span>
            </div>
            <div class="jankx-chatbox__header-actions">
                <button class="jankx-chatbox__header-btn" id="jankx-chatbox-clear-btn" title="<?php esc_attr_e('Xóa lịch sử chat', 'jankx'); ?>" aria-label="<?php esc_attr_e('Xóa lịch sử chat', 'jankx'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" xmlns="http://www.w3.org/2000/svg">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 .49-3.93"></path>
                    </svg>
                </button>
                <button class="jankx-chatbox__header-btn" id="jankx-chatbox-minimize-btn" title="<?php esc_attr_e('Thu nhỏ', 'jankx'); ?>" aria-label="<?php esc_attr_e('Thu nhỏ', 'jankx'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" xmlns="http://www.w3.org/2000/svg">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </button>
                <button class="jankx-chatbox__header-btn" id="jankx-chatbox-close-btn" title="<?php esc_attr_e('Đóng', 'jankx'); ?>" aria-label="<?php esc_attr_e('Đóng', 'jankx'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" xmlns="http://www.w3.org/2000/svg">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Subtitle -->
        <div class="jankx-chatbox__subtitle">
            <?php echo esc_html($subtitle); ?>
        </div>

        <!-- Messages area -->
        <div class="jankx-chatbox__messages" id="jankx-chatbox-messages" role="log" aria-live="polite" aria-label="<?php esc_attr_e('Cuộc hội thoại', 'jankx'); ?>">
            <!-- Greeting card (initial state) -->
            <div class="jankx-chatbox__greeting" id="jankx-chatbox-greeting">
                <div class="jankx-chatbox__greeting-title">
                    <?php esc_html_e('Bạn đang xem trang này', 'jankx'); ?>
                </div>
                <div class="jankx-chatbox__greeting-desc">
                    <?php esc_html_e('Bạn muốn tóm tắt nội dung hay tìm tour liên quan?', 'jankx'); ?>
                </div>
                <div class="jankx-chatbox__suggestions" id="jankx-chatbox-suggestions">
                    <button class="jankx-chatbox__suggestion-btn" data-query="Tóm tắt nội dung trang này cho tôi">
                        <?php esc_html_e('Hỏi về trang này', 'jankx'); ?>
                    </button>
                    <button class="jankx-chatbox__suggestion-btn" data-query="Gợi ý các tour du lịch liên quan">
                        <?php esc_html_e('Tour liên quan', 'jankx'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Input area -->
        <div class="jankx-chatbox__input-area">
            <div class="jankx-chatbox__input-wrapper">
                <textarea
                    id="jankx-chatbox-input"
                    class="jankx-chatbox__input"
                    placeholder="<?php esc_attr_e('Hỏi sản phẩm, đơn hàng...', 'jankx'); ?>"
                    rows="1"
                    aria-label="<?php esc_attr_e('Nhập tin nhắn', 'jankx'); ?>"
                    maxlength="1000"
                ></textarea>
                <button
                    id="jankx-chatbox-send-btn"
                    class="jankx-chatbox__send-btn"
                    aria-label="<?php esc_attr_e('Gửi tin nhắn', 'jankx'); ?>"
                    disabled
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" xmlns="http://www.w3.org/2000/svg">
                        <line x1="12" y1="19" x2="12" y2="5"></line>
                        <polyline points="5 12 12 5 19 12"></polyline>
                    </svg>
                </button>
            </div>
            <p class="jankx-chatbox__disclaimer">
                <?php esc_html_e('Trợ lý AI có thể trả lời chưa chính xác.', 'jankx'); ?>
                <strong><?php esc_html_e('Không thay thế tư vấn y tế.', 'jankx'); ?></strong>
            </p>
        </div>
    </div>

</div><!-- #jankx-ai-chatbox -->
