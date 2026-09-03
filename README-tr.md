# mhm/ui-core — Türkçe özet

> Bu dosya hatırlatma amaçlıdır. Sözleşmenin resmi ve güncel hâli [README.md](README.md)
> (İngilizce); ikisi çelişirse **İngilizce olan geçerlidir**.

## Bu paket ne işe yarar?

MHM WordPress eklentilerinin **ortak arayüz altyapısı**. Eklenti değildir, **Composer
paketidir**: kendi başına kurulmaz, eklentilerin içine gömülür.

Üç sorumluluğu var:

1. **React yönetici kiti** — her yönetici ekranının ihtiyaç duyduğu enqueue işini tek yerde
   toplar; paylaşılan JS modülleri (para biçimlendirme, REST istemci fabrikası, `useApi`
   hook'u, hata sınırı bileşeni).
2. **Bileşen fabrikası** — tek bir bileşen sözleşmesinden shortcode, Gutenberg bloğu ve
   Elementor widget'ı üretmek. **v0.6.0'dan beri motorun saf çekirdeği burada:**
   `src/Layout/` altında **12 dosya / 1.381 satır** (`LayoutEngine` · `CompositionBuilder` ·
   `TokenMapper` · `BlueprintValidator` · `AdapterRegistry` · `Normalization` · `DiffService`
   + sözleşme ve hata kodları). 🔴 **Kalıcı katman bilerek üründe kaldı** — veritabanına yazan
   içe-aktarma/geri-alma, ürüne özel adaptörler ve WP-CLI komutu. Sebep ölçülmüş bir sınırdır:
   paketin WordPress test koşumu yok, `wp_insert_post`'u stub'layıp atomik geri almayı "test
   etmek" mock'u test etmek olurdu.
3. **Sürüm dikişi** — WordPress.org'a uygun ücretsiz çekirdeğin, lisanslı Pro eklentisine
   açtığı uzantı noktaları.

İçinde **iş mantığı, lisans kodu ve dış HTTP çağrısı yoktur.** İçine gömüldüğü her WordPress
eklentisi gibi GPL'dir.

## Kapsam — ne yapıyor, neyi bilerek yapmıyor

Paketi tanımlayan şey bir faz sayacı değil, **sözleşmesi**: yükleyici hakemliği, React yönetici
hattı, Layout motorunun saf çekirdeği ve `--mhmui-*` isim uzayı sınırı. Bunların dördü de
sevk ediliyor, iki üründe çalışıyor ve kapı altında. Altı fazlı tasarım dokümanı MHM'nin iç
geliştirme deposunda yaşıyor; **2026-09-03**'te kapatılan hâli şudur:

| Faz | Ne | Sonuç |
|---|---|---|
| 0 | İskelet — depo, composer/npm, CI, kalite kapıları | ✅ |
| 1 | Token birleştirme | ✅ **tanımı değişerek** — aşağı bak |
| 2 | Layout motoru pakete | ✅ **saf çekirdek + tüketici göçü** (v0.6.0); kalıcı katman **rafta**, aşağı bak |
| 3 | React kiti pakete — `enqueue_react_page()` + paylaşılan JSX | ✅ (v0.4.x) |
| 4 | Bileşen scaffold'u — `wp mhm-ui make:component` | ⛔ **düşürüldü** — aşağı bak |
| 5 | İkinci ürün pilotu | ⏳ **ikinci ürün bağlandığında kendiliğinden olur**, ayrı iş değil |

### Faz 1 — iki yarısı yapıldı, sorusu değişti, ve bu artık cevap

Ürün tarafındaki birleştirme (Rentiva'nın **101 kanonik token**'ı) ve paketin kendi 15 tokenli
React paleti (**2026-08-29**) — ikisi de kapandı. Tasarım dokümanı *"tek token kaynağı"* istiyordu;
ortaya çıkan şey **bilerek ayrılmış iki isim uzayı** (`--mhmui-*` paket / `--mhm-*` ürün).
Çakışma yasak olduğu için değil **yapısal olarak imkânsız** olduğu için bitti. Bu, Faz 1'in
tamamlanması değil sorusunun düzeltilmesidir — ve düzeltilmiş soru cevaplanmış olduğundan ✅.

Kararı sabitleyen şey belge değil **kapı**: `bin/check-css-namespace.mjs` (stylelint + Babel AST)
ve `bin/check-php-namespace.php` (`token_get_all()`) paketin sevk yüzeyini ölçer ve `--mhmui-*`
dışında bir custom property tanımlamasını ya da okumasını, bir de ID seçicisi kullanmasını
imkânsız kılar. İkisi de CI'da; `tests/Gate/` altında 78 koşumluk regresyon bataryası var.
🔴 Kapıların kendisi `export-ignore`'lu — tüketicinin `vendor/`'una **inmezler**.

### Faz 2 — kalıcı katman neden rafta

Ön koşulu (gerçek WordPress'e karşı entegrasyon koşumu) kurulmuş, taşınacak koddaki sekiz kusur
bulunup **yaşadığı yerde** düzeltilmişti. Durduran şey karşılığın olmamasıydı: o kod bugün
yayınlanmış bir eklentide çalışıyor ve testle korunuyor; taşımanın getirisi ancak **ikinci bir
tüketiciyle** doğar ve bugün hiçbir ürünün layout kalıcılığına ihtiyacı yok (ölçüldü). Rafı
kaldıracak şart adıyla yazılı: ikinci bir ürün buna gerçekten ihtiyaç duyduğunda.

### Faz 4 — neden düşürüldü

Scaffold CLI'ın karşılığı *çok bileşen × çok ürün*. Tek tüketicili bir pakette bu, Faz 2'yi rafa
kaldıran sınıfın aynısıdır: getirisi ileride, maliyeti anında, doğrulaması tek örneğe karşı.
"Yapılmadı" diye taşımak bitmemişlik hissini sonsuza yayar; bilerek düşürüldü. İkinci ürün üç
dört bileşen sonra aynı şablonu elle yazmaktan yorulursa, o gün gerçek bir talep olur.

### Faz 5 — ayrı bir iş değil

Gerçek yanlışlama başka bir ürünün tüketmesidir; aynı üründe pilot yapmak Rentiva'nın
varsayımlarını Rentiva'yla sınamaktır. Aday belli ve bağlanma anı kendi belgesinde yazılı —
"Yeni bir eklentiye nasıl eklenir?" bölümü tam olarak o gün için var.

📌 Üçüncü sorumluluk (**katman dikişi**) için pakette **kod yok**; Lite↔Pro dikişi Rentiva'nın
kendi içinde yaşıyor. Bu bir eksik değil, ölçülmüş bir sınır: ikinci bir Lite/Pro çifti gelene
kadar dikişin genel biçimi bilinemez.

**Kısacası:** ui-core, bugün istenen işi yapan, iki üründe sevk edilen, kapı altında bir
pakettir. Açık kalem yok; kalan hayatı bakım ve gerçek bir tüketicinin isteyeceği şeydir.

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
kırılma biçimi vardır. Aşağıdaki değerler 2026-09-03'te v0.7.1'e karşı ölçüldü.

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
mhmuicore_register( '0.7.1', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
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
