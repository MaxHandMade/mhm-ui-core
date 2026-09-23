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
# pro.css is inlined too, and the reason is a defect this file once shipped.
# 0.8.1 moved .mhmui-pro-lock out of admin.css (a free core enqueues that one,
# and a lock rule in it reads as crippleware to a reviewer). This builder kept
# inlining admin.css alone, so from that release on the pro-lock card rendered an
# UNSTYLED box: a card demonstrating a component whose rule was no longer in the
# page. Nobody saw it because the sync was never re-run after 0.8.0. The check at
# the bottom of this file exists so the next split fails loudly instead.
pro_css = io.open(ROOT + "/assets/react/pro.css", encoding="utf-8").read()
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
        + "<style>\n" + WP_CHROME
        + "\n/* ---- assets/react/admin.css (v%s), inlined verbatim ---- */\n" % version + css
        + "\n/* ---- assets/react/pro.css (v%s), inlined verbatim ---- */\n" % version + pro_css
        + "\n</style></head>\n"
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
# Direction marks (aria-hidden), same vocabulary as StatCard.php / StatCard.jsx.
# `delta` is (direction, text) or (direction, text, label) -- `label` is the
# 0.13.0 optional consumer-supplied accessible name, rendered as the
# visually-hidden .mhmui-stat-card__delta-sr span. `text` NEVER carries an
# arrow or sign (0.12.0+): the mark below is what the kit itself draws.
# Since 0.13.0 `flat` gets its own mark too (→) and its own delta line --
# "no data" (the sub line) and "no change" (a flat delta) are different
# facts. Unlike up/down, flat deliberately carries NO colour rule of its own
# in either stylesheet (the base .mhmui-stat-card__delta colour already
# reads as neutral) -- so .mhmui-stat-card__delta--flat is exempted below,
# in SHARED_RULE_MODIFIERS, from the "does every rendered class have a rule"
# self-check; otherwise this file's own flat demo card below trips it.
DIRECTION_MARKS = {"up": "↑", "down": "↓", "flat": "→"}

def stat_card(label, value, tone, icon=True, delta=None, sub=None):
    line = ""
    if delta and delta[0] in DIRECTION_MARKS:
        direction, text = delta[0], delta[1]
        label_text = delta[2] if len(delta) > 2 else None
        # Trailing space INSIDE the span's own text, not a bare text node
        # after it -- otherwise the label and text run together as one word
        # (measured 2026-09-18: "artış3 this month"). Keeps the class set
        # unchanged: the span still emits exactly one class either way.
        sr = '<span class="mhmui-stat-card__delta-sr">%s </span>' % label_text if label_text else ""
        line = ('<p class="mhmui-stat-card__delta mhmui-stat-card__delta--%s" data-direction="%s">'
                '<span class="mhmui-stat-card__delta-mark" aria-hidden="true">%s</span>%s%s</p>'
                % (direction, direction, DIRECTION_MARKS[direction], sr, text))
    elif sub:
        line = '<p class="mhmui-stat-card__sub">%s</p>' % sub
    return ('<div class="mhmui-stat-card mhmui-stat-card--%s">%s<div class="mhmui-stat-card__body">'
            '<p class="mhmui-stat-card__label">%s</p><p class="mhmui-stat-card__value">%s</p>%s</div></div>'
            % (tone, '<span class="dashicons" aria-hidden="true"></span>' if icon else "", label, value, line))

