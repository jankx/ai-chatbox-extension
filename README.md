# AI Chatbox Extension

Trợ lý AI tích hợp vào nibitour.vn, hỗ trợ khách hàng hỏi về **tour du lịch, sản phẩm và đơn hàng** thông qua giao diện chatbox nổi (floating chatbox) trên toàn bộ frontend.

---

## Tính năng

- 🤖 **AI đa provider** – Google Gemini, OpenAI, Ollama (local) — dễ dàng mở rộng thêm provider mới
- 💬 **3 trạng thái UI** – Icon → Pill → Expanded panel
- 🔍 **Bộ lọc tour** – Filter theo điểm đến, ngân sách, thời gian, đối tượng...
- 📋 **Options menu** – Hội thoại mới, chia sẻ, đánh giá, quyền riêng tư
- 🧠 **Context-aware** – Tự động đính kèm tên trang & loại nội dung vào mỗi câu hỏi
- 💾 **Conversation history** – Giữ ngữ cảnh hội thoại trong session
- ⚙️ **Admin settings** – Cấu hình đầy đủ từ WordPress Admin
- 🔌 **WordPress filter hooks** – Cho phép plugin/theme bên ngoài mở rộng

---

## Yêu cầu

| Thành phần | Phiên bản tối thiểu |
|---|---|
| PHP | 7.4+ |
| WordPress | 6.0+ |
| Jankx Framework | latest |
| WooCommerce | Không bắt buộc |

---

## Cài đặt

Extension được load tự động bởi Jankx khi thư mục `ai-chatbox/` tồn tại trong `extensions/` của theme và `manifest.json` có `"auto_activate": true`.

**Không cần** `composer install` vì autoloader được đăng ký thủ công qua `spl_autoload_register` trong `AiChatboxExtension.php`.

---

## Cấu hình ban đầu

### 1. Lấy API Key

**Google Gemini (khuyến nghị – miễn phí):**

