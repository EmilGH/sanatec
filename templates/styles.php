<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/ui/inline.php';

/**
 * The public page's stylesheet, inlined: the dark tokens, the component
 * sections it uses, and a few page-only rules below.
 */
$extra = <<<'CSS'
body{margin:0}
.st-affil{display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px;margin:8px 16px 0;padding:18px 16px;border:1px solid var(--line);border-radius:var(--radius-md);background:var(--panel)}
.st-affil__logo{width:min(75vw,420px);max-width:100%;height:auto;background:#fff;padding:10px 16px;border-radius:10px;box-sizing:border-box}
.st-affil div{display:grid;gap:2px;font-size:15px;line-height:1.4;justify-items:center}.st-affil strong{font-size:18px}.st-affil span{color:var(--muted)}
/* Short pages (a team profile, a passport) must still fill the screen so the CTA bar sits at the bottom. */
.st-root{min-height:100vh;min-height:100dvh;display:flex;flex-direction:column}
.st-root>main{flex:1 0 auto}
/* In a column flex box an auto-margined child shrinks to its content; every band is full width up to its cap. */
.st-root>*{box-sizing:border-box;width:100%}
.st-root>.st-affil{width:calc(100% - 32px)}
.st-row__name{margin-right:auto}
.st-row__title{color:inherit;text-decoration:none}
.st-row__title svg{width:14px;height:14px;margin-left:8px;vertical-align:-1px;color:var(--muted)}
.st-row__title:hover,.st-row__title:hover svg{color:var(--aqua)}
.st-seg__in{position:absolute;opacity:0;pointer-events:none}
.st-seg{display:flex;margin:0 16px 4px;padding:4px;border-radius:var(--radius-pill);background:var(--panel);gap:4px}
.st-seg label{flex:1;min-height:44px;display:grid;place-items:center;border-radius:var(--radius-pill);font:600 15px/1 var(--font-sans);color:var(--muted);cursor:pointer}
#seg-training:checked~.st-seg label[for=seg-training],#seg-adventures:checked~.st-seg label[for=seg-adventures]{background:var(--bg);color:var(--ink)}
#seg-training:checked~#adventures,#seg-adventures:checked~#training{display:none}
.st-skip{position:absolute;left:-9999px;top:0;background:var(--aqua);color:var(--on-aqua);padding:12px 18px;font-weight:600;z-index:20}
.st-skip:focus{left:0}
.st-wrap{max-width:1120px;margin:0 auto}
.st-hero__lede{margin-top:14px;max-width:420px}
.st-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.st-note{margin:14px 0 0;font-size:14px;line-height:20px;color:var(--muted)}
.st-share{padding:16px 16px 0;color:var(--muted);font-size:14px}
.st-share strong{color:var(--ink)}
.st-cols{display:grid;gap:8px}
.st-list{list-style:none;margin:0;padding:0;font-size:15px;line-height:22px;color:var(--muted)}
.st-list li{display:flex;gap:10px;padding:5px 0}
.st-list--in svg{color:var(--ok);width:18px;height:18px;flex:none;margin-top:2px}
.st-list--out span{color:var(--danger);width:18px;flex:none;text-align:center;font-weight:700}
.st-included__h{color:var(--ok);text-transform:uppercase;letter-spacing:.1em;font-size:13px;margin-bottom:8px}
.st-included__h--out{color:var(--danger)}
.st-wm--small{height:24px}
.st-foot p{margin:10px 0 0}
/* The contact table's last rule is the divider before the footer; keep the same breathing room on every page. */
#contact{padding-bottom:32px}
.st-contact a:last-child{border-bottom:0}
.st-contact .st-pill{margin-left:8px;min-height:20px;padding:0 8px;font-size:11px;vertical-align:1px}
.st-foot{border-top:1px solid var(--line);padding-top:24px}
/* Phones and tablets: the wordmark spans the screen, the language switch sits centred beneath it. */
.st-hdr{flex-direction:column;align-items:center;gap:10px;padding:14px 12px 6px;min-height:0}
.st-hdr__brand{width:100%;justify-content:center}
.st-hdr .st-wm,.st-hdr__brand svg.st-wm{width:100%;height:auto;aspect-ratio:486.5/126.8}
.st-foot .st-wm--small{height:24px;width:92px;display:block}
.st-hero__mark{pointer-events:none}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
@container (min-width:900px){
  /* .st-wrap centres each band at 1120px; inside it only a fixed gutter is needed. */
  .st-cols{grid-template-columns:1fr 1fr;gap:56px;padding:0 40px}
  .st-seg{display:none}
  #seg-training:checked~#adventures,#seg-adventures:checked~#training{display:block}
  .st-section{padding:40px 0 8px}
  .st-hdr{flex-direction:row;justify-content:space-between;padding:12px 40px;min-height:76px}
  .st-hdr__brand{width:auto}
  .st-hdr .st-wm,.st-hdr__brand svg.st-wm{height:64px;width:246px;aspect-ratio:auto}
  .st-hero{min-height:420px}
  .st-ctabar{display:none}
  #contact,#included,.st-foot{padding-left:40px;padding-right:40px}
}
CSS;

echo ui_inline_css(['Headers', 'Buttons', 'Badges', 'Menu rows', 'Sticky CTA', 'Public hero'], $extra);
