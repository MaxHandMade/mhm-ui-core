# mhm/ui-core — Türkçe özet

> Bu dosya hatırlatma amaçlıdır. Sözleşmenin resmi ve güncel hâli [README.md](README.md)
> (İngilizce); ikisi çelişirse **İngilizce olan geçerlidir**.

## Bu paket ne işe yarar?

MHM WordPress eklentilerinin **ortak arayüz altyapısı**. Eklenti değildir, **Composer
paketidir**: kendi başına kurulmaz, eklentilerin içine gömülür. Tek cümleyle: **bir sonraki
eklenti sıfırdan başlamasın diye.**

Üç sorumluluğu var ve üçü de v0.8.0'dan itibaren pakette:

1. **Bileşen fabrikası** — bir bileşen **bir kez** tanımlanır (her parça Sabit / Veri / Ayar),
   paket ondan **dört yüzey** türetir: shortcode, Gutenberg bloğu, Elementor widget'ı ve Layout
   adapter'ı. Elle yazılan tek şey renderer'dır. `wp mhm-ui make:component` iskeleti açar.
2. **React yönetici kiti** — her yönetici ekranının enqueue işi tek çağrıda; paylaşılan JS
   modülleri (REST istemci fabrikası, `useApi`, para biçimlendirme, hata sınırı) ve görsel
   bileşenler (istatistik kartı, KPI kutusu, durum rozeti, sayfalama, Pro kilidi, bildirim,
   panel); tek token kaynağından üretilen `--mhmui-*` paleti.
3. **Lite/Pro dikişi** — ücretsiz çekirdek boş **yuvalar** ilan eder ve **yetenekler** tanımlar,
   Pro eklentisi doldurur; `wp mhm-ui check:purity` çekirdeğin PHP ve JS yüzeyini okur ve üç
   cevaptan biriyle biter: temiz · bulgu · **karar veremediği yerler** — kapsamı ve sınırı aşağıda
   yazılı. WordPress.org'un crippleware yasağına uyum, sonradan kazı değil doğuştan.

Altında duran tesisat: **sürüm farkındalıklı yükleyici** (bir sitede kaç eklenti taşırsa taşısın
en yüksek sürüm boot eder) ve **Layout motorunun saf çekirdeği** (`src/Layout/`, manifest →
markup; veritabanına yazan kalıcı katman bilerek üründe).

İçinde **iş mantığı, lisans kodu ve dış HTTP çağrısı yoktur.** İçine gömüldüğü her WordPress
eklentisi gibi GPL'dir.

## Kapsam — üç sorumluluk, üçü de pakette

Paketi tanımlayan şey faz sayacı değil **sözleşmesi**. Tasarım dokümanı (2026-07-14) üç
sorumluluk tanımlıyor; **2026-09-03**'te (v0.8.0) üçü de koda döküldü ve gerçek WordPress'e
karşı ölçüldü:

| # | Sorumluluk | Pakette | Kanıt |
|---|---|---|---|
| 1 | **Bileşen fabrikası** — bir sözleşme (Sabit/Veri/Ayar), dört yüzey: shortcode · Gutenberg bloğu · Elementor widget'ı · Layout adapter'ı | `src/Component/` · `wp mhm-ui make:component` | Rentiva'nın `featured-vehicles` bloğu sözleşmeye geri çevrilip **bayt bayt yeniden üretildi** (`FeaturedVehiclesReproductionTest`) · dört yüzey aynı girdiye **aynı** tipli ayarları veriyor (`SurfacesTest`) |
| 2 | **React yönetici kiti** — sayfa yükleyici + REST istemcisi + görsel bileşenler + tek token kaynağı | `bootstrap.php` · `src-react/` · `src-react/tokens.json` → `npm run tokens:build` | `kit.test.jsx` · `tokens.test.js` (kaynak ile CSS arasında sürüklenme kapısı, mutasyonla sınandı) |
| 3 | **Lite/Pro dikişi** — çekirdek boş **yuva** ilan eder, Pro doldurur; **yetenek** verir; **saflık kapısı** çekirdekte HTTP/lisans/yapay limit olmadığını kanıtlar | `src/Seam/` · `wp mhm-ui check:purity` | `PilotSeamTest` — gerçek WP'de iki fixture eklenti (çekirdek + Pro): shortcode ve blok Pro'nun dolgusunu yuvadan alıyor, tarayıcı çekirdeği **temiz**, Pro'yu **kirli** buluyor (negatif kontrol) |

Bunların altındaki tesisat (yükleyici hakemliği · Layout motorunun saf çekirdeği · `--mhmui-*`
isim uzayı kapıları) daha önce vardı ve olduğu gibi duruyor.

