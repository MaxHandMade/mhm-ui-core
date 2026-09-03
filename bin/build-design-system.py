#!/usr/bin/env python3
# Builds the Claude Design design-system bundle for mhm-ui-core.
#
#   python bin/build-design-system.py   ->  build/design-system/
#   then: DesignSync finalize_plan(projectId, localDir=build/design-system,
#         writes=[README.md, foundations/tokens.html, components/*.html], deletes=[])
#         + write_files(localPath=...)
#   Claude Design project: mhm-ui-core (54e0bee9-f818-4621-824d-799907c264ec), created 2026-09-03.
#
# Station 4 of the house pipeline (SENKRON): run after every change to
# src-react/components or tokens.json, then push with DesignSync, or Claude
# Design starts drawing with components that are not ours.
#
# One preview HTML per component, each with the @dsCard marker on line 1 that
# the Design System pane indexes. The DOM in every preview is exactly what the
# JSX in src-react/components emits, and the stylesheet is the package's own
# assets/react/admin.css inlined -- so what Claude Design draws with is what
# WordPress renders, not a hand-drawn approximation.
import io, json, os, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, "build", "design-system")

css = io.open(ROOT + "/assets/react/admin.css", encoding="utf-8").read()
tokens = json.loads(io.open(ROOT + "/src-react/tokens.json", encoding="utf-8").read())
version = json.loads(io.open(ROOT + "/package.json", encoding="utf-8").read())["version"]

# Minimal wp-admin chrome the previews depend on (Dashicons glyphs are replaced
# by a labelled box so the preview needs no font download).
WP_CHROME = """
  body { margin: 0; padding: 24px; background: #f0f0f1; color: #1d2327; font: 13px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
  .button { display: inline-block; padding: 0 10px; min-height: 30px; line-height: 2.15384615; font-size: 13px; border: 1px solid #2271b1; border-radius: 3px; background: #f6f7f7; color: #2271b1; cursor: pointer; }
  .button:disabled { color: #a7aaad; border-color: #dcdcde; background: #f6f7f7; cursor: default; }
  .notice { background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 1px 12px; position: relative; }
  .notice p { margin: .5em 0; padding: 2px; }
  .notice-success { border-left-color: #00a32a; } .notice-warning { border-left-color: #dba617; }
  .notice-error { border-left-color: #d63638; } .notice-info { border-left-color: #72aee6; }
  .notice-dismiss { position: absolute; top: 0; right: 1px; border: none; margin: 0; padding: 9px; background: none; color: #787c82; cursor: pointer; }
  .notice-dismiss::before { content: "\\2715"; font-size: 14px; }
  .screen-reader-text { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(1px,1px,1px,1px); }
  .dashicons { display: inline-block; width: 20px; height: 20px; font-size: 20px; line-height: 1; text-align: center; }
  .dashicons::before { content: "\\25C6"; }
  .ds-note { margin: 0 0 18px; font-size: 12px; color: #646970; max-width: 68ch; }
  .ds-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; margin-bottom: 18px; }
  .ds-label { font-size: 11px; letter-spacing: .05em; text-transform: uppercase; color: #646970; margin: 14px 0 6px; }
"""

def page(group, title, note, body, width=760):
    return (
        '<!-- @dsCard group="%s" name="%s" viewport="%d" -->\n' % (group, title, width)
        + "<!doctype html>\n<html lang=\"tr\"><head><meta charset=\"utf-8\"><title>%s — mhm-ui-core %s</title>\n" % (title, version)
        + "<style>\n" + WP_CHROME + "\n/* ---- assets/react/admin.css (v%s), inlined verbatim ---- */\n" % version + css + "\n</style></head>\n"
        + "<body class=\"mhmui-admin\">\n<p class=\"ds-note\">%s</p>\n%s\n</body></html>\n" % (note, body)
    )

files = {}

# ---- Tokens ----------------------------------------------------------------
swatches = []
for name, value in tokens["tokens"].items():
    is_color = value.startswith("#")
    swatches.append(
        '<div style="width:150px"><div style="height:56px;border-radius:4px;border:1px solid #c3c4c7;background:%s"></div>'
        '<div style="font-family:ui-monospace,Consolas,monospace;font-size:12px;margin-top:6px">--mhmui-%s</div>'
        '<div style="font-size:12px;color:#646970">%s</div></div>' % (value if is_color else "#fff", name, value)
    )
files["foundations/tokens.html"] = page(
    "Foundations", "Tokens",
    "Tek kaynak: <code>src-react/tokens.json</code>. CSS özel özellikleri buradan üretilir (<code>npm run tokens:build</code>); "
    "tasarımda başka renk kullanılmaz. Paket yalnız <code>--mhmui-*</code> tanımlar; ürünler <code>--mhm-*</code> kendi isim uzayında.",
    '<div class="ds-row">' + "\n".join(swatches) + "</div>",
    width=900,
)

