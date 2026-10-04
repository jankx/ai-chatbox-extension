/**
 * Jankx AI Chatbox – chatbox.js
 *
 * Quản lý 3 states: icon → pill → expanded
 * + Options dropdown menu
 * + Filter panel (bộ lọc tour)
 * + Chat với Gemini qua WordPress REST API
 */
(function () {
    'use strict';

    // =========================================================================
    // Config từ wp_localize_script
    // =========================================================================
    const cfg = window.jankxAiChatbox || {};
    const REST_URL    = cfg.restUrl    || '/wp-json/jankx/v1/ai-chat';
    const NONCE       = cfg.nonce      || '';
    const BOT_NAME    = cfg.botName    || 'Trợ lý AI';
    const SYS_PROMPT  = cfg.systemPrompt || '';
    const PAGE_CTX    = cfg.currentPage  || {};
    const I18N        = cfg.i18n || {};

    // =========================================================================
    // DOM references
    // =========================================================================
    const root       = document.getElementById('jankx-ai-chatbox');
    if (!root) return;

    const iconBtn    = root.querySelector('#jankx-chatbox-icon-btn');
    const pill       = root.querySelector('#jankx-chatbox-pill');
    const panel      = root.querySelector('#jankx-chatbox-panel');
    const messagesEl = root.querySelector('#jankx-chatbox-messages');
    const inputEl    = root.querySelector('#jankx-chatbox-input');
    const sendBtn    = root.querySelector('#jankx-chatbox-send-btn');
    const closeBtn   = root.querySelector('#jankx-chatbox-close-btn');
    const minimizeBtn= root.querySelector('#jankx-chatbox-minimize-btn');
    const clearBtn   = root.querySelector('#jankx-chatbox-clear-btn');
    const greetingEl = root.querySelector('#jankx-chatbox-greeting');

    // =========================================================================
    // State
    // =========================================================================
    let currentState = 'icon';   // 'icon' | 'pill' | 'expanded'
    let chatHistory  = [];       // [{role, text}, ...]
    let activeFilters= {};       // {destination, for_whom, budget, duration, discount, tour_type}
    let isLoading    = false;
    let optionsOpen  = false;
    let filterOpen   = false;

    // =========================================================================
    // State machine
    // =========================================================================
    function setState(newState) {
        currentState = newState;
        root.dataset.state = newState;

        if (newState === 'expanded') {
            panel.setAttribute('aria-hidden', 'false');
            inputEl.focus();
            loadSuggestionsIfNeeded();
        } else {
            panel.setAttribute('aria-hidden', 'true');
            closeOptions();
            closeFilter();
        }
    }

    // =========================================================================
    // Trigger state transitions (icon → pill after delay, pill → expanded on click)
    // =========================================================================
    let pillTimer = null;

    function initStateTransitions() {
        // Icon → Pill: sau 2s khi trang load
        pillTimer = setTimeout(() => {
            if (currentState === 'icon') setState('pill');
        }, 2000);

        // Icon button click → Expanded
        iconBtn.addEventListener('click', () => setState('expanded'));

        // Pill click → Expanded
        pill.addEventListener('click', () => setState('expanded'));
        pill.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setState('expanded');
            }
        });

        // Close → Icon
        closeBtn.addEventListener('click', () => {
            setState('icon');
            // Reset lại pill timer
            clearTimeout(pillTimer);
            pillTimer = setTimeout(() => {
                if (currentState === 'icon') setState('pill');
            }, 3000);
        });

        // Minimize → Pill
        minimizeBtn.addEventListener('click', () => setState('pill'));

        // Clear chat
        clearBtn.addEventListener('click', clearChat);

        // ESC để đóng
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (optionsOpen)     { closeOptions(); return; }
                if (filterOpen)      { closeFilter();  return; }
                if (currentState === 'expanded') setState('pill');
            }
        });
    }

    // =========================================================================
    // Options Menu
    // =========================================================================
    function buildOptionsMenu() {
        const optionsBtn = root.querySelector('#jankx-chatbox-options-btn');
        if (!optionsBtn) return;

        const menu = document.createElement('div');
        menu.className    = 'jankx-chatbox__options-menu';
        menu.id           = 'jankx-chatbox-options-menu';
        menu.setAttribute('role', 'menu');

        const items = [
            { id: 'new-chat',     icon: icons.edit,    label: 'Hội thoại mới',       action: clearChat },
            { id: 'filter',       icon: icons.filter,  label: 'Bộ lọc tour',          action: openFilter },
            { divider: true },
            { id: 'history',      icon: icons.list,    label: 'Danh sách hội thoại',  action: noop },
            { id: 'share',        icon: icons.share,   label: 'Chia sẻ hội thoại',    action: noop },
            { id: 'rate',         icon: icons.star,    label: 'Đánh giá hội thoại',   action: noop },
            { id: 'close-chat',   icon: icons.close2,  label: 'Đóng hội thoại',        action: () => setState('icon') },
            { divider: true },
            { id: 'privacy',      icon: icons.shield,  label: 'Quyền riêng tư & dữ liệu', action: noop },
        ];

        items.forEach(item => {
            if (item.divider) {
                const div = document.createElement('div');
                div.className = 'jankx-chatbox__options-divider';
                menu.appendChild(div);
                return;
            }
            const btn = document.createElement('button');
            btn.className = 'jankx-chatbox__options-item';
            btn.setAttribute('role', 'menuitem');
            btn.dataset.id = item.id;
            btn.innerHTML = `
                <span class="jankx-chatbox__options-item-inner">
                    <span class="jankx-chatbox__options-item-icon">${item.icon}</span>
                    ${escHtml(item.label)}
                </span>
                <span class="jankx-chatbox__options-item-chevron">${icons.chevron}</span>
            `;
            btn.addEventListener('click', () => {
                closeOptions();
                item.action();
            });
            menu.appendChild(btn);
        });

        // Footer links
        const footer = document.createElement('div');
        footer.className = 'jankx-chatbox__options-footer';
        footer.innerHTML = `
            <a href="/dieu-khoan-su-dung" target="_blank">Điều khoản sử dụng</a>
            <a href="/chinh-sach-bao-mat" target="_blank">Quyền riêng tư</a>
        `;
        menu.appendChild(footer);

        // Gắn vào header (position: relative)
        const header = root.querySelector('.jankx-chatbox__header');
        header.appendChild(menu);

        // Toggle on click
        optionsBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            optionsOpen ? closeOptions() : openOptions();
        });

        // Click ngoài để đóng
        document.addEventListener('click', (e) => {
            if (optionsOpen && !menu.contains(e.target) && e.target !== optionsBtn) {
                closeOptions();
            }
        });
    }

    function openOptions() {
        const menu = root.querySelector('#jankx-chatbox-options-menu');
        if (!menu) return;
        optionsOpen = true;
        menu.classList.add('is-open');
        root.querySelector('#jankx-chatbox-options-btn')?.classList.add('is-active');
    }

    function closeOptions() {
        const menu = root.querySelector('#jankx-chatbox-options-menu');
        if (!menu) return;
        optionsOpen = false;
        menu.classList.remove('is-open');
        root.querySelector('#jankx-chatbox-options-btn')?.classList.remove('is-active');
    }

    // =========================================================================
    // Filter Panel (Bộ lọc tour)
    // =========================================================================
    let filterOptionsCache = null;

    async function openFilter() {
        if (!filterOptionsCache) {
            filterOptionsCache = await fetchFilterOptions();
        }
        renderFilterPanel(filterOptionsCache);
        panel.classList.add('show-filter');
        filterOpen = true;
    }

    function closeFilter() {
        panel.classList.remove('show-filter');
        filterOpen = false;
    }

    async function fetchFilterOptions() {
        try {
            const res = await fetch(REST_URL + '/filter-options', {
                headers: { 'X-WP-Nonce': NONCE },
            });
            return res.ok ? await res.json() : getDefaultFilterOptions();
        } catch {
            return getDefaultFilterOptions();
        }
    }

    function renderFilterPanel(options) {
        // Xóa panel cũ nếu có
        const old = panel.querySelector('.jankx-chatbox__filter-panel');
        if (old) old.remove();

        const fp = document.createElement('div');
        fp.className = 'jankx-chatbox__filter-panel';

        // Header
        fp.innerHTML = `
            <div class="jankx-chatbox__filter-header">
                <span class="jankx-chatbox__filter-title">Bộ lọc tour</span>
                <button class="jankx-chatbox__filter-close" id="jankx-filter-close-btn">Đóng</button>
            </div>
            <div class="jankx-chatbox__filter-body" id="jankx-filter-body"></div>
            <div class="jankx-chatbox__filter-footer">
                <button class="jankx-chatbox__filter-reset" id="jankx-filter-reset-btn">Xóa bộ lọc</button>
                <button class="jankx-chatbox__filter-apply" id="jankx-filter-apply-btn">Xem kết quả</button>
            </div>
        `;

        const body = fp.querySelector('#jankx-filter-body');

        // Build dropdowns
        Object.entries(options).forEach(([key, group]) => {
            const grp = document.createElement('div');
            grp.className = 'jankx-chatbox__filter-group';

            const select = document.createElement('select');
            select.className = 'jankx-chatbox__filter-select';
            select.id        = `jankx-filter-${key}`;
            select.name      = key;

            Object.entries(group.options || {}).forEach(([val, label]) => {
                const opt      = document.createElement('option');
                opt.value      = val;
                opt.textContent= label;
                if (activeFilters[key] === val) opt.selected = true;
                select.appendChild(opt);
            });

            grp.innerHTML = `<label class="jankx-chatbox__filter-label" for="jankx-filter-${key}">${escHtml(group.label || key)}</label>`;
            grp.appendChild(select);
            body.appendChild(grp);
        });

        if (!Object.keys(options).length) {
            body.innerHTML = '<p class="jankx-chatbox__filter-empty">Không có bộ lọc nào.</p>';
        }

        // Events
        fp.querySelector('#jankx-filter-close-btn').addEventListener('click', closeFilter);
        fp.querySelector('#jankx-filter-reset-btn').addEventListener('click', () => {
            activeFilters = {};
            fp.querySelectorAll('.jankx-chatbox__filter-select').forEach(s => s.selectedIndex = 0);
        });
        fp.querySelector('#jankx-filter-apply-btn').addEventListener('click', () => {
            activeFilters = {};
            fp.querySelectorAll('.jankx-chatbox__filter-select').forEach(s => {
                if (s.value) activeFilters[s.name] = s.value;
            });
            closeFilter();
            const summary = buildFilterSummary();
            if (summary) {
                sendMessage('Tìm tour với bộ lọc: ' + summary);
            }
        });

        panel.appendChild(fp);
    }

    function buildFilterSummary() {
        const labelMap = {
            destination: 'Điểm đến', for_whom: 'Dành cho', budget: 'Ngân sách',
            duration: 'Thời gian', discount: 'Giảm giá', tour_type: 'Loại hình',
        };
        return Object.entries(activeFilters)
            .map(([k, v]) => `${labelMap[k] || k}: ${v}`)
            .join(', ');
    }

    function getDefaultFilterOptions() {
        return {
            destination: { label: 'Điểm đến',  options: { '': 'Tất cả', 'phu-quoc': 'Phú Quốc', 'da-lat': 'Đà Lạt', 'ha-long': 'Hạ Long' } },
            for_whom:    { label: 'Dành cho',   options: { '': 'Tất cả', 'family': 'Gia đình', 'couple': 'Cặp đôi', 'group': 'Nhóm bạn' } },
            budget:      { label: 'Ngân sách',  options: { '': 'Tất cả', 'under_5m': 'Dưới 5tr', '5m_10m': '5-10tr', 'above_10m': 'Trên 10tr' } },
            duration:    { label: 'Thời gian',  options: { '': 'Tất cả', '1d': '1 ngày', '2d1n': '2N1Đ', '3d2n': '3N2Đ', '5d+': '5 ngày+' } },
        };
    }

    // =========================================================================
    // Suggestions
    // =========================================================================
    async function loadSuggestionsIfNeeded() {
        if (chatHistory.length > 0) return;
        if (!greetingEl) return;

        const suggestContainer = greetingEl.querySelector('#jankx-chatbox-suggestions');
        if (!suggestContainer) return;

        const pageType = PAGE_CTX.type || 'default';

        try {
            const res = await fetch(
                `${REST_URL}/suggestions?page_type=${pageType}`,
                { headers: { 'X-WP-Nonce': NONCE } }
            );
            if (!res.ok) return;
            const data = await res.json();
            if (!data.suggestions?.length) return;

            suggestContainer.innerHTML = '';
            data.suggestions.forEach(s => {
                const btn = document.createElement('button');
                btn.className = 'jankx-chatbox__suggestion-btn';
                btn.textContent = s.label;
                btn.addEventListener('click', () => sendMessage(s.query));
                suggestContainer.appendChild(btn);
            });
        } catch { /* fail silently */ }
    }

    // =========================================================================
    // Chat
    // =========================================================================
    function initInput() {
        inputEl.addEventListener('input', () => {
            // Auto-resize
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + 'px';
            sendBtn.disabled = !inputEl.value.trim();
        });

        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendBtn.disabled) triggerSend();
            }
        });

        sendBtn.addEventListener('click', triggerSend);
    }

    function triggerSend() {
        const msg = inputEl.value.trim();
        if (!msg || isLoading) return;
        inputEl.value = '';
        inputEl.style.height = 'auto';
        sendBtn.disabled = true;
        sendMessage(msg);
    }

    async function sendMessage(message) {
        if (!message.trim()) return;

        hideGreeting();
        appendMessage('user', message);
        chatHistory.push({ role: 'user', text: message });

        const typingEl = appendTyping();
        isLoading = true;

        try {
            const res = await fetch(REST_URL, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce':   NONCE,
                },
                body: JSON.stringify({
                    message,
                    history:  chatHistory.slice(0, -1), // exclude current message
                    context:  PAGE_CTX,
                    filters:  activeFilters,
                }),
            });

            typingEl.remove();

            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                appendMessage('assistant', err.error || I18N.errorMessage || 'Xin lỗi, có lỗi xảy ra.');
                return;
            }

            const data  = await res.json();
            const reply = data.reply || I18N.errorMessage || 'Xin lỗi, tôi không thể trả lời lúc này.';
            appendMessage('assistant', reply);
            chatHistory.push({ role: 'assistant', text: reply });

        } catch (err) {
            typingEl.remove();
            appendMessage('assistant', I18N.errorMessage || 'Xin lỗi, kết nối thất bại. Vui lòng thử lại.');
        } finally {
            isLoading = false;
        }
    }

    function appendMessage(role, text) {
        const wrapper = document.createElement('div');
        wrapper.className = `jankx-chatbox__message jankx-chatbox__message--${role}`;

        const bubble = document.createElement('div');
        bubble.className = 'jankx-chatbox__message-bubble';
        bubble.textContent = text;

        wrapper.appendChild(bubble);
        messagesEl.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    }

    function appendTyping() {
        const wrapper = document.createElement('div');
        wrapper.className = 'jankx-chatbox__message jankx-chatbox__message--assistant';
        wrapper.innerHTML = `
            <div class="jankx-chatbox__typing" aria-label="${I18N.thinking || 'Đang xử lý...'}">
                <span class="jankx-chatbox__typing-dot"></span>
                <span class="jankx-chatbox__typing-dot"></span>
                <span class="jankx-chatbox__typing-dot"></span>
            </div>
        `;
        messagesEl.appendChild(wrapper);
        scrollToBottom();
        return wrapper;
    }

    function hideGreeting() {
        if (greetingEl && greetingEl.style.display !== 'none') {
            greetingEl.style.display = 'none';
        }
    }

    function clearChat() {
        chatHistory = [];
        messagesEl.innerHTML = '';
        if (greetingEl) {
            greetingEl.style.display = '';
            messagesEl.appendChild(greetingEl);
        }
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // =========================================================================
    // SVG Icons
    // =========================================================================
    const icons = {
        edit:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`,
        filter:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="11" y1="18" x2="13" y2="18"/></svg>`,
        list:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>`,
        share:   `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>`,
        star:    `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
        close2:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>`,
        shield:  `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>`,
        chevron: `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>`,
    };

    // =========================================================================
    // Helpers
    // =========================================================================
    function escHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function noop() {}

    // =========================================================================
    // Add options button to header (if not in template)
    // =========================================================================
    function ensureOptionsButton() {
        if (root.querySelector('#jankx-chatbox-options-btn')) return;
        const btn = document.createElement('button');
        btn.id        = 'jankx-chatbox-options-btn';
        btn.className = 'jankx-chatbox__header-btn';
        btn.setAttribute('aria-label', 'Tùy chọn');
        btn.setAttribute('title', 'Tùy chọn');
        btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>`;

        // Insert before close button
        const actions = root.querySelector('.jankx-chatbox__header-actions');
        const closeBtnEl = root.querySelector('#jankx-chatbox-close-btn');
        actions.insertBefore(btn, closeBtnEl);
    }

    function ensureFilterButton() {
        if (root.querySelector('#jankx-chatbox-filter-btn')) return;
        const btn = document.createElement('button');
        btn.id        = 'jankx-chatbox-filter-btn';
        btn.className = 'jankx-chatbox__header-btn';
        btn.setAttribute('aria-label', 'Bộ lọc tour');
        btn.setAttribute('title', 'Bộ lọc tour');
        btn.innerHTML = icons.filter;
        btn.addEventListener('click', () => filterOpen ? closeFilter() : openFilter());

        const actions = root.querySelector('.jankx-chatbox__header-actions');
        actions.insertBefore(btn, actions.firstChild);
    }

    // =========================================================================
    // Boot
    // =========================================================================
    function init() {
        ensureOptionsButton();
        ensureFilterButton();
        initStateTransitions();
        buildOptionsMenu();
        initInput();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