### Bileşen fabrikası nasıl çalışır

```php
// contracts/hero.php — tek tasarım kararı: her parça Sabit / Veri / Ayar
return array(
    'slug'     => 'hero',
    'title'    => __( 'Hero', 'my-plugin' ),        // ürün çevirir, paket değil
    'settings' => array(                            // AYAR → shortcode attr + block attr + Elementor kontrolü
        'title'      => array( 'type' => 'string',  'default' => '' ),
        'showButton' => array( 'type' => 'boolean', 'default' => true ),
        'columns'    => array( 'type' => 'integer', 'default' => 3 ),
        'layout'     => array( 'type' => 'enum',    'enum' => array( 'grid', 'slider' ), 'default' => 'grid' ),
    ),
    'data'     => array( 'items' ),                 // VERİ → renderer sorgular
);

// Elle yazılan TEK şey: renderer (SABİT parçalar burada yaşar)
final class HeroRenderer implements \MHMUiCore\Component\ComponentRenderer {
    public function render( array $settings, array $context ): string { /* … */ }
}

// Ürün kimliği bir kez, sonra her bileşen tek satır
$factory = mhmuicore_component_factory( array(
    'prefix' => 'myplugin', 'block_namespace' => 'my-plugin', 'text_domain' => 'my-plugin',
) );
$hero = $factory->register( new ComponentContract( require 'contracts/hero.php' ), new HeroRenderer() );
// → [myplugin_hero …] · <!-- wp:my-plugin/hero --> · Elementor widget (Elementor yüklüyse) · $hero->layout_adapter()
```

🔴 **Ayar allowlist'i sözleşmenin kendisidir:** bildirilmemiş bir attribute renderer'a hiç ulaşmaz,
bildirilen her biri **tipli** gelir — `'1'`/`'0'` (shortcode), `true`/`false` (blok), `'yes'`/`''`
(Elementor) hepsi aynı `bool`'a iner. Dört yüzey bu yüzden asla ayrışamaz.

**Scaffold:** `wp mhm-ui make:component hero --prefix=myplugin --block-namespace=my-plugin
--text-domain=my-plugin --php-namespace='MyPlugin\Components'` → sözleşme dosyası, renderer
iskeleti, `block.json`, test iskeleti. Var olan dosyanın üstüne **yazmaz**.

### Dikiş nasıl çalışır

```php
// Ücretsiz çekirdek: yuvaları ADIYLA ilan eder, yetenekleri tanımlar
$seam = mhmuicore_slot_registry( 'myplugin' );
$seam->declare_slot( 'hero_after', 'Hero markup sonrasına eklenecek şey.' );
$caps = mhmuicore_capabilities( 'myplugin' );
// … renderer içinde:
$html = $seam->apply( 'hero_after', $html, $settings );
if ( $caps->has( 'pro_badge' ) ) { /* daha fazlasını yap — asla daha azını */ }

// Pro eklentisi: doldurur ve verir
$seam->fill( 'hero_after', fn( $html ) => $html . '<div class="upsell">…</div>' );
$caps->grant( 'pro_badge' );
```

İlan edilmemiş bir yuvayı doldurmak **fırlatır** — sessizce boşa düşmez. Her yuva ayrıca
`{prefix}_seam_{slot}` WordPress kancasına köprülenir; üçüncü taraf sınıfı bilmeden takılabilir.

🔴 **Yetenek bir "yapabilir"dir, "yapmasın" değil.** `if ( ! $caps->has('x') ) { çekirdeğin
yapabildiği şeyi reddet }` crippleware'dir; `if ( $caps->has('x') ) { fazlasını yap }` dikiştir.

**Saflık kapısı:** `wp mhm-ui check:purity <çekirdek-dizini>` — çekirdeğin PHP ve JavaScript
yüzeyini okur; PHP'nin tarayıcıya verdiği JavaScript de dahil (`wp_add_inline_script`, heredoc,
`<script>`). **Üç** cevaptan biriyle biter: **temiz** · **bulgu** · **karar veremediği yerler**.
🔴 Üçüncüsü geçiş değildir; okuyacak dosya bulamadığı ağaç da, açamadığı dosya da öyle. Sonda her
koşumdan önce iki dilde fixture koşar ve yarısı koşmamışsa kendini bozuk ilan eder.