1. Truy cập [https://aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey)
2. Tạo API key mới
3. Copy key

### 2. Nhập vào Admin Settings

```
WordPress Admin → Settings → AI Chatbox
```

Điền API key vào ô **Google Gemini API Key** → **Lưu cài đặt**.

---

## Cấu trúc thư mục

```
ai-chatbox/
├── AiChatboxExtension.php          ← Entry point: đăng ký hooks, assets, render
├── manifest.json                   ← Khai báo extension cho Jankx loader
├── composer.json
│
├── templates/
│   └── chatbox.php                 ← HTML template cho 3 states của chatbox
│
├── assets/
│   ├── css/chatbox.css             ← Styles, animations, responsive
│   └── js/chatbox.js               ← State machine, chat loop, options, filter
│
└── src/
    ├── Contracts/
    │   └── AiProviderInterface.php ← Strategy interface (contract)
    │
    ├── Providers/
    │   ├── AbstractAiProvider.php  ← Base class: context enrichment, history trim
    │   ├── GeminiProvider.php      ← Google Gemini implementation
    │   └── OpenAiProvider.php      ← OpenAI / Ollama / Groq compatible
    │
    ├── Factory/
    │   └── AiProviderFactory.php   ← Factory + WP filter hook để mở rộng
    │
    ├── Services/
    │   └── ChatService.php         ← Orchestration: filter→prompt, WP hooks
    │
    ├── Api/
    │   └── ChatRestController.php  ← REST API endpoints
    │
    └── Admin/
        └── SettingsPage.php        ← Trang cài đặt WordPress Admin
```

---

## Design Patterns

Extension áp dụng các pattern sau để đảm bảo khả năng mở rộng:

### Strategy Pattern – `AiProviderInterface`

Mọi provider AI đều implement cùng một interface. Code tầng trên (ChatService, REST controller) **không biết** đang dùng Gemini hay OpenAI.

```
AiProviderInterface
    ├── GeminiProvider
    ├── OpenAiProvider
    └── [YourCustomProvider]  ← thêm mới không sửa code cũ
```

### Factory Pattern – `AiProviderFactory`

Tạo đúng provider dựa theo WordPress option `jankx_ai_chatbox_provider`, đồng thời expose filter hook để bên ngoài đăng ký provider tùy chỉnh.

### Template Method – `AbstractAiProvider`

Base class xử lý logic chung (context enrichment, cắt history, kiểm tra `isConfigured`). Concrete provider chỉ cần override `callApi()`.

### Service Layer – `ChatService`

Tách toàn bộ business logic (inject filter vào system prompt, gọi WP hooks) ra khỏi HTTP layer (REST controller).

---

## REST API Endpoints

Base namespace: `jankx/v1`

### `POST /ai-chat`

Gửi tin nhắn và nhận phản hồi AI.

**Request body:**

```json
{
    "message": "Tour Phú Quốc giá bao nhiêu?",
    "history": [
        { "role": "user",      "text": "Xin chào" },
        { "role": "assistant", "text": "Chào bạn! Tôi có thể giúp gì?" }
    ],
    "context": {
        "title": "Tour Phú Quốc 3N2Đ",
        "url":   "https://nibitour.vn/tour/phu-quoc-3n2d",
        "type":  "product"
    },
    "filters": {
        "destination": "phu-quoc",
        "budget":      "5m_10m"
    }
}
```

**Response:**

```json
{
    "reply":     "Tour Phú Quốc 3N2Đ hiện có giá từ 5.990.000đ/người...",
    "timestamp": "2026-10-04 22:00:00"
}
```

---

### `GET /ai-chat/suggestions`

Lấy danh sách câu hỏi gợi ý theo loại trang.

**Query params:**

| Tham số | Kiểu | Mô tả |
|---|---|---|
| `page_type` | string | `product`, `tour`, `destination`, `default` |
| `post_id`   | int    | ID bài viết hiện tại (optional) |

**Response:**

```json
{
    "suggestions": [
        { "label": "Lịch khởi hành", "query": "Cho tôi biết các ngày khởi hành gần nhất" },
        { "label": "Giá & Ưu đãi",   "query": "Tour này giá bao nhiêu và có ưu đãi gì?" }
    ]
}
```

---

### `GET /ai-chat/filter-options`

Lấy các tùy chọn cho bộ lọc tour (dropdown options).

**Response:**

```json
{
    "destination": {
        "label": "Điểm đến",
        "options": { "": "Tất cả", "phu-quoc": "Phú Quốc", "da-lat": "Đà Lạt" }
    },
    "budget": {
        "label": "Ngân sách",
        "options": { "": "Tất cả", "under_5m": "Dưới 5 triệu" }
    }
}
```

> Nếu taxonomy `destination` tồn tại trong WordPress, danh sách điểm đến sẽ tự động lấy từ terms.

---

### `GET /ai-chat/provider-info` *(yêu cầu `manage_options`)*

Xem thông tin provider đang hoạt động.

```json
{
    "name":               "gemini",
    "is_configured":      true,
    "available_providers": {
        "gemini": "Google Gemini (Miễn phí)",
        "openai": "OpenAI (GPT-4o, GPT-4o-mini...)",
        "ollama": "Ollama (Local – Miễn phí)"
    }
}
```

---

## WordPress Options

Tất cả cài đặt lưu trong `wp_options`:

| Option key | Mặc định | Mô tả |
|---|---|---|
| `jankx_ai_chatbox_enabled` | `1` | Bật/tắt chatbox |
| `jankx_ai_chatbox_bot_name` | `Trợ lý AI` | Tên hiển thị |
| `jankx_ai_chatbox_pill_label` | `Mua cùng AI` | Text trên pill state |
| `jankx_ai_chatbox_subtitle` | *(tên blog)* | Dòng subtitle dưới header |
| `jankx_ai_chatbox_system_prompt` | *(xem code)* | System instruction cho AI |
| `jankx_ai_chatbox_provider` | `gemini` | Provider đang dùng |
| `jankx_ai_chatbox_gemini_api_key` | — | API key Gemini |
| `jankx_ai_chatbox_gemini_model` | `gemini-1.5-flash` | Model Gemini |
| `jankx_ai_chatbox_openai_api_key` | — | API key OpenAI |
| `jankx_ai_chatbox_openai_model` | `gpt-4o-mini` | Model OpenAI |
| `jankx_ai_chatbox_openai_base_url` | `https://api.openai.com/v1` | Base URL (đổi cho Groq, Ollama...) |
| `jankx_ai_chatbox_ollama_model` | `llama3` | Model Ollama |
| `jankx_ai_chatbox_ollama_base_url` | `http://localhost:11434/v1` | URL Ollama server |

---

## WordPress Filter Hooks

### `jankx_ai_chatbox_make_provider`

Đăng ký AI provider tùy chỉnh mà **không cần sửa Factory**.

```php
add_filter('jankx_ai_chatbox_make_provider', function ($provider, $name) {
    if ($name === 'claude') {
        return new \MyPlugin\ClaudeProvider(
            get_option('my_claude_api_key')
        );
    }
    return $provider;
}, 10, 2);
```

> Provider phải implement `Jankx\Extensions\AiChatbox\Contracts\AiProviderInterface`.

---

### `jankx_ai_chatbox_registered_providers`

Thêm provider vào danh sách dropdown trong Admin Settings.

```php
add_filter('jankx_ai_chatbox_registered_providers', function ($providers) {
    $providers['claude'] = 'Anthropic Claude';
    return $providers;
});
```

---

### `jankx_ai_chatbox_before_send`

Can thiệp vào tin nhắn **trước khi gửi lên AI** (lọc từ ngữ, thêm context...).

```php
add_filter('jankx_ai_chatbox_before_send', function ($message, $context, $filters) {
    // Thêm thông tin order nếu user đang đăng nhập
    if (is_user_logged_in()) {
        $message .= ' [User ID: ' . get_current_user_id() . ']';
    }
    return $message;
}, 10, 3);
```

---

### `jankx_ai_chatbox_after_response`

Xử lý hoặc format **phản hồi từ AI** trước khi trả về client.

```php
add_filter('jankx_ai_chatbox_after_response', function ($reply, $message) {
    // Tự động thêm disclaimer
    return $reply . "\n\n*Giá có thể thay đổi, vui lòng liên hệ để xác nhận.*";
}, 10, 2);
```

---

### `jankx_ai_chatbox_suggestions`

Tùy chỉnh danh sách câu hỏi gợi ý.

```php
add_filter('jankx_ai_chatbox_suggestions', function ($suggestions, $page_type, $post_id) {
    if ($page_type === 'product' && has_term('combo', 'product_cat', $post_id)) {
        array_unshift($suggestions, [
            'label' => 'Tour combo giá tốt',
            'query' => 'So sánh các gói combo đang có khuyến mãi',
        ]);
    }
    return $suggestions;
}, 10, 3);
```

---

### `jankx_ai_chatbox_filter_options`

Thêm hoặc chỉnh sửa các nhóm filter trong bộ lọc tour.

```php
add_filter('jankx_ai_chatbox_filter_options', function ($options) {
    $options['transport'] = [
        'label'   => 'Phương tiện',
        'options' => [
            ''       => 'Tất cả',
            'flight' => 'Máy bay',
            'bus'    => 'Xe khách',
            'train'  => 'Tàu hỏa',
        ],
    ];
    return $options;
});
```

---

## Huấn luyện / Cung cấp dữ liệu thật (RAG)

Để AI trả lời chính xác dựa trên dữ liệu website thực tế (không cần fine-tune model), bạn nên sử dụng cơ chế nhúng dữ liệu (Context Injection) thông qua filter `jankx_ai_chatbox_before_send`. Dữ liệu sẽ được tự động đính kèm ẩn phía sau câu hỏi để AI đọc được.

**Cách 1: Nhúng dữ liệu trang hiện tại**
```php
add_filter('jankx_ai_chatbox_before_send', function ($message, $context, $filters) {
    // Lấy thông tin bài viết hiện tại nếu là tour
    if (isset($context['type']) && $context['type'] === 'tour' && isset($context['url'])) {
        $post_id = url_to_postid($context['url']);
        if ($post_id) {
            $title = get_the_title($post_id);
            $content = strip_tags(get_post_field('post_content', $post_id));
            $price = get_post_meta($post_id, '_price', true);
            
            // Nhúng ngầm dữ liệu
            $message .= "\n\n--- THÔNG TIN TOUR ---\nTên: $title\nGiá: $price\nChi tiết: $content\n";
        }
    }
    return $message;
}, 10, 3);
```

**Cách 2: Truy vấn Database dựa theo câu hỏi / bộ lọc**
```php
add_filter('jankx_ai_chatbox_before_send', function ($message, $context, $filters) {
    if (isset($filters['destination']) && $filters['destination'] === 'nha-trang') {
        $tours = new WP_Query([
            'post_type' => 'tour',
            'tax_query' => [['taxonomy' => 'destination', 'field' => 'slug', 'terms' => 'nha-trang']],
            'posts_per_page' => 3
        ]);
        
        $message .= "\n\n--- TOUR NHA TRANG TRONG HỆ THỐNG ---\n";
        if ($tours->have_posts()) {
            while ($tours->have_posts()) {
                $tours->the_post();
                $message .= "- " . get_the_title() . " (Giá: " . get_post_meta(get_the_ID(), '_price', true) . ")\n";
            }
            wp_reset_postdata();
        }
    }
    return $message;
}, 10, 3);
```

---

## Hướng dẫn thêm AI Provider mới

Ví dụ thêm **Anthropic Claude**:

### Bước 1 – Tạo class Provider

```php
// src/Providers/ClaudeProvider.php
namespace Jankx\Extensions\AiChatbox\Providers;

class ClaudeProvider extends AbstractAiProvider
{
    public function getProviderName(): string { return 'claude'; }

    protected function callApi(string $message, array $history, string $systemPrompt)
    {
        $messages = [];
        foreach ($history as $turn) {
            $messages[] = [
                'role'    => $this->normalizeRole($turn['role'] ?? 'user'),
                'content' => $turn['text'] ?? '',
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'      => 'claude-3-5-haiku-20241022',
                'max_tokens' => 1024,
                'system'     => $systemPrompt,
                'messages'   => $messages,
            ]),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) return $response;

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['content'][0]['text']
            ?? new \WP_Error('claude_empty', 'Claude trả về phản hồi rỗng.');
    }
}
```

### Bước 2 – Đăng ký qua filter hook

```php
// Trong functions.php của theme hoặc một plugin riêng:
add_filter('jankx_ai_chatbox_make_provider', function ($provider, $name) {
    if ($name === 'claude') {
        return new \Jankx\Extensions\AiChatbox\Providers\ClaudeProvider(
            get_option('jankx_ai_chatbox_claude_api_key', '')
        );
    }
    return $provider;
}, 10, 2);

add_filter('jankx_ai_chatbox_registered_providers', function ($providers) {
    $providers['claude'] = 'Anthropic Claude (Haiku, Sonnet...)';
    return $providers;
});
```

Không cần sửa bất kỳ file nào của extension.

---

## UI States

Chatbox có 3 trạng thái chuyển đổi tự động:

```
[Load trang]
     │
     ▼  (2 giây)
 ┌────────┐        click        ┌──────────┐
 │  ICON  │ ─────────────────► │   PILL   │
 │ (tròn) │                    │ (label)  │
 └────────┘                    └──────────┘
                                     │ click
                                     ▼
                               ┌──────────┐
                               │ EXPANDED │
                               │ (panel)  │
                               └──────────┘
                                   │    │
                          minimize │    │ close
                                   ▼    ▼
                                [PILL] [ICON]
```

### CSS Data Attributes

Trạng thái được điều khiển qua `data-state` trên `#jankx-ai-chatbox`:

```css
[data-state="icon"]     { /* chỉ hiện icon button */ }
[data-state="pill"]     { /* hiện pill label */      }
[data-state="expanded"] { /* hiện full panel */      }
```

---

## Customization CSS

Override CSS variables để thay đổi theme màu sắc:

```css
#jankx-ai-chatbox {
    --cb-primary:       #your-color;
    --cb-primary-light: #your-color-light;
    --cb-accent:        #your-accent;
    --cb-panel-w:       420px;   /* chiều rộng panel */
    --cb-panel-h:       620px;   /* chiều cao panel  */
}
```

---

## Changelog

### v1.0.0
- Ra mắt extension với Google Gemini support
- 3 UI states: icon, pill, expanded
- Bộ lọc tour (điểm đến, ngân sách, thời gian...)
- Options dropdown menu
- Context-aware (đính kèm thông tin trang hiện tại)
- Conversation history trong session
- WordPress filter hooks cho khả năng mở rộng
- OpenAI & Ollama provider (sẵn sàng, chỉ cần cấu hình)

---

## License

Thuộc dự án nibitour.vn – Puleeno Tech. All rights reserved.
