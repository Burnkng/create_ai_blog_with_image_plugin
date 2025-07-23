# Automation Create Blog With Image WordPress Eklentisi

Bu WordPress eklentisi, blog içeriği oluşturmayı otomatikleştirmek için tasarlanmıştır.

## Kurulum

1. Eklenti dosyalarını WordPress'in `wp-content/plugins` dizinine yükleyin
2. WordPress yönetici panelinden eklentiyi etkinleştirin

## Özellikler

- OpenAI ChatGPT (GPT-4.1 mini/nano) ile otomatik, SEO uyumlu ve detaylı blog yazısı oluşturma
- Unsplash API ile otomatik ve telifsiz öne çıkan görsel bulma ve ekleme
- Tek seferde birden fazla (toplu) blog yazısı oluşturma ve otomatik yayınlama
- Her blog için anahtar kelimeyle görsel arama ve görsel seçme imkanı
- Blog içeriği, başlık, kategori ve etiketlerin otomatik olarak ayrıştırılması ve eklenmesi
- Blog oluşturulmadan önce içerik ve görsel önizlemesi
- Toplu üretimde ilerleme çubuğu ve durum takibi
- Kullanıcı dostu, modern ve etkileşimli WordPress yönetici arayüzü
- Başarılı işlem, hata ve uyarılar için bildirim sistemi
- Otomatik olarak görseli medya kütüphanesine kaydetme ve yazıya öne çıkan görsel olarak atama
- Kategori ve etiketlerin otomatik oluşturulması ve yazıya eklenmesi
- API anahtarlarını kolayca yönetme ve ayarlama

## Gereksinimler

- WordPress 5.0 veya üstü
- PHP 7.4 veya üstü

## Lisans

GPL v2 veya üstü 

## API Anahtarları Nasıl Alınır?

### Unsplash API Anahtarı

1. [https://unsplash.com/join](https://unsplash.com/join) adresinden bir Unsplash hesabı oluşturun veya giriş yapın.
2. Sağ üstteki menüden "Developers/API" bölümüne gidin veya doğrudan [https://unsplash.com/developers](https://unsplash.com/developers) adresine gidin.
3. "Your Apps" (Uygulamalarınız) bölümüne girin ve "New Application" (Yeni Uygulama) butonuna tıklayın.
4. Uygulamanız için bir isim ve açıklama girin, gerekli şartları kabul edin ve uygulamayı oluşturun.
5. Uygulama detay sayfasında "Access Key" (Erişim Anahtarı) ve "Secret Key" (Gizli Anahtar) bilgilerini göreceksiniz. Erişim anahtarınızı kopyalayın ve eklentide ilgili ayara yapıştırın.
6. Demo modunda saatte 50 istek hakkınız olur. Daha yüksek limit için "Apply for Production" başvurusu yapabilirsiniz.

Daha fazla bilgi için: [Unsplash API Dokümantasyonu](https://unsplash.com/documentation)

### OpenAI ChatGPT API Anahtarı

1. [https://platform.openai.com/](https://platform.openai.com/) adresinden bir OpenAI hesabı oluşturun veya giriş yapın.
2. Sağ üstte profil simgesine tıklayın ve açılan menüden "View API Keys" (API Anahtarlarını Görüntüle) seçeneğine tıklayın.
3. "Create new secret key" (Yeni gizli anahtar oluştur) butonuna tıklayın.
4. Oluşan anahtarı kopyalayın ve güvenli bir yerde saklayın. Bu anahtarı eklentide ilgili ayara yapıştırın.

Not: OpenAI API anahtarınızı kimseyle paylaşmayın ve herkese açık ortamlarda yayınlamayın.

Daha fazla bilgi için: [OpenAI API Dokümantasyonu](https://platform.openai.com/docs/) 