files["components/stat-card.html"] = page(
    "Components", "StatCard",
    "<b>StatCard</b> — etiket · biçimlendirilmiş değer · isteğe bağlı delta ya da alt satır. Prop'lar: label, value, icon, tone (success/warning/danger/info/neutral), sub, delta{direction,text,label}. "
    "delta.text hiçbir zaman ok/işaret taşımaz (kit kendi aria-hidden ↑/↓/→ işaretini basar -- flat de 0.13.0'dan itibaren kendi satırını alır, sub'a düşmez); delta.label isteğe bağlıdır -- tüketicinin çevirdiği erişilebilir ad (örn. \"artış\"/\"azalış\"), görsel olarak gizli ama ekran okuyucuda. "
    "Her dize prop'tur: paketin text domain'i yok, çeviriyi ürün yapar.",
    '<div class="ds-label">Tonlar</div><div class="ds-row" style="display:grid;grid-template-columns:repeat(2,1fr)">'
    + stat_card("Toplam Rezervasyon", "1.284", "info", delta=("up", "%12 bu ay", "artış"))
    + stat_card("Toplam Gelir", "₺418.900", "success", delta=("down", "%3 bu ay", "azalış"))
    + stat_card("Aktif Araç", "37", "warning", sub="52 toplam")
    + stat_card("Bu ay kiralayan", "63", "neutral", delta=("flat", "%0 bu ay"))
    + stat_card("İptal", "4", "danger", sub="son 7 gün")
    + "</div>",
)

files["components/stats-grid.html"] = page(
    "Components", "StatsGrid",
    "<b>StatsGrid</b> — StatCard satırı. Prop'lar: cards[] (StatCard prop nesneleri, key = label), columns (varsayılan 4).",
    '<div class="mhmui-stats-grid" style="--mhmui-columns:4">'
    + stat_card("Rezervasyon", "1.284", "info", delta=("up", "%12 bu ay"))
    + stat_card("Gelir", "₺418.900", "success", delta=("up", "%8 bu ay"))
    + stat_card("Aktif Araç", "37", "warning", sub="52 toplam")
    + stat_card("Kiralayan", "63", "neutral", sub="bu ay")
    + "</div>",
    width=960,
)

# KpiBox was absorbed into StatCard after v0.9.6 (unreleased at the time of
# writing). Its look did not go away -- it is
# what StatCard renders when no tone is given -- so the sheet shows that state
# next to the toned ones instead of documenting a second component.
def quiet_card(value, label):
    return ('<div class="mhmui-stat-card"><div class="mhmui-stat-card__body">'
            '<p class="mhmui-stat-card__label">%s</p>'
            '<p class="mhmui-stat-card__value">%s</p></div></div>' % (label, value))
files["components/stat-card-quiet.html"] = page(
    "Components", "StatCard (tonsuz)",
    "<b>StatCard</b> ton verilmediğinde sessiz çerçeveli kutudur. Renk <i>opt-in</i>'dir: "
    "anahtar rakam, anlamı gerektirmedikçe renk taşımaz. Ton verilince kart dolgulu hale gelir.",
    '<div class="ds-row">' + quiet_card("%92", "Doluluk") + quiet_card("18", "Bekleyen") + quiet_card("₺12.400", "Depozito") + "</div>",
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
    "<b>Notice</b> — WordPress'in kendi bildirim biçiminde satır içi bildirim. Prop'lar: tone (success/warning/danger/info), onDismiss, dismissLabel, children.",
    '<div class="notice notice-success mhmui-notice mhmui-notice--success" role="status"><p>Ayarlar kaydedildi.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Kapat</span></button></div>'
    '<div class="notice notice-warning mhmui-notice mhmui-notice--warning" role="status"><p>Lisans 12 gün içinde doluyor.</p></div>'
    '<div class="notice notice-error mhmui-notice mhmui-notice--danger" role="status"><p>Dışa aktarma başarısız: dosya yazılamadı.</p></div>'
    '<div class="notice notice-info mhmui-notice mhmui-notice--info" role="status"><p>Yeni sürüm hazır.</p></div>',
    width=620,
)

files["components/widget.html"] = page(
    "Components", "Widget",
    "<b>Widget</b> — başlıklı panel; çoğu yönetici ekranının yapı taşı. Prop'lar: title, subtitle, icon, actions, children.",
    '<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title"><span class="dashicons" aria-hidden="true"></span>Son rezervasyonlar<span class="mhmui-widget__subtitle">son 7 gün</span></h3>'
    '<div class="mhmui-widget__actions"><a href="#" class="button">Tümü</a></div></header>'
    '<div class="mhmui-widget__body"><div class="mhmui-stats-grid" style="--mhmui-columns:3">'
    + stat_card("Yeni", "14", "info") + stat_card("Teslim", "9", "success") + stat_card("İade", "2", "warning")
    + "</div></div></section>",
    width=720,
)

