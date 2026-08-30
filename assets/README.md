# `assets/` — paylaşılan ön uç varlıkları

Bu dizin, ui-core'u vendor'layan **her** eklentiye giden varlıkları taşır: paylaşılan
React kaynakları, admin CSS'i, ikonlar.

## Nasıl adreslenir

Asla başka bir eklentinin URL sabitini elle yazma. Kazanan kopyayı iki yardımcı verir
(ikisi de `bootstrap.php`'de, yani **sürüm seçicinin kazananında** tanımlı):

```php
if ( function_exists( 'mhmuicore_asset_url' ) ) {
    wp_enqueue_style(
        'mhmuicore-admin',
        mhmuicore_asset_url( 'react/admin.css' ),
        array(),
        mhmuicore_version()
    );
}
```

- `mhmuicore_asset_url( $relative )` → herkese açık URL
- `mhmuicore_asset_path( $relative )` → mutlak dosya yolu
- `mhmuicore_version()` → **booting eden** kopyanın sürümü (önbellek kırma için bunu kullan,
  tüketen eklentinin kendi sürümünü değil)

🔴 **`function_exists()` koruması zorunlu.** Sahada hâlâ ui-core **0.2.x** kazanıyor olabilir;
o sürümde bu fonksiyonlar yok. Korumasız çağrı fatal atar.

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
