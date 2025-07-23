<?php
/**
 * Plugin Name: AI Blog Generator With Image
 * Plugin URI: https://www.burakbinmar.com                        <table class="form-table">
                            <tr>
                                <th scope="row">OpenAI API Anahtarı</th>
                                <td>
                                    <input type="text" name="ai_blog_generator_api_key" 
                                           value="<?php echo esc_attr(get_option('ai_blog_generator_api_key')); ?>" 
                                           class="regular-text">
                                    <p class="description">GPT API'si için OpenAI API anahtarınız</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Unsplash API Anahtarı</th>
                                <td>
                                    <input type="text" name="ai_blog_generator_unsplash_api_key" 
                                           value="<?php echo esc_attr(get_option('ai_blog_generator_unsplash_api_key')); ?>" 
                                           class="regular-text">
                                    <p class="description">Otomatik görseller için Unsplash API anahtarınız. <a href="https://unsplash.com/developers" target="_blank">Buradan</a> ücretsiz anahtar alabilirsiniz.</p>
                                </td>
                            </tr>
                        </table>-generator
 * Description: GPT-4.1 mini/nano kullanarak otomatik blog yazıları oluşturan WordPress eklentisi
 * Version: 1.0.0
 * Author: Burak Binmar
 * Author URI: https://www.burakbinmar.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-blog-generatorwithimage
 */

if (!defined('ABSPATH')) {
    exit;
}

// Eklenti sınıfı
class AI_Blog_Generator {
    private static $instance = null;
    private $api_key = '';
    private $unsplash_api_key = ''; // Unsplash API anahtarı için değişken

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
        add_action('wp_ajax_search_featured_image', array($this, 'search_featured_image'));
        add_action('wp_ajax_upload_selected_image', array($this, 'upload_selected_image')); // Yeni AJAX işleyicisi
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
        register_setting('ai_blog_generator_settings', 'ai_blog_generator_unsplash_api_key'); // Unsplash API ayarı
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
                            <tr>
                                <th scope="row">Unsplash API Anahtarı</th>
                                <td>
                                    <input type="text" name="ai_blog_generator_unsplash_api_key" 
                                           value="<?php echo esc_attr(get_option('ai_blog_generator_unsplash_api_key')); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
                
                <div class="generator-section">
                    <h2>Blog Yazısı Oluştur</h2>
                    <div class="mode-selector">
                        <label>
                            <input type="radio" name="generation-mode" value="single" checked> Tek Blog
                        </label>
                        <label>
                            <input type="radio" name="generation-mode" value="multiple"> Çoklu Blog
                        </label>
                    </div>

                    <div id="single-mode" class="prompt-container">
                        <textarea id="blog-prompt" placeholder="Blog yazısı için konu veya prompt girin..."></textarea>
                        <div class="image-keyword-field">
                            <label for="image-keyword">Görsel anahtar kelimesi (boş bırakılırsa başlık kullanılır):</label>
                            <input type="text" id="image-keyword" placeholder="Örn: doğa, teknoloji, işletme...">
                        </div>
                        <button id="generate-blog" class="button button-primary">Blog Oluştur</button>
                    </div>

                    <div id="multiple-mode" class="prompt-container" style="display: none;">
                        <textarea id="main-topic" placeholder="Ana konuyu detaylı açıklayın..."></textarea>
                        <textarea id="blog-topics" placeholder="Blog konularını her satıra bir tane gelecek şekilde girin...&#10;Örnek:&#10;konu1&#10;konu2&#10;konu3"></textarea>
                        <div class="image-keyword-field">
                            <label for="multiple-image-keyword">Görsel anahtar kelimesi (boş bırakılırsa başlık kullanılır):</label>
                            <input type="text" id="multiple-image-keyword" placeholder="Örn: doğa, teknoloji, işletme...">
                        </div>
                        <button id="generate-multiple-blogs" class="button button-primary">Blogları Oluştur</button>
                    </div>

                    <div id="generation-result" style="display: none;">
                        <h3>Oluşturulan İçerik</h3>
                        <div id="blog-content"></div>
                        <div class="image-preview" style="display: none;">
                            <h4>Öne Çıkan Görsel</h4>
                            <img src="" alt="Öne Çıkan Görsel">
                            <div class="image-attribution"></div>
                        </div>
                        <button id="publish-blog" class="button button-primary">
                            Yayınla <span class="image-loading" style="display: none;">(Görsel aranıyor...)</span>
                        </button>
                    </div>

                    <div id="generation-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress"></div>
                        </div>
                        <div class="progress-text">İşleniyor: <span id="current-topic"></span></div>
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
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

        if (empty($content)) {
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

        // Öne çıkan görsel ayarla
        if ($image_id > 0) {
            set_post_thumbnail($post_id, $image_id);
        }

        wp_send_json_success(array(
            'message' => 'Yazı başarıyla yayınlandı.',
            'post_url' => get_permalink($post_id)
        ));
    }