files["components/tabs.html"] = page(
    "Components", "Tabs",
    "<b>Tabs</b> — sayfa bölümleri, gerçek bağlantılar. Etkin sekme 2px vurgu çizgisiyle de ayrılır. Rozet: sayı aria-hidden, anlam görünmez metinde. Prop'lar: label, current, items{id,label,href,badge,badgeLabel}, onSelect.",
    '<nav class="mhmui-tabs" aria-label="Bayi yönetimi bölümleri">'
    '<a class="mhmui-tabs__tab mhmui-tabs__tab--current" href="#" aria-current="page">Bekleyen Başvurular<span class="mhmui-tabs__badge"><span aria-hidden="true">1</span><span class="mhmui-tabs__badge-sr">1 bekleyen</span></span></a>'
    '<a class="mhmui-tabs__tab" href="#">Aktif Bayiler</a>'
    '<a class="mhmui-tabs__tab" href="#">IBAN Talepleri<span class="mhmui-tabs__badge">3</span></a>'
    '<a class="mhmui-tabs__tab" href="#">Komisyon</a></nav>',
    width=720,
)

files["components/page-header.html"] = page(
    "Components", "PageHeader",
    "<b>PageHeader</b> — detay görünümünün başlığı. Varsayılan h2 (sayfanın h1'i WordPress'in). Prop'lar: back{label,href,onClick}, title, badge{text,tone}, meta, actions, level.",
    '<div class="mhmui-page-header"><a class="mhmui-page-header__back" href="#"><span aria-hidden="true">← </span>Bekleyen başvurular</a>'
    '<div class="mhmui-page-header__title-row"><h2 class="mhmui-page-header__title">Marmaris Cars</h2><span class="mhmui-status mhmui-status--warning">Beklemede</span>'
    '<div class="mhmui-page-header__actions"><a href="#" class="button">Profil</a></div></div>'
    '<p class="mhmui-page-header__meta">Bayi başvurusu #9292 · 23/09/2026 07:26</p></div>',
    width=720,
)

files["components/detail-list.html"] = page(
    "Components", "DetailList",
    "<b>DetailList</b> — etiket/değer çiftleri. Boş değer gri metin; ton metni boyamaz, önüne nokta koyar. Prop'lar: items{label,value,tone}, emptyText, columns, layout (stacked|inline).",
    '<dl class="mhmui-detail-list" style="--mhmui-columns:2">'
    '<div class="mhmui-detail-list__item"><dt class="mhmui-detail-list__label">Hizmet şehri</dt><dd class="mhmui-detail-list__value">Muğla</dd></div>'
    '<div class="mhmui-detail-list__item"><dt class="mhmui-detail-list__label">Vergi dairesi</dt><dd class="mhmui-detail-list__value mhmui-detail-list__value--empty">Girilmemiş</dd></div>'
    '</dl><hr>'
    '<dl class="mhmui-detail-list mhmui-detail-list--inline">'
    + "".join(
        '<div class="mhmui-detail-list__item"><dt class="mhmui-detail-list__label">%s</dt><dd class="mhmui-detail-list__value"><span class="mhmui-detail-list__mark mhmui-detail-list__mark--%s" aria-hidden="true"></span>%s</dd></div>'
        % (label, tone, value)
        for label, tone, value in (
            ("Belgeler", "warning", "2 eksik"),
            ("Ödeme", "success", "Tamam"),
            ("Risk", "danger", "Yüksek"),
            ("Kaynak", "info", "Form"),
            ("Not", "neutral", "Yok"),
        )
    )
    + "</dl>",
    width=620,
)