**PHP tarafı:** `wp_remote_get/post/request/head` + `wp_safe_` biçimleri · `curl_init` · `curl_exec` ·
`fsockopen` · `stream_socket_client` — düz ya da tam nitelikli (`\wp_remote_get`) yazılsın. İkinci bir
liste yalnız **kanıtla** konuşur: `wp_enqueue_script/style` · `wp_register_script/style` ·
`download_url` · `wp_remote_fopen` · `file_get_contents` · `fopen` · `get_headers` ·
`simplexml_load_file` — bunlara **mutlak URL** verildiğinde bulgu, verilmediğinde sessizdir; CDN'den
font çekmek WP.org'un reddettiği şekildir, yerel dosya okumak ise bu fonksiyonların işi. Lisans ve
yapay limit sözcükleri tanımlayıcı, değişken ve katar literallerinde (interpolasyonlu olanlar dahil),
`snake_case`/`camelCase` ayrımı gözetilmeden aranır.

**JS tarafı:** karar **çağrının kendi hedef argümanında** verilir — çevresindeki blokta değil, argüman
listesinin geri kalanında da değil: `fetch` · `sendBeacon` · `XMLHttpRequest.open` · `axios` ·
`jQuery.ajax/get/post/getJSON` · `WebSocket` · `EventSource` · `importScripts` · `import()` ·
`window.open`, ayrıca `location`/`src`/`action`'a atanan ve `setAttribute`'a verilen URL'ler. Hedef
argüman bir seçenek nesnesiyse hedef onun `url`/`path`/`src` özelliğidir; yükün geri kalanı başkasının
işidir. Mutlak URL'ye çözülen hedef — doğrudan ya da dosyanın **tam bir kez** bağladığı bir ad
üzerinden — bulgudur; aynı siteye çözülen temizdir, `ajaxurl` ve `wpApiSettings` de öyle (WordPress'in
kendisinin doldurduğu iki global). Gerisi **karar verilemedi**'dir: yüksek sesle söylenir, yutulmaz.

🔴 **Kapının sınırları iddianın parçasıdır.** Değişken fonksiyon adıyla yapılan PHP çağrısı ve iki
listede adı geçmeyen bir kütüphanenin isteği görülmez. Çalışma anında kurulan JS hedefi temiz değil,
karar verilemedi sayılır — ve REST kökünü `wp_localize_script` ile tarayıcıya veren bir eklenti her
istek için bir "karar verilemedi" toplar, çünkü bu kapı bir değeri PHP'den localize yüküne kadar
**izlemez**. Verbi çalışma anında olan `xhr.open( method, url )` sink olarak okunur: yalnız literal URL
varken konuşur. jQuery'nin `.attr( 'src', … )`'i, `axios.create({ baseURL })` ve `$.getScript`
yukarıdaki biçimler arasında **değildir**. Üretilmiş paket — satırı 2000 karakteri aşan dosya —
okunmaz, karar verilemedi olur; kapıyı paketin üretildiği **kaynaklara** doğrult. `vendor/`,
`node_modules/`, `tests/`, `.git/` hiç okunmaz — ZIP'e giren vendor'lanmış lisans istemcisi bu koşumun
dışındadır. PHP'nin içinde kapı kodu düzyazıdan ayıramaz; oradaki JavaScript yalnız kanıtla raporlanır.

Gerçek bir 475 dosyalık Lite çekirdeğinde ölçüldü: **3 bulgu, 29 dosyada 58 karar verilemedi** —
50'si yükü PHP'den gelen tek bir `$.ajax({ url: vars.ajax_url })` deyimi, 4'ü üretilmiş paket, 4'ü
gerçek çalışma-anı hedefi. Cevabın dürüst şekli budur: **okunacak kısa bir liste**, temiz kâğıdı değil.

### React kiti ve token kaynağı

`src-react/index.js`: `StatCard` · `StatsGrid` · `KpiBox` · `StatusBadge` · `Pagination` ·
`ProLock` · `Notice` · `Widget` (+ önceki `ErrorBoundary`, `createApiClient`, `useApi`,
`createFormatter`). **Her dize prop'tur** — paketin text domain'i yok, çeviriyi ürün yapar.

Tek token kaynağı `src-react/tokens.json`: `npm run tokens:build` `assets/react/admin.css`'teki
`--mhmui-*` bloğunu yeniden üretir, `npm run tokens:check` CI'da sürüklenmeyi kırmızı yapar.
JS tarafında `import { tokens } from '…/src-react'` ile aynı değerler.

### Bilerek yapılmayanlar

- **Rentiva göç etmedi.** Paket ikinci bir tüketicinin sınamasından geçmeden Rentiva'nın 16 bloğunu
  ve widget'larını fabrikaya taşımak, tek örneğe karşı yeniden yazım olurdu. Fabrika Rentiva'nın en
  büyük bloğunu **yeniden üretebildiğini** test olarak kanıtladı; taşıma, Rentiva'nın kendi
  penceresinde ve kendi sırasında.
