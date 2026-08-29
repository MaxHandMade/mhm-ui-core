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
   Elementor widget'ı üretmek. 🔴 **Bu motor YAZILMIŞ durumda ama burada değil:**
   `mhm-rentiva/src/Layout/` altında **16 dosya / ~2.100 satır** olarak yaşıyor
   (`AdapterRegistry` · `CompositionBuilder` · `TokenMapper` · `BlueprintValidator` ·
   `AtomicImporter` · `ContractRules` · adaptörler + CLI). Tasarım dokümanının **Faz 2**'si
   tam olarak bunun pakete taşınmasıdır — yani eksik olan kod değil, **kodun yeri**.
3. **Sürüm dikişi** — WordPress.org'a uygun ücretsiz çekirdeğin, lisanslı Pro eklentisine
   açtığı uzantı noktaları.

İçinde **iş mantığı, lisans kodu ve dış HTTP çağrısı yoktur.** İçine gömüldüğü her WordPress
eklentisi gibi GPL'dir.

## Bitti mi? — Hayır: altı fazın ikisi

Tasarım dokümanı (`rentiva-dev/docs/superpowers/specs/2026-07-14-mhm-ui-core-design.md`)
altı faz tanımlıyor. **2026-08-29** itibarıyla ölçülen durum:

| Faz | Ne | Durum |
|---|---|---|
| 0 | İskelet — depo, composer/npm, CI, kalite kapıları | ✅ **bitti** |
| 1 | Token birleştirme — tek token kaynağı | 🟡 **iki yarısı da yapıldı, tanımı değişti** (aşağı bak) |
| 2 | **Layout motoru pakete** | ⬜ başlamadı — *kod var, Rentiva'da* |
| 3 | **React kiti pakete** — `enqueue_react_page()` + paylaşılan JSX | ✅ **2026-08-27** (v0.4.x) |
| 4 | Bileşen scaffold'u — `wp mhm-ui make:component` | ⬜ başlamadı |
| 5 | İkinci ürün (greenfield pilot) — dikiş doğuştan | ⬜ başlamadı |

📌 **Faz 3, 1 ve 2'den önce yapıldı.** Sıra bilerek bozuldu: canlı kusur oradaydı (Pro'nun
beş yönetici ekranı, eklenti sınırını aşan bir çağrıya bağlıydı). Bu, 1 ve 2'yi kolaylaştırmaz —
yalnız erteler.

📌 Üçüncü sorumluluk (**katman dikişi**) için de pakette bugün **kod yok**; Lite↔Pro dikişi
Rentiva'nın kendi içinde yaşıyor.

### Faz 1 neden 🟡 — iki yarısı yapıldı ama sorusu değişti

Faz 1'in iki yarısı vardı ve **ikisi de kapandı**: ürün tarafındaki birleştirme (Rentiva'nın
**101 kanonik token**'ı) daha önce, paketin kendi 15 tokenli React paleti **2026-08-29**'da.

Ama tasarım dokümanı Faz 1'i *"tek token kaynağı — hem `TokenMapper`'ı hem admin React'i besler"*
diye tanımlıyordu. Yapılan şey bu **değil**: paket `--mhmui-*`, ürünler `--mhm-*` — yani tek kaynak
değil, **bilerek ayrılmış iki isim uzayı**. Çakışma böylece yasak olduğu için değil **yapısal
olarak imkânsız** olduğu için bitti. Bu, Faz 1'i tamamlamak mı yoksa sorusunu değiştirmek mi —
henüz karara bağlanmadı, o yüzden ✅ değil 🟡.

Kararı sabitleyen şey belge değil **kapı**: `bin/check-css-namespace.mjs` (stylelint + Babel AST)
ve `bin/check-php-namespace.php` (`token_get_all()`) paketin sevk yüzeyini ölçer ve `--mhmui-*`
dışında bir custom property tanımlamasını ya da okumasını, bir de ID seçicisi kullanmasını
imkânsız kılar. İkisi de CI'da; `tests/gate/` altında 78 koşumluk regresyon bataryası var.
🔴 Kapıların kendisi `export-ignore`'lu — tüketicinin `vendor/`'una **inmezler**.

**Kısacası:** ui-core bugün *çalışan ve sevk edilen* bir paket, ama **tamamlanmış değil**.
Bitmiş olan: React yönetici hattı ve isim uzayı sınırı.

## Nerede kullanılıyor?

| Eklenti | Nasıl geliyor |
|---|---|
| `mhm-rentiva` (ücretsiz, WP.org) | `composer.json` → `mhm/ui-core: ^0.4`, ZIP'e dahil |
| `mhm-rentiva-pro` (lisanslı) | aynı sürüm zorunlu — `check-uicore-parity` kapısı bunu kilitler |

🔴 **İki eklenti de kendi kopyasını taşır ve kendi kopyasını kaydeder.** Aynı sitede ikisi
birden varsa, `plugins_loaded` önceliği 0'da **en yüksek sürüm** kazanır ve yalnız o boot eder.
Kaydetmeyen eklenti bu yarışa hiç girmez — bu, 2026-08-27'ye kadar Pro'nun durumuydu ve Pro'yu
sessizce Lite'ın boot etmesine bağımlı kılıyordu.

## Nasıl kullanılır?

**1. Kayıt** — tüketici eklentinin ana dosyasında, `plugins_loaded`'dan önce:

```php
require_once __DIR__ . '/vendor/mhm/ui-core/register.php';
mhmuicore_register( '0.5.0', __DIR__ . '/vendor/mhm/ui-core/bootstrap.php' );
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