# ---- Components (DOM = what the JSX emits) --------------------------------
def stat_card(label, value, tone, icon=True, delta=None, sub=None):
    line = ""
    if delta and delta[0] != "flat":
        line = '<p class="mhmui-stat-card__delta mhmui-stat-card__delta--%s">%s</p>' % delta
    elif sub:
        line = '<p class="mhmui-stat-card__sub">%s</p>' % sub
    return ('<div class="mhmui-stat-card mhmui-stat-card--%s">%s<div class="mhmui-stat-card__body">'
            '<p class="mhmui-stat-card__label">%s</p><p class="mhmui-stat-card__value">%s</p>%s</div></div>'
            % (tone, '<span class="dashicons" aria-hidden="true"></span>' if icon else "", label, value, line))

files["components/stat-card.html"] = page(
    "Components", "StatCard",
    "<b>StatCard</b> — etiket · biçimlendirilmiş değer · isteğe bağlı delta ya da alt satır. Prop'lar: label, value, icon, tone (blue/green/amber/grey/red), sub, delta{direction,text}. "
    "Her dize prop'tur: paketin text domain'i yok, çeviriyi ürün yapar.",
    '<div class="ds-label">Tonlar</div><div class="ds-row" style="display:grid;grid-template-columns:repeat(2,1fr)">'
    + stat_card("Toplam Rezervasyon", "1.284", "blue", delta=("up", "↑ %12 bu ay"))
    + stat_card("Toplam Gelir", "₺418.900", "green", delta=("down", "↓ %3 bu ay"))
    + stat_card("Aktif Araç", "37", "amber", sub="52 toplam")
    + stat_card("Bu ay kiralayan", "63", "grey", delta=("flat", ""), sub="")
    + stat_card("İptal", "4", "red", sub="son 7 gün")
    + "</div>",
)

files["components/stats-grid.html"] = page(
    "Components", "StatsGrid",
    "<b>StatsGrid</b> — StatCard satırı. Prop'lar: cards[] (StatCard prop nesneleri, key = label), columns (varsayılan 4).",
    '<div class="mhmui-stats-grid" style="grid-template-columns:repeat(4,1fr)">'
    + stat_card("Rezervasyon", "1.284", "blue", delta=("up", "↑ %12 bu ay"))
    + stat_card("Gelir", "₺418.900", "green", delta=("up", "↑ %8 bu ay"))
    + stat_card("Aktif Araç", "37", "amber", sub="52 toplam")
    + stat_card("Kiralayan", "63", "grey", sub="bu ay")
    + "</div>",
    width=960,
)

def kpi(value, label, tone):
    return ('<div class="mhmui-kpi-box mhmui-kpi-box--%s"><p class="mhmui-kpi-box__value">%s</p>'
            '<p class="mhmui-kpi-box__label">%s</p></div>' % (tone, value, label))
files["components/kpi-box.html"] = page(
    "Components", "KpiBox",
    "<b>KpiBox</b> — küçük anahtar rakam: değer üstte, etiket altta. Prop'lar: value, label, tone (blue/green/amber/grey/red).",
    '<div class="ds-row">' + kpi("%92", "Doluluk", "blue") + kpi("18", "Bekleyen", "amber") + kpi("₺12.400", "Depozito", "green") + kpi("3", "Gecikmiş", "red") + kpi("241", "Müşteri", "grey") + "</div>",
)

files["components/status-badge.html"] = page(
    "Components", "StatusBadge",
    "<b>StatusBadge</b> — durum rozeti. Ürün kendi alan durumlarını beş tona eşler; paket 'onaylandı'nın anlamını değil 'success'in görünüşünü bilir. Prop: tone (success/warning/danger/info/neutral).",
    '<div class="ds-row">'
    '<span class="mhmui-status mhmui-status--success">Onaylandı</span>'
    '<span class="mhmui-status mhmui-status--warning">Bekliyor</span>'
    '<span class="mhmui-status mhmui-status--danger">İptal</span>'
    '<span class="mhmui-status mhmui-status--info">Taslak</span>'
    '<span class="mhmui-status mhmui-status--neutral">Arşiv</span>'
    "</div>",
    width=560,
)

files["components/pagination.html"] = page(
    "Components", "Pagination",
    "<b>Pagination</b> — önceki / sayfa x / y / sonraki. Prop'lar: page, totalPages, onChange(page), labels{previous,next,of,navigation}. Uçlarda düğme devre dışı.",
    '<div class="ds-label">Ortada</div>'
    '<nav class="mhmui-pagination" aria-label="Sayfalar"><button type="button" class="button mhmui-pagination__button">Önceki</button>'
    '<span class="mhmui-pagination__status">3 / 12</span><button type="button" class="button mhmui-pagination__button">Sonraki</button></nav>'
    '<div class="ds-label">İlk sayfada</div>'
    '<nav class="mhmui-pagination" aria-label="Sayfalar"><button type="button" class="button mhmui-pagination__button" disabled>Önceki</button>'
    '<span class="mhmui-pagination__status">1 / 12</span><button type="button" class="button mhmui-pagination__button">Sonraki</button></nav>',
    width=560,
)