- **Layout kalıcılığı (AtomicImporter, rollback, audit) üründe.** Hiçbir ürünün ikinci bir kopyaya
  ihtiyacı yok; ihtiyaç doğduğunda taşınır.
- **npm registry'ye yayın yok.** `package.json` artefakt olarak hazır (`main`, `exports`,
  `peerDependencies`); tüketiciler bugün `vendor/mhm/ui-core/src-react` yolundan import ediyor.

## Nerede kullanılıyor?

| Eklenti | Nasıl geliyor |
|---|---|
| `mhm-rentiva` (ücretsiz, WP.org) | `composer.json` → `mhm/ui-core`, ZIP'e dahil |
| `mhm-rentiva-pro` (lisanslı) | aynı sürüm zorunlu — `check-uicore-parity` kapısı bunu kilitler |

🔴 **İki eklenti de kendi kopyasını taşır ve kendi kopyasını kaydeder.** Aynı sitede ikisi
birden varsa, `plugins_loaded` önceliği 0'da **en yüksek sürüm** kazanır ve yalnız o boot eder.
Kaydetmeyen eklenti bu yarışa hiç girmez — bu, 2026-08-27'ye kadar Pro'nun durumuydu ve Pro'yu
sessizce Lite'ın boot etmesine bağımlı kılıyordu.

## Yeni bir eklentiye nasıl eklenir?

🔴 **Paket yeni bir ürüne kendiliğinden girmez.** Dört elle adım ister ve her adımın kayıtlı bir
kırılma biçimi vardır. Aşağıdaki değerler 2026-09-03'te v0.8.0'a karşı ölçüldü.

**1. Composer — depo + require.** Paket Packagist'te **değil**, o yüzden `require` tek başına
yetmez; VCS deposu da tanıtılır:

```jsonc
"repositories": [
    { "type": "vcs", "url": "https://github.com/MaxHandMade/mhm-ui-core" }
],
"require": { "mhm/ui-core": "^0.7" }
```

🔴 `^0.x` **yamayı** kendiliğinden alır, **minörü almaz**. Bu bilinçlidir: sevk yüzeyi değişince
minör bump edilir ve alım kararı tüketicinin olur.

**2. Kayıt + sürüm literali.** `## Nasıl kullanılır?` bölümündeki iki satır. Sürüm dizesi elle
yazılır ve kurulu paketle aynı olmak zorundadır — düşük yazmak eski bir kopyanın yarışı
kazanmasına yol açar ve **sessizdir**. Bu yüzden `bin/check-uicore-version.php` kapısı da
kopyalanır; ölçüldü ki bu literal üç sürüm boyunca sürüklenmiş ve hiçbir kapı görmemişti.

**3. `.distignore` — altı satır, yirmi sekiz değil.** Composer kurulumu paketin `export-ignore`
kurallarına uyar: `tests/` · `bin/` · `docker/` · `.github/` ve linter yapılandırmaları
tüketicinin ağacına **hiç inmez** (ölçüldü: `vendor/mhm/ui-core` 28 dosya taşıyor, hepsi sevk
yüzeyi). Dolayısıyla onları tek tek dışlayan satırlar ölü koddur. Gereken yalnız bu:

```gitignore
/vendor/*
!/vendor/mhm/
/vendor/mhm/ui-core/README.md
/vendor/mhm/ui-core/README-tr.md
/vendor/mhm/ui-core/assets/README.md
/vendor/mhm/ui-core/package.json
```

🔴 **Sıra anlamlıdır:** `/vendor/*` + negation bilinçli — `/vendor/` dizini toptan dışlanırsa
içindeki hiçbir şey geri alınamaz. Ve üç dışlama satırı `!/vendor/mhm/`'den **sonra** gelmek
zorundadır; son eşleşen kazanır.

🔴 **`package.json` neden vendor'da kalıp ZIP'ten çıkıyor:** içinde `"sideEffects": false` var
ve webpack `src-react/` modüllerini bundle ederken onu okur. Paketten `export-ignore` ile
atılsaydı tree shaking sessizce kapanırdı. ZIP'e girmemesinin sebebi ayrı: o noktada derleme
çoktan bitmiştir. İki mekanizma birbirinin yedeği değil, iki farklı ana ait.

📌 README'ler ZIP'e girmez ama vendor'da kalır; birçok eklentinin kendi `.distignore`'unda
çapasız bir `README.md` deseni vardır ve ui-core'unkini de tesadüfen yakalar. Yukarıdaki satırlar
o tesadüfe güvenmez.

