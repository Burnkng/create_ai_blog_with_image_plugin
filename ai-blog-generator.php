<?php
/**
 * Plugin Name: AI Blog Generator
 * Plugin URI: https://your-website.com/ai-blog-generator
 * Description: GPT-4.1 mini/nano kullanarak otomatik blog yazıları oluşturan WordPress eklentisi
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-blog-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

// Eklenti sınıfı
class AI_Blog_Generator {
    private static $instance = null;
    private $api_key = '';

    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX işleyicileri
        add_action('wp_ajax_generate_blog_content', array($this, 'generate_blog_content'));
        add_action('wp_ajax_publish_blog_post', array($this, 'publish_blog_post'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'AI Blog Generator',
            'AI Blog Generator', 
            'manage_options',
            'ai-blog-generator',
            array($this, 'admin_page'),
            'dashicons-edit'
        );
    }

    public function register_settings() {
        register_setting('ai_blog_generator_settings', 'ai_blog_generator_api_key');
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook != 'toplevel_page_ai-blog-generator') {
            return;
        }
        wp_enqueue_style('ai-blog-generator-admin', plugins_url('assets/css/admin.css', __FILE__));
        wp_enqueue_script('ai-blog-generator-admin', plugins_url('assets/js/admin.js', __FILE__), array('jquery'), '1.0.0', true);
        wp_localize_script('ai-blog-generator-admin', 'aiBlogGenerator', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_blog_generator_nonce')
        ));
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>AI Blog Generator</h1>
            <div class="ai-blog-generator-container">
                <div class="settings-section">
                    <h2>Ayarlar</h2>
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('ai_blog_generator_settings');
                        do_settings_sections('ai_blog_generator_settings');
                        ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row">API Anahtarı</th>
                                <td>
                                    <input type="text" name="ai_blog_generator_api_key" 
                                           value="<?php echo esc_attr(get_option('ai_blog_generator_api_key')); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="generator-section">
                    <h2>Blog Yazısı Oluştur</h2>
                    <div class="prompt-container">
                        <textarea id="blog-prompt" placeholder="Blog yazısı için konu veya prompt girin..."></textarea>
                        <button id="generate-blog" class="button button-primary">Blog Oluştur</button>
                    </div>
                    <div id="generation-result" style="display: none;">
                        <h3>Oluşturulan İçerik</h3>
                        <div id="blog-content"></div>
                        <button id="publish-blog" class="button button-primary">Yayınla</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // AI ile blog içeriği oluştur
    public function generate_blog_content() {
        check_ajax_referer('ai_blog_generator_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Yetkiniz yok.'));
        }

        $prompt = sanitize_textarea_field($_POST['prompt']);
        if (empty($prompt)) {
            wp_send_json_error(array('message' => 'Prompt boş olamaz.'));
        }

        $api_key = get_option('ai_blog_generator_api_key');
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API anahtarı ayarlanmamış.'));
        }

        try {
            // GPT API'ye istek
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode(array(
                    'model' => 'gpt-4.1-mini',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'Sen profesyonel bir WordPress blog yazarısın. Verilen konuda detaylı ve SEO dostu blog yazıları oluşturuyorsun. HTML formatında yaz, markdown değil. Yanıtını tam olarak aşağıdaki formatta ver ve format etiketlerini kesinlikle değiştirme: 

BAŞLIK: [Buraya SEO dostu çekici bir başlık yaz]

İÇERİK: 
[Buraya detaylı blog içeriğini HTML formatında yaz. <p>, <h2>, <strong>, <a> gibi HTML etiketleri kullan]

KATEGORİ: [Buraya yazı için en uygun tek bir kategori adı yaz]

ETİKETLER: [Buraya virgülle ayrılmış 5-10 adet anahtar kelime/etiket yaz]'
                        ),
                        array(
                            'role' => 'user',
                            'content' => $prompt
                        )
                    ),
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                )),
                'timeout' => 60,
            ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (empty($body) || !isset($body['choices'][0]['message']['content'])) {
                throw new Exception('API yanıtı geçersiz.');
            }

            $content = $body['choices'][0]['message']['content'];
            
            // İçeriği ayrıştır
            $title = '';
            $post_content = '';
            $category = '';
            $tags = array();
            
            // Başlık ayrıştırma - daha kesin bir ayrıştırma
            if (preg_match('/BAŞLIK:\s*([^\n]+)/', $content, $title_match)) {
                $title = trim($title_match[1]);
            }
            
            // İçerik ayrıştırma - daha kesin bir ayrıştırma
            if (preg_match('/İÇERİK:\s*(.*?)(?=\s*KATEGORİ:)/s', $content, $content_match)) {
                $post_content = trim($content_match[1]);
            }
            
            // Kategori ayrıştırma
            if (preg_match('/KATEGORİ:\s*([^\n]+)/', $content, $category_match)) {
                $category = trim($category_match[1]);
            }
            
            // Etiketler ayrıştırma
            if (preg_match('/ETİKETLER:\s*([^\n]+)/', $content, $tags_match)) {
                $tags_string = trim($tags_match[1]);
                $tags = array_map('trim', explode(',', $tags_string));
            }
            
            // Başlık kontrolü
            if (empty($title)) {
                throw new Exception('AI başlık üretemedi. Lütfen tekrar deneyin.');
            }
            
            // İçerik kontrolü
            if (empty($post_content)) {
                throw new Exception('AI içerik üretemedi. Lütfen tekrar deneyin.');
            }
            
            wp_send_json_success(array(
                'title' => $title,
                'content' => wp_kses_post($post_content),
                'category' => $category,
                'tags' => $tags
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'İçerik oluşturulurken hata: ' . $e->getMessage()
            ));
        }
    }

    // Blog yazısını direkt yayınla
    public function publish_blog_post() {
        check_ajax_referer('ai_blog_generator_nonce', 'nonce');

        if (!current_user_can('publish_posts')) {
            wp_send_json_error(array('message' => 'Yayınlama yetkiniz yok.'));
        }

        $title = sanitize_text_field($_POST['title']);
        $content = wp_kses_post($_POST['content']);
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $tags = isset($_POST['tags']) ? $_POST['tags'] : array();

        if (empty($content)) {                    alert('Blog yazısı başarıyla yayınlandı!');

            wp_send_json_error(array('message' => 'İçerik boş olamaz.'));
        }

        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_author'   => get_current_user_id()
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            wp_send_json_error(array(
                'message' => 'Yazı yayınlanırken hata: ' . $post_id->get_error_message()
            ));
        }

        // Kategori ekle
        if (!empty($category)) {
            $cat_id = 0;
            $existing_cat = get_term_by('name', $category, 'category');
            
            if ($existing_cat) {
                $cat_id = $existing_cat->term_id;
            } else {
                $new_cat = wp_insert_term($category, 'category');
                if (!is_wp_error($new_cat)) {
                    $cat_id = $new_cat['term_id'];
                }
            }
            
            if ($cat_id > 0) {
                wp_set_post_categories($post_id, array($cat_id));
            }
        }
        
        // Etiketler ekle
        if (!empty($tags)) {
            wp_set_post_tags($post_id, $tags);
        }

        wp_send_json_success(array(
            'message' => 'Yazı başarıyla yayınlandı.',
            'post_url' => get_permalink($post_id)
        ));
    }
}

// Eklentiyi başlat
function ai_blog_generator_init() {
    AI_Blog_Generator::get_instance();
}
add_action('plugins_loaded', 'ai_blog_generator_init'); 