files["components/detail-layout.html"] = page(
    "Components", "DetailLayout",
    "<b>DetailLayout</b> — ana sütun + yapışkan yan sütun; kap ~800px altına inince yan sütun alta iner. Prop'lar: children, aside, asideLabel.",
    '<div class="mhmui-detail-layout"><div class="mhmui-detail-layout__main">'
    '<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title">Başvuran</h3></header><div class="mhmui-widget__body">…</div></section>'
    '<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title">Belgeler</h3></header><div class="mhmui-widget__body">…</div></section>'
    '</div><aside class="mhmui-detail-layout__aside" aria-label="Karar">'
    '<section class="mhmui-widget"><header class="mhmui-widget__header"><h3 class="mhmui-widget__title">Karar</h3></header><div class="mhmui-widget__body">…</div></section>'
    "</aside></div>",
    width=1100,
)

files["components/confirm-button.html"] = page(
    "Components", "ConfirmButton",
    "<b>ConfirmButton</b> — sayfa içi iki adımlı onay (window.confirm yerine). Kapalı/açık × primary/secondary/danger; gövdeli (gerekçe) ve onay kilitli hâl. Hedefler ≥44px.",
    "".join(
        '<div class="%s"><button type="button" class="button mhmui-confirm__trigger">%s</button></div>' % (cls, text)
        for cls, text in (
            ("mhmui-confirm mhmui-confirm--primary", "Bayiyi onayla ve etkinleştir"),
            ("mhmui-confirm mhmui-confirm--secondary", "Askıya al"),
            ("mhmui-confirm mhmui-confirm--danger", "Başvuruyu reddet"),
        )
    )
    + '<div class="mhmui-confirm mhmui-confirm--danger"><div class="mhmui-confirm__prompt">'
    '<p class="mhmui-confirm__text" id="q1">Başvuru reddedilsin mi?</p>'
    '<div class="mhmui-confirm__body"><label for="r1">Ret gerekçesi (zorunlu)</label><textarea id="r1" rows="3"></textarea></div>'
    '<div class="mhmui-confirm__actions"><button type="button" class="button mhmui-confirm__confirm" aria-describedby="q1" aria-disabled="true">Evet, reddet</button>'
    '<button type="button" class="button mhmui-confirm__cancel" aria-describedby="q1">Vazgeç</button></div></div></div>'
    + '<div class="mhmui-confirm mhmui-confirm--primary"><div class="mhmui-confirm__prompt">'
    '<p class="mhmui-confirm__text" id="q2">Bu başvuru onaylansın mı?</p>'
    '<div class="mhmui-confirm__actions"><button type="button" class="button mhmui-confirm__confirm" aria-describedby="q2">Evet, onayla</button>'
    '<button type="button" class="button mhmui-confirm__cancel" aria-describedby="q2">Vazgeç</button></div></div></div>',
    width=620,
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
StatCard · StatsGrid · StatusBadge · Pagination · ProLock · Notice · Widget · Tabs · PageHeader · DetailList · DetailLayout · ConfirmButton
(+ görünmeyenler: ErrorBoundary, createApiClient, useApi, createFormatter)

## Senkron
`DesignSync` ile bileşen bileşen; kaynak `mhm-ui-core` deposu. Bu paket değişince burası
yeniden eşitlenir; aksi hâlde Claude Design bizim olmayan bileşenlerle çizmeye başlar.
""" % version

# ---- Self-check: every class a card SHOWS must have a rule in the page -------
#
# The card is a promise that this is what WordPress renders. A card whose class
# has no rule in the inlined stylesheet renders an unstyled box and still looks
# like a finished card in the Design System pane -- which is exactly what
# happened between 0.8.1 and 0.9.5: the lock rule moved to pro.css, this builder
# inlined admin.css alone, and the pro-lock card lost its styling for five
# releases without a single failure anywhere. Nothing was broken; something
# simply stopped being true, quietly.
#
# Extension points: classes the kit emits for consumers to target, with no rule
# of their own here and none in any consumer today (measured 2026-09-06). They
# are not the lock case: nothing about the component's appearance depends on
# them. .mhmui-pagination__button rides alongside WordPress's own .button, which
# does the styling; .mhmui-widget__actions is a container inside a header that
# already lays its children out. Notice's tone modifiers carry NO color of
# their own by design -- WordPress's native .notice-success/-warning/-error/-info
# does all the coloring (Notice.jsx emits both), so `mhmui-notice--<tone>` is a
# bare semantic hook for consumers, not a styled state. Listed BY NAME so the
# exemption is a decision on the record, not an inference the check quietly makes.
STYLE_HOOKS = {
    "mhmui-pagination__button",
    "mhmui-widget__actions",
    "mhmui-notice--success",
    "mhmui-notice--warning",
    "mhmui-notice--danger",
    "mhmui-notice--info",
}

# Modifiers that intentionally carry NO rule of their own -- not the
# vocabulary drift this check exists to catch (that drift looks like
# `mhmui-stat-card--blue`, a modifier with NO canonical role behind it at
# all), but a deliberate design decision, named here so the exemption is on
# the record. `--up` and `--down` are NOT in this set: both DO have their
# own colour rule in both stylesheets (admin.css:317-318, front.css:126-127)
# and so must be checked like any other class -- exempting a modifier that
# actually has a rule would make this self-check unable to fail for the one
# thing it is named after (measured 2026-09-18: an earlier version of this
# set exempted `--up` on a now-false premise -- "needs no visual difference
# from the plain delta line" -- which predated the commit that gave it a
# colour, so a since-deleted `--up` rule would have stayed invisible here).
# `--flat` (0.13.0) is the real case: it deliberately gets NO colour rule at
# all in either stylesheet (the base .mhmui-stat-card__delta colour already
# reads as neutral, and flat must read as neither good nor bad -- the →
# mark is its whole cue; see the comment beside the --up/--down rules in
# admin.css and front.css).
SHARED_RULE_MODIFIERS = {"mhmui-stat-card__delta--flat"}

missing = []
for rel, content in files.items():
    body = content.split("</head>", 1)[-1]
    styles = content.split("<style>", 1)[-1].split("</style>", 1)[0]
    used = set()
    for attr in re.findall(r'class="([^"]+)"', body):
        for cls in attr.split():
            if cls.startswith("mhmui-") and cls not in STYLE_HOOKS and cls not in SHARED_RULE_MODIFIERS:
                used.add(cls)
    for cls in sorted(used):
        # 🔴 What this proves is narrower than it reads: the class is MENTIONED
        # somewhere in the page's CSS, not that it has declarations of its own.
        # A class that only appears as one member of a grouped or compound
        # selector satisfies it. Measured 2026-09-18: with `--up` removed from
        # SHARED_RULE_MODIFIERS (so it IS checked) its base colour rule was
        # deleted from admin.css and this check still exited 0, because the
        # tone-override selector further down still lists
        # `.mhmui-stat-card__delta--up,` to cancel colour on toned cards. The
        # same hole covers every class here, `.dashicons` included. Tightening
        # it means parsing rule bodies rather than matching selector text --
        # a change to how EVERY class is verified, deliberately not made on a
        # release branch. Until then: a green run means "nothing renders a
        # class the stylesheet has never heard of", and no more than that.
        if not re.search(r"\." + re.escape(cls) + r"\s*[,{]", styles):
            missing.append("%s -> .%s" % (rel, cls))

if missing:
    print("DESIGN SYSTEM: a card shows a class the page has no rule for")
    for row in missing:
        print("  " + row)
    print("Fix the stylesheet or the inlining, not this check.")
    raise SystemExit(1)
print("CHECKED: %d card(s), every mhmui-* base class has a rule" % len(files))

os.makedirs(OUT, exist_ok=True)
for rel, content in files.items():
    path = os.path.join(OUT, rel.replace("/", os.sep))
    os.makedirs(os.path.dirname(path), exist_ok=True)
    io.open(path, "w", encoding="utf-8", newline="\n").write(content)
    print("wrote", rel, len(content), "bytes")
print("BUNDLE", OUT)
