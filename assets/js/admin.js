jQuery(document).ready(function($) {
    const generateButton = $('#generate-blog');
    const promptTextarea = $('#blog-prompt');
    const resultContainer = $('#generation-result');
    const blogContent = $('#blog-content');
    const publishButton = $('#publish-blog');
    let generatedTitle = '';
    let generatedCategory = '';
    let generatedTags = [];

    generateButton.on('click', function() {
        const prompt = promptTextarea.val().trim();
        
        if (!prompt) {
            alert('Lütfen bir prompt girin!');
            return;
        }

        // UI'ı yükleniyor durumuna getir
        generateButton.addClass('loading').prop('disabled', true);
        generateButton.text('Oluşturuluyor...');

        // API isteği
        $.ajax({
            url: aiBlogGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_blog_content',
                prompt: prompt,
                nonce: aiBlogGenerator.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Yanıtı işle
                    generatedTitle = response.data.title;
                    generatedCategory = response.data.category || '';
                    generatedTags = response.data.tags || [];
                    
                    // İçeriği göster - başlığı içeriğe dahil etmiyoruz çünkü WordPress başlığı ayrı bir alanda saklar
                    blogContent.html(response.data.content);
                    
                    // Başlık, kategori ve etiketleri göster
                    let metaHTML = `
                        <div class="blog-meta">
                            <h2 class="blog-title">${generatedTitle}</h2>
                            <div class="blog-taxonomy">
                    `;
                    
                    if (generatedCategory) {
                        metaHTML += `<p><strong>Kategori:</strong> ${generatedCategory}</p>`;
                    }
                    
                    if (generatedTags && generatedTags.length > 0) {
                        metaHTML += `<p><strong>Etiketler:</strong> ${generatedTags.join(', ')}</p>`;
                    }
                    
                    metaHTML += `</div></div>`;
                    
                    // Meta bilgileri içerikten önce göster
                    blogContent.prepend(metaHTML);
                    
                    resultContainer.show();
                } else {
                    alert('Hata: ' + response.data.message);
                }
            }, 
            error: function() {
                alert('İçerik oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.');
            },
            complete: function() {
                generateButton.removeClass('loading').prop('disabled', false);
                generateButton.text('Blog Oluştur');
            }
        });
    });

    publishButton.on('click', function() {
        // Başlık meta bilgilerinden çıkarılıyor, içerik ise meta bilgileri olmadan alınıyor
        const metaSection = blogContent.find('.blog-meta');
        let content = '';
        
        if (metaSection.length) {
            // Meta bölümünü kaldır ve sadece içeriği al
            metaSection.remove();
            content = blogContent.html();
        } else {
            content = blogContent.html();
        }

        if (!content) {
            alert('Yayınlanacak içerik bulunamadı!');
            return;
        }

        publishButton.addClass('loading').prop('disabled', true);
        publishButton.text('Yayınlanıyor...');

        $.ajax({
            url: aiBlogGenerator.ajax_url,
            type: 'POST',
            data: {
                action: 'publish_blog_post',
                title: generatedTitle,
                content: content,
                category: generatedCategory,
                tags: generatedTags,
                nonce: aiBlogGenerator.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Blog yazısı başarıyla yayınlandı!');
                    // Sadece içerik alanını temizle, promptu koru
                    blogContent.empty();
                    resultContainer.hide();
                } else {
                    alert('Hata: ' + response.data.message);
                }
            },
            error: function() {
                alert('Yazı yayınlanırken bir hata oluştu. Lütfen tekrar deneyin.');
            },
            complete: function() {
                publishButton.removeClass('loading').prop('disabled', false);
                publishButton.text('Yayınla');
            }
        });
    });
}); 