files["components/pro-lock.html"] = page(
    "Components", "ProLock",
    "<b>ProLock</b> — yetenek verilmişse çocukları, yoksa ürünün verdiği fallback'i gösterir. Bir GÖRÜNÜM tercihidir, kapı değil: çekirdek yapabildiği şeyi asla saklamaz; saklanan, eklenti olmadan var olmayan Pro ekranıdır. Prop'lar: unlocked, fallback, children.",
    '<div class="ds-label">Kilitli</div><div class="mhmui-pro-lock">Bayi raporları Pro eklentisiyle gelir.</div>'
    '<div class="ds-label">Açık</div><div class="mhmui-widget"><div class="mhmui-widget__header"><h3 class="mhmui-widget__title">Bayi raporları</h3></div><div class="mhmui-widget__body">…</div></div>',
    width=620,
)

files["components/notice.html"] = page(
    "Components", "Notice",
    "<b>Notice</b> — WordPress'in kendi bildirim biçiminde satır içi bildirim. Prop'lar: tone (success/warning/error/info), onDismiss, dismissLabel, children.",
    '<div class="notice notice-success mhmui-notice mhmui-notice--success" role="status"><p>Ayarlar kaydedildi.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Kapat</span></button></div>'
    '<div class="notice notice-warning mhmui-notice mhmui-notice--warning" role="status"><p>Lisans 12 gün içinde doluyor.</p></div>'
    '<div class="notice notice-error mhmui-notice mhmui-notice--error" role="status"><p>Dışa aktarma başarısız: dosya yazılamadı.</p></div>'
    '<div class="notice notice-info mhmui-notice mhmui-notice--info" role="status"><p>Yeni sürüm hazır.</p></div>',
    width=620,
)

files["components/widget.html"] = page(
    "Components", "Widget",
    "<b>Widget</b> — başlıklı panel; çoğu yönetici ekranının yapı taşı. Prop'lar: title, subtitle, icon, actions, children.",
    '<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title"><span class="dashicons" aria-hidden="true"></span>Son rezervasyonlar<span class="mhmui-widget__subtitle">son 7 gün</span></h3>'
    '<div class="mhmui-widget__actions"><a href="#" class="button">Tümü</a></div></header>'
    '<div class="mhmui-widget__body"><div class="mhmui-stats-grid" style="grid-template-columns:repeat(3,1fr)">'
    + kpi("14", "Yeni", "blue") + kpi("9", "Teslim", "green") + kpi("2", "İade", "amber")
    + "</div></div></section>",
    width=720,
)

# ---- README for the designers ---------------------------------------------
files["README.md"] = u"""# mhm-ui-core — tasarım sistemi (v%s)

Bu proje `mhm-ui-core` paketinin **gerçek** bileşen kütüphanesidir: her kartın DOM'u
`src-react/components/*.jsx`'in ürettiğinin aynısı, stili paketin `assets/react/admin.css`'i.
Burada çizilen, WordPress'te aynen render olur.

## Kurallar
- **Renkler yalnız Foundations › Tokens'tan** (`--mhmui-*`). Başka renk = tasarımda hata.
- **Her dize prop'tur.** Paketin text domain'i yok; etiketleri ürün çevirir.
- **Yönetici ekranı = React**, ekran durum taşıyorsa. Taşımıyorsa WP Settings API.
- **Ön yüz (shortcode · blok · Elementor) React DEĞİL**: tek Bileşen Sözleşmesinden geçer —
  her parça **Sabit / Veri / Ayar**; üç yüzey ondan türer, elle yazılan tek şey renderer.
- Claude Design **üretim kodu üretmez**; devir paketi üretir, kodu Claude Code yazar.

## Bileşenler
StatCard · StatsGrid · KpiBox · StatusBadge · Pagination · ProLock · Notice · Widget
(+ görünmeyenler: ErrorBoundary, createApiClient, useApi, createFormatter)

## Senkron
`DesignSync` ile bileşen bileşen; kaynak `mhm-ui-core` deposu. Bu paket değişince burası
yeniden eşitlenir; aksi hâlde Claude Design bizim olmayan bileşenlerle çizmeye başlar.
""" % version

os.makedirs(OUT, exist_ok=True)
for rel, content in files.items():
    path = os.path.join(OUT, rel.replace("/", os.sep))
    os.makedirs(os.path.dirname(path), exist_ok=True)
    io.open(path, "w", encoding="utf-8", newline="\n").write(content)
    print("wrote", rel, len(content), "bytes")
print("BUNDLE", OUT)