    // Görsel arama fonksiyonu
    public function search_featured_image() {
        check_ajax_referer('ai_blog_generator_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Dosya yükleme yetkiniz yok.'), 403);
            return;
        }

        $query = sanitize_text_field($_POST['query']);
        $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'single';
        $topic_count = isset($_POST['topic_count']) ? intval($_POST['topic_count']) : 1;

        if (empty($query)) {
            wp_send_json_error(array(
                'message' => 'Arama sorgusu boş olamaz.', 
                'continue_without_image' => true
            ), 400);
            return;
        }

        $unsplash_api_key = get_option('ai_blog_generator_unsplash_api_key');
        if (empty($unsplash_api_key)) {
            wp_send_json_error(array(
                'message' => 'Unsplash API anahtarı ayarlanmamış.', 
                'continue_without_image' => true
            ), 400);
            return;
        }

        try {
            // Mod ve konu sayısına göre per_page değerini belirle
            $per_page = $mode === 'single' ? 3 : ceil($topic_count * 1.5);
            
            // API isteği
            $response = wp_remote_get('https://api.unsplash.com/search/photos?query=' . urlencode($query) . '&per_page=' . $per_page, array(
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $unsplash_api_key,
                ),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                error_log('Unsplash API hatası: ' . $response->get_error_message());
                wp_send_json_error(array(
                    'message' => 'Unsplash API hatası: ' . $response->get_error_message(), 
                    'continue_without_image' => true
                ), 500);
                return;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $error_message = 'Unsplash API HTTP Hatası: ' . $response_code;
                if ($response_code === 429) {
                    $error_message = 'Unsplash API saatlik limit (50 istek) aşıldı. Lütfen daha sonra tekrar deneyin.';
                }
                error_log($error_message);
                wp_send_json_error(array(
                    'message' => $error_message, 
                    'continue_without_image' => true
                ), $response_code);
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (empty($body) || !isset($body['results']) || empty($body['results'])) {
                wp_send_json_error(array(
                    'message' => 'Uygun görsel bulunamadı.', 
                    'continue_without_image' => true
                ), 404);
                return;
            }

            // Tüm görselleri döndür
            $images = array();
            foreach ($body['results'] as $image_data) {
                $images[] = array(
                    'url' => $image_data['urls']['regular'],
                    'thumb' => $image_data['urls']['thumb'],
                    'author' => $image_data['user']['name'],
                    'attribution' => 'Photo by ' . $image_data['user']['name'] . ' on Unsplash'
                );
            }

            wp_send_json_success(array(
                'images' => $images,
                'query' => $query
            ));

        } catch (Exception $e) {
            error_log('Görsel arama hatası: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Görsel aranırken hata: ' . $e->getMessage(), 
                'continue_without_image' => true
            ), 500);
        }
    }

    // Seçilen görseli yükle
    public function upload_selected_image() {
        check_ajax_referer('ai_blog_generator_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Dosya yükleme yetkiniz yok.'), 403);
            return;
        }

        $image_url = sanitize_url($_POST['image_url']);
        $title = sanitize_text_field($_POST['title']);
        $description = sanitize_text_field($_POST['description']);

        if (empty($image_url)) {
            wp_send_json_error(array('message' => 'Görsel URL\'si boş olamaz.'), 400);
            return;
        }

        try {
            // Görseli indir ve medya kütüphanesine ekle
            $image_id = $this->upload_image_to_media_library($image_url, $title, $description);

            if (is_wp_error($image_id)) {
                wp_send_json_error(array(
                    'message' => 'Görsel indirme hatası: ' . $image_id->get_error_message()
                ), 500);
                return;
            }

            wp_send_json_success(array(
                'image_id' => $image_id,
                'image_url' => wp_get_attachment_url($image_id)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Görsel yüklenirken hata: ' . $e->getMessage()
            ), 500);
        }
    }

    // Görseli indir ve medya kütüphanesine ekle
    private function upload_image_to_media_library($image_url, $title, $description = '') {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Görseli geçici olarak indirme
        $temp_file = download_url($image_url);

        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Dosya bilgilerini ayarlama
        $file_array = array(
            'name' => sanitize_title($title) . '.jpg',
            'tmp_name' => $temp_file,
            'error' => 0,
            'size' => filesize($temp_file),
        );

        // Görseli medya kütüphanesine yükleme
        $attachment_id = media_handle_sideload($file_array, 0, $title, array(
            'post_excerpt' => $description,
        ));

        // Geçici dosyayı silme
        @unlink($temp_file);

        return $attachment_id;
    }
}

// Eklentiyi başlat
function ai_blog_generator_init() {
    AI_Blog_Generator::get_instance();
}
add_action('plugins_loaded', 'ai_blog_generator_init');