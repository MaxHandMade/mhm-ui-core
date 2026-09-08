# `assets/` — paylaşılan ön uç varlıkları

Bu dizin, ui-core'u vendor'layan **her** eklentiye giden varlıkları taşır: paylaşılan
React kaynakları, admin CSS'i, ikonlar.

## Nasıl adreslenir

Asla başka bir eklentinin URL sabitini elle yazma, ve kendi `wp_enqueue_style()`'ini kendin
kurma. Kazanan kopya (`bootstrap.php`, **sürüm seçicinin kazananı**) tek bir yardımcı verir:

```php
if ( function_exists( 'mhmuicore_enqueue_kit' ) ) {
    mhmuicore_enqueue_kit( 'admin' ); // ya da 'front' / 'pro'
}
```

`mhmuicore_enqueue_kit( $surface, $fallback_root = '' )` handle'ı, URL'yi VE sürümü kendi
seçer — tüketici hiçbirini elle yazmaz:

- `'admin'` / `'front'` / `'pro'` paketin sabit handle'larına kaydolur (`mhmuicore-admin` vb.)
- `'pro'` tek başına yeterlidir: `admin.css`'i de kendisi kuyruğa alır ve ona **bağımlı** kılar
  (`pro.css`'in token'ları `admin.css`'te yaşar — ayrı ayrı çağırmak gerekmez, ikisini de bu
  tek çağrı halleder)
- Free bir çekirdek kendi `assets/`'inden `pro.css`'i budamışsa (`.distignore`), `$fallback_root`
  bir Pro tüketicinin kendi ui-core kopyasına düşmesini sağlar — kazanan yine paketin kendisidir,
  tüketici yalnız bir kök adı verir
- Dosya hiçbir kopyada yoksa boş dize döner: sessiz 404 yerine ölçülebilir "hiç"

Alt seviye yardımcılar (`mhmuicore_asset_url()`, `mhmuicore_asset_path()`, `mhmuicore_version()`)
hâlâ `bootstrap.php`'de tanımlı ve kendi URL/yol/sürüm ihtiyacın için kullanılabilir, ama bir
kit stylesheet'i kuyruğa almak için **`mhmuicore_enqueue_kit()`i kullan** — el yazımı
`wp_enqueue_style()` çağrısı, spec'in yasakladığı "tüketicinin kendi vendor yolunu enqueue etmesi"
kalıbına geri döner.

🔴 **`function_exists()` koruması zorunlu.** Sahada hâlâ ui-core **0.2.x** kazanıyor olabilir;
o sürümde bu fonksiyon yok. Korumasız çağrı fatal atar.

## Neden `register.php`'de değil

İki dosya **farklı kurallarla** seçiliyor:

| Dosya | Koruma | Kazanan |
|---|---|---|
| `register.php` | `function_exists()` | **ilk yükleyen** eklenti |
| `bootstrap.php` | `defined( MHMUICORE_VERSION )` | **en yüksek sürüm** |

Varlık yardımcısı `register.php`'de olsaydı, ilk yükleyen eski kopyadan gelebilirdi —
üstelik o kopyada `assets/` hiç olmayabilir. `bootstrap.php`'de tanımlıyken yardımcı,
işaret ettiği dosyalarla **aynı kopyadan** gelir (`MHMUICORE_DIR` o kopyanın `__DIR__`'ı).

## Düzen

```
assets/
  react/
    admin.css   paketin kendi --mhmui-* yönetici paleti
```

🔴 **Buraya ne girer, `src-react/`'e ne girer.** Ayrım kaynak/çıktı değil, **nasıl tüketildiği**:

| | `assets/` | `src-react/` |
|---|---|---|
| Nasıl kullanılır | doğrudan servis edilir (`wp_enqueue_*`) | tüketicinin derlemesine `import` edilir |
| Adreslenmesi | `mhmuicore_asset_url()` / `_path()` | göreli yol (`../../vendor/mhm/ui-core/src-react/…`) |
| Taşınabilir mi | evet, kimse yola bağlı değil | **hayır** — Lite'ta 4, Pro'da 5 `import` yola çakılı |

`admin.css` 2026-08-30'a kadar `src-react/` altındaydı ve bu yüzden **yüklenemiyordu**: yardımcı
`assets/` altına bakıyor, dosya orada değildi. Üç belge (bu dosya, `bootstrap.php`'nin docblock'u,
`AssetLocatorTest`) doğru düzeni tarif ediyordu; yalnız ağaç uymuyordu. Testler de bunu göremedi,
çünkü hepsi dize kurulumunu ölçüyordu, diske hiç dokunmuyordu — artık bir varlık iddiası var.

## Sevkiyat

Tüketen eklentinin `.distignore`'u belirler. Bugün `mhm-rentiva` `/vendor/*` + `!/vendor/mhm/`
ile ui-core'un tamamını ZIP'e alıyor, `tests/` ve `.github/` gibi geliştirme dosyalarını ayrıca
dışlıyor. **`assets/` varsayılan olarak sevk edilir** — istenen budur.

📌 ui-core'a eklenen her şey **hem Lite hem Pro** ZIP'ine girer. Boyut payını ölç
(2026-08-26 taban çizgisi: `mhm-rentiva.6.1.0.zip` içinde 6 dosya / 23.4 KB).
