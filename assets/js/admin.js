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

        // Önce tüm resimler için arama yapıp kullanıcıya göster
        try {
            const allImages = await searchAllImages(imageKeyword || topics[0], 'multiple', topics.length);
            if (!allImages || allImages.length === 0) {
                if (!confirm('Hiç resim bulunamadı. Görselsiz devam etmek istiyor musunuz?')) {
                    return;
                }
            } else {
                // Resim seçme modalını göster
                showImageSelectionModal(allImages, async function(selectedImages) {
                    if (selectedImages.length < topics.length) {
                        alert('Seçilen resim sayısı (' + selectedImages.length + '), blog sayısından (' + topics.length + ') az. Eksik bloglar görselsiz oluşturulacak.');
                    }
                    
                    await processMultipleBlogs(mainTopic, topics, selectedImages);
                });
                return;
            }
        } catch (error) {
            console.error('Resim arama hatası:', error);
            if (!confirm('Resim aramada hata oluştu. Görselsiz devam etmek istiyor musunuz?')) {
                return;
            }
        }
        
        // Resim seçilmediyse veya hata oluştuysa görselsiz devam et
        await processMultipleBlogs(mainTopic, topics, []);
    });

    // Çoklu blog işleme fonksiyonu
    async function processMultipleBlogs(mainTopic, topics, selectedImages) {
        $('#multiple-mode button').prop('disabled', true);
        $('#generation-progress').show();
        $('.progress').width('0%');

        for (let i = 0; i < topics.length; i++) {
            $('#current-topic').text(topics[i]);
            $('.progress').width((i / topics.length * 100) + '%');
            
            let combinedPrompt = mainTopic + '\n\nAlt Konu: ' + topics[i];
            
            try {
                // Seçilen resim varsa onu kullan, yoksa görselsiz devam et
                const selectedImage = selectedImages[i] || null;
                await generateAndPublishBlog(combinedPrompt, null, selectedImage);
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
    }

    // Tek blog yayınlama
    $('#publish-blog').on('click', function() {
        // Görsel anahtar kelimesini veya başlığı kullan
        var title = $('#generation-result').data('title');
        var imageKeyword = $('#generation-result').data('imageKeyword') || title;
        
        // Öne çıkan görsel arama ve seçme
        searchAllImages(imageKeyword, 'single').then(function(images) {
            if (!images || images.length === 0) {
                if (confirm('Hiç resim bulunamadı. Görselsiz devam etmek istiyor musunuz?')) {
                    publishWithImage(null);
                }
                return;
            }
            
            // Resim seçme modalını göster
            showImageSelectionModal(images, function(selectedImages) {
                if (selectedImages.length > 0) {
                    publishWithImage(selectedImages[0]);
                } else {
                    if (confirm('Resim seçilmedi. Görselsiz devam etmek istiyor musunuz?')) {
                        publishWithImage(null);
                    }
                }
            });
        }).catch(function(error) {
            console.error('Resim arama hatası:', error);
            if (confirm('Resim aramada hata oluştu. Görselsiz devam etmek istiyor musunuz?')) {
                publishWithImage(null);
            }
        });
    });
    
    // Seçilen resimle yayınlama
    function publishWithImage(selectedImage) {
        var blogData = {
            action: 'publish_blog_post',
            nonce: aiBlogGenerator.nonce,
            title: $('#generation-result').data('title'),
            content: $('#generation-result').data('content'),
            category: $('#generation-result').data('category'),
            tags: $('#generation-result').data('tags'),
            image_id: 0
        };
        
        // Eğer seçilen resim varsa, önce indir ve sonra yayınla
        if (selectedImage) {
            // Resim yükleme göstergesi
            $('.image-loading').show();
            
            // Resmi WordPress medya kütüphanesine yükle
            uploadSelectedImage(selectedImage.url, $('#generation-result').data('title'), selectedImage.attribution)
                .then(function(response) {
                    $('.image-loading').hide();
                    if (response.success) {
                        // Resim yüklendi, şimdi blog yazısını yayınla
                        blogData.image_id = response.data.image_id;
                        publishBlogPost(blogData);
                        
                        // Resim önizlemeyi göster
                        $('.image-preview img').attr('src', response.data.image_url);
                        $('.image-preview .image-attribution').text(selectedImage.attribution);
                        $('.image-preview').show();
                    } else {
                        // Resim yüklenemedi, görselsiz yayınla
                        alert('Resim yüklenemedi: ' + response.data.message + '\nGörselsiz devam ediliyor.');
                        publishBlogPost(blogData);
                    }
                })
                .catch(function(error) {
                    $('.image-loading').hide();
                    alert('Resim yükleme hatası: ' + error + '\nGörselsiz devam ediliyor.');
                    publishBlogPost(blogData);
                });
        } else {
            // Görselsiz yayınla
            publishBlogPost(blogData);
        }
    }
    
    // Blog yazısını yayınla
    function publishBlogPost(blogData) {
        $.post(aiBlogGenerator.ajax_url, blogData, function(response) {
            if (response.success) {
                alert('Blog yazısı başarıyla yayınlandı!');
                window.open(response.data.post_url, '_blank');
                $('#generation-result').hide();
            } else {
                alert('Hata: ' + response.data.message);
            }
        });
    }

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
    async function generateAndPublishBlog(prompt, customImageKeyword, selectedImage) {
        return new Promise((resolve, reject) => {
            $.post(aiBlogGenerator.ajax_url, {
                action: 'generate_blog_content',
                nonce: aiBlogGenerator.nonce,
                prompt: prompt
            }, function(response) {
                if (response.success) {
                    // Blog içeriği oluşturuldu
                    const blogData = {
                        action: 'publish_blog_post',
                        nonce: aiBlogGenerator.nonce,
                        title: response.data.title,
                        content: response.data.content,
                        category: response.data.category,
                        tags: response.data.tags,
                        image_id: 0
                    };
                    
                    // Eğer önceden seçilmiş bir resim varsa
                    if (selectedImage) {
                        // Resmi WordPress medya kütüphanesine yükle
                        uploadSelectedImage(selectedImage.url, response.data.title, selectedImage.attribution)
                            .then(function(uploadResponse) {
                                if (uploadResponse.success) {
                                    blogData.image_id = uploadResponse.data.image_id;
                                }
                                // Resim yüklendi veya yüklenemedi, her durumda blog yazısını yayınla
                                publishAndResolve(blogData, resolve, reject);
                            })
                            .catch(function() {
                                // Hata durumunda görselsiz yayınla
                                publishAndResolve(blogData, resolve, reject);
                            });
                    } else {
                        // Seçilmiş resim yoksa görselsiz yayınla
                        publishAndResolve(blogData, resolve, reject);
                    }
                } else {
                    reject(response.data.message);
                }
            });
        });
    }
    
    // Blog yazısını yayınla ve promise'i çöz
    function publishAndResolve(blogData, resolve, reject) {
        $.post(aiBlogGenerator.ajax_url, blogData, function(pubResponse) {
            if (pubResponse.success) {
                resolve();
            } else {
                reject(pubResponse.data.message);
            }
        }).fail(function(xhr) {
            reject('Yayınlama isteği başarısız: ' + xhr.statusText);
        });
    }
    
    // Tüm resimleri arama
    function searchAllImages(query, mode = 'single', topic_count = 1) {
        return new Promise((resolve, reject) => {
            $.post(aiBlogGenerator.ajax_url, {
                action: 'search_featured_image',
                nonce: aiBlogGenerator.nonce,
                query: query,
                mode: mode,
                topic_count: topic_count
            }, function(response) {
                if (response.success) {
                    resolve(response.data.images);
                } else {
                    reject(response.data.message);
                }
            }).fail(function(xhr) {
                reject('Görsel arama isteği başarısız oldu: ' + xhr.statusText);
            });
        });
    }
    
    // Seçilen resmi WordPress medya kütüphanesine yükle
    function uploadSelectedImage(imageUrl, title, attribution) {
        return new Promise((resolve, reject) => {
            $.post(aiBlogGenerator.ajax_url, {
                action: 'upload_selected_image',
                nonce: aiBlogGenerator.nonce,
                image_url: imageUrl,
                title: title,
                description: attribution
            }, function(response) {
                resolve(response);
            }).fail(function(xhr) {
                reject(xhr.statusText);
            });
        });
    }
    
    // Resim seçme modalını göster
    function showImageSelectionModal(images, callback) {
        // Eğer modal zaten varsa kaldır
        $('#image-selection-modal').remove();
        
        // Modal HTML oluştur
        var modalHtml = `
            <div id="image-selection-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Resim Seçin</h2>
                    <p>Blog yazıları için kullanmak istediğiniz resimleri seçin. Birden fazla seçim yapabilirsiniz.</p>
                    <div class="image-grid"></div>
                    <div class="modal-actions">
                        <button id="select-images" class="button button-primary">Seçili Resimleri Kullan</button>
                        <button id="cancel-selection" class="button">İptal</button>
                    </div>
                </div>
            </div>
        `;
        
        // Modalı body'e ekle
        $('body').append(modalHtml);
        
        // Resimleri grid'e ekle
        var $imageGrid = $('#image-selection-modal .image-grid');
        images.forEach(function(image, index) {
            var imageHtml = `
                <div class="image-item" data-index="${index}">
                    <img src="${image.thumb}" alt="Resim ${index + 1}">
                    <div class="image-overlay">
                        <span class="selection-indicator">✓</span>
                    </div>
                    <div class="image-caption">${image.attribution}</div>
                </div>
            `;
            $imageGrid.append(imageHtml);
        });
        
        // Resim seçme işlevi
        $('.image-item').on('click', function() {
            $(this).toggleClass('selected');
        });
        
        // Modalı göster
        $('#image-selection-modal').show();
        
        // Kapat butonunu işle
        $('.modal .close, #cancel-selection').on('click', function() {
            $('#image-selection-modal').remove();
            callback([]);  // Boş dizi döndür
        });
        
        // Seçimi tamamla
        $('#select-images').on('click', function() {
            var selectedImages = [];
            $('.image-item.selected').each(function() {
                var index = $(this).data('index');
                selectedImages.push(images[index]);
            });
            $('#image-selection-modal').remove();
            callback(selectedImages);
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