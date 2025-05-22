jQuery(document).ready(function($) {
    // Mode switching
    $('input[name="generation-mode"]').on('change', function() {
        if (this.value === 'single') {
            $('#single-mode').show();
            $('#multiple-mode').hide();
        } else {
            $('#single-mode').hide();
            $('#multiple-mode').show();
        }
        $('#generation-result').hide();
    });

    // Tek blog oluşturma
    $('#generate-blog').on('click', function() {
        var prompt = $('#blog-prompt').val();
        if (!prompt) {
            alert('Lütfen bir konu girin.');
            return;
        }
        generateSingleBlog(prompt);
    });

    // Çoklu blog oluşturma
    $('#generate-multiple-blogs').on('click', async function() {
        var mainTopic = $('#main-topic').val();
        var topics = $('#blog-topics').val().split('\n').filter(topic => topic.trim() !== '');
        var imageKeyword = $('#multiple-image-keyword').val();
        
        if (!mainTopic) {
            alert('Lütfen ana konuyu açıklayın.');
            return;
        }
        
        if (topics.length === 0) {
            alert('Lütfen en az bir blog konusu girin.');
            return;
        }

        $('#multiple-mode button').prop('disabled', true);
        $('#generation-progress').show();
        $('.progress').width('0%');

        for (let i = 0; i < topics.length; i++) {
            $('#current-topic').text(topics[i]);
            $('.progress').width((i / topics.length * 100) + '%');
            
            let combinedPrompt = mainTopic + '\n\nAlt Konu: ' + topics[i];
            
            try {
                await generateAndPublishBlog(combinedPrompt, imageKeyword);
                await new Promise(resolve => setTimeout(resolve, 2000)); // API rate limit için bekleme
            } catch (error) {
                console.error('Blog oluşturma hatası:', error);
            }
        }

        $('.progress').width('100%');
        $('#current-topic').text('Tamamlandı!');
        setTimeout(() => {
            $('#generation-progress').hide();
            $('#multiple-mode button').prop('disabled', false);
            alert('Tüm bloglar başarıyla oluşturuldu ve yayınlandı!');
        }, 1000);
    });

    // Tek blog yayınlama
    $('#publish-blog').on('click', function() {
        // Görsel anahtar kelimesini veya başlığı kullan
        var title = $('#generation-result').data('title');
        var imageKeyword = $('#generation-result').data('imageKeyword') || title;
        
        // Öne çıkan görsel arama
        searchFeaturedImage(imageKeyword, function(imageData) {
            var blogData = {
                action: 'publish_blog_post',
                nonce: aiBlogGenerator.nonce,
                title: title,
                content: $('#generation-result').data('content'),
                category: $('#generation-result').data('category'),
                tags: $('#generation-result').data('tags'),
                image_id: imageData ? imageData.image_id : 0
            };

            $.post(aiBlogGenerator.ajax_url, blogData, function(response) {
                if (response.success) {
                    alert('Blog yazısı başarıyla yayınlandı!');
                    window.open(response.data.post_url, '_blank');
                    $('#generation-result').hide();
                } else {
                    alert('Hata: ' + response.data.message);
                }
            });
        });
    });

    // Tek blog oluşturma fonksiyonu
    function generateSingleBlog(prompt) {
        $('#generate-blog').prop('disabled', true);
        
        $.post(aiBlogGenerator.ajax_url, {
            action: 'generate_blog_content',
            nonce: aiBlogGenerator.nonce,
            prompt: prompt
        }, function(response) {
            $('#generate-blog').prop('disabled', false);
            
            if (response.success) {
                $('#blog-content').html(response.data.content);
                $('#generation-result')
                    .data('title', response.data.title)
                    .data('content', response.data.content)
                    .data('category', response.data.category)
                    .data('tags', response.data.tags)
                    .data('imageKeyword', $('#image-keyword').val()) // Görsel anahtar kelimesini kaydet
                    .show();
            } else {
                alert('Hata: ' + response.data.message);
            }
        });
    }

    // Otomatik blog oluştur ve yayınla
    async function generateAndPublishBlog(prompt, customImageKeyword) {
        return new Promise((resolve, reject) => {
            $.post(aiBlogGenerator.ajax_url, {
                action: 'generate_blog_content',
                nonce: aiBlogGenerator.nonce,
                prompt: prompt
            }, function(response) {
                if (response.success) {
                    // Blog içeriği oluşturuldu, görsel ara
                    // Özel anahtar kelimesi varsa onu kullan, yoksa başlığı kullan
                    const imageKeyword = customImageKeyword || response.data.title;
                    
                    searchFeaturedImage(imageKeyword, function(imageData) {
                        // Şimdi yayınla
                        $.post(aiBlogGenerator.ajax_url, {
                            action: 'publish_blog_post',
                            nonce: aiBlogGenerator.nonce,
                            title: response.data.title,
                            content: response.data.content,
                            category: response.data.category,
                            tags: response.data.tags,
                            image_id: imageData ? imageData.image_id : 0
                        }, function(pubResponse) {
                            if (pubResponse.success) {
                                resolve();
                            } else {
                                reject(pubResponse.data.message);
                            }
                        });
                    });
                } else {
                    reject(response.data.message);
                }
            });
        });
    }
    
    // Öne çıkan görsel arama
    function searchFeaturedImage(query, callback) {
        $('.image-loading').show();
        
        $.post(aiBlogGenerator.ajax_url, {
            action: 'search_featured_image',
            nonce: aiBlogGenerator.nonce,
            query: query
        }, function(response) {
            $('.image-loading').hide();
            
            if (response.success) {
                // Görsel önizleme göster
                $('.image-preview img').attr('src', response.data.image_url);
                $('.image-preview .image-attribution').text(response.data.image_attribution);
                $('.image-preview').show();
                
                callback(response.data);
            } else {
                // Hata durumunda görselsiz devam et
                console.warn('Görsel arama hatası:', response.data.message);
                $('.image-preview').hide();
                
                if (response.data.continue_without_image) {
                    // Görsel olmadan devam edilebilir
                    showNotification('Uyarı: ' + response.data.message + ' Blog yazısı görselsiz yayınlanacak.', 'warning');
                    callback(null);
                } else {
                    // Kritik hata, devam edilemez
                    showNotification('Hata: ' + response.data.message, 'error');
                    callback(null);
                }
            }
        }).fail(function(xhr) {
            $('.image-loading').hide();
            $('.image-preview').hide();
            console.error('Görsel arama isteği başarısız oldu', xhr);
            
            // Ajax isteği tamamen başarısız olduğunda da devam et
            showNotification('Görsel arama isteği başarısız oldu. Blog yazısı görselsiz yayınlanacak.', 'warning');
            callback(null);
        });
    }
    
    // Bildirim gösterme fonksiyonu
    function showNotification(message, type) {
        // Bildirim alanı yoksa oluştur
        if ($('#notification-area').length === 0) {
            $('body').append('<div id="notification-area"></div>');
        }
        
        const notificationId = 'notification-' + Date.now();
        const notificationClass = type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success');
        
        // Bildirim kutusunu oluştur
        const notification = $('<div class="notification ' + notificationClass + '" id="' + notificationId + '">' + 
                              '<span class="message">' + message + '</span>' +
                              '<span class="close-btn">&times;</span>' +
                              '</div>');
        
        // Bildirim alanına ekle
        $('#notification-area').append(notification);
        
        // Kapatma butonuna tıklama işlevini ekle
        $('#' + notificationId + ' .close-btn').on('click', function() {
            $('#' + notificationId).fadeOut(300, function() { $(this).remove(); });
        });
        
        // 5 saniye sonra otomatik kapat
        setTimeout(function() {
            $('#' + notificationId).fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }
});