**4. Pro'su olan üründe beşinci adım.** Lite ve Pro **aynı** ui-core sürümünü istemek zorundadır;
parite kapısı eşitlik arar, uyumluluk değil.

## Nasıl kullanılır?

**1. Kayıt** — tüketici eklentinin ana dosyasında, `plugins_loaded`'dan önce:

```php
require_once __DIR__ . '/vendor/mhm/ui-core/register.php';
mhmuicore_register( '0.8.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
```

🔴 Sürüm dizesi **elle yazılır** (kayıt, herhangi bir bootstrap yüklenmeden önce koşar) ve
kurulu paketle aynı olmak zorundadır. Düşük yazmak, eski bir kopyanın yarışı kazanmasına yol
açar ve **sessizdir** — o yüzden her iki eklentide de `bin/check-uicore-version.php` kapısı var.
Ölçüldü: bu literal üç sürüm boyunca sürüklenmiş ve hiçbir kapı görmemişti.

**2. React yönetici sayfası** — dört adımı tek çağrı yapar (REST nonce middleware'i istek
başına bir kez, `wp-components` stili, `@wordpress/scripts`'in ürettiği bağımlılık listesi ve
sürümüyle bundle, ve JSON çeviri katalogları):

```php
if ( function_exists( 'mhmuicore_enqueue_react_page' ) ) {
    mhmuicore_enqueue_react_page( array(
        'page'          => 'dashboard',        // build/admin/dashboard.js
        'base_dir'      => MY_PLUGIN_DIR,      // sonunda taksim
        'base_url'      => MY_PLUGIN_URL,
        'handle_prefix' => 'my-plugin-react-', // handle = önek + page
        'version'       => MY_PLUGIN_VERSION,  // yalnız yedek
        'text_domain'   => 'my-plugin',
    ) );
}
```

🔴 **`function_exists()` ile koru:** sitede kazanan kopya daha eski olabilir, o zaman bu
fonksiyon yoktur. Üç şeyi bilerek yapmaz: altı zorunlu anahtarın **varsayılanı yoktur**
(paketin text domain'i ve sabitleri yok, boş dize de reddedilir — tanımsız bir sabitin çöktüğü
şekil budur) · çağıranın `version`'ı **manifest'i ezmez** (o bir içerik hash'idir; tersi, eski
cache anahtarıyla yeni bayt sevk eder) · dizi argüman alır, çünkü paket API'sine **yalnız
ekleme** yapabilir.

**3. Varlık bulucular** — `mhmuicore_version()` · `mhmuicore_asset_path()` ·
`mhmuicore_asset_url()`. Hepsi **boot eden kopyaya** göre çözülür.

**4. Paylaşılan JS** (`src-react/`) — `createFormatter` · `createApiClient( ns, apiFetch )` ·
`useApi` · `ErrorBoundary`. Göreli yolla import edilir:
`../../vendor/mhm/ui-core/src-react/...` (çıplak `@mhm/ui-core` **kullanılamaz**).

🔴 **Uç haritası pakete AİT DEĞİLDİR.** Paylaşılan olan **fabrikadır**; her eklenti kendi REST
uç haritasını kendi taşır. Bu kural 2026-08-27'de pahalıya öğrenildi: Pro'nun beş yönetici
ekranı Lite'ın haritasını okuyordu, WP.org ayrıştırması o haritadan ücretli bölümleri sildi ve
beş ekran birden kırıldı.

## Değişmez kurallar

- **Sürüm yalnız EKLER.** Kaldırılan veya yeniden adlandırılan API, sahadaki eski tüketiciyi
  kırar. `0.2.0` bilinçli olarak geriye uyumluluk takma adı bırakmadı (gerekçesi README.md'de).
- **Locator'lar `bootstrap.php`'de, `register.php`'de değil.** İkisi **farklı kurallarla**
  seçilir: `register.php` → `function_exists()`, yani **ilk yükleyen** kazanır; `bootstrap.php`
  → **en yüksek sürüm** kazanır. Karıştırmak, varlık yolunun eski bir kopyadan gelmesi demektir.
- **Tag = dağıtım, ama yalnız aralık içindeyse.** `^0.3`'e sabitlenmiş bir tüketiciye `v0.4.x`
  **inmez**; ancak constraint bilerek yükseltilince gelir.
- **Paket, içine gireceği ağaçtan daha gevşek denetlenemez.** PHPCS burada `WordPress-Extra`
  koşar; daha dar bir setle "temiz" görünüp tüketicinin kapısını kırmıştı (v0.4.0 → v0.4.1).
