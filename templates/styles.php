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
.st-hdr .st-wm{height:34px;width:auto}
.st-hero__mark{pointer-events:none}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
@container (min-width:900px){
  .st-cols{grid-template-columns:1fr 1fr;gap:48px;padding:0 max(40px,calc((100% - 1120px)/2))}
  .st-section{padding:40px 0 8px}
  .st-hdr{padding:0 max(40px,calc((100% - 1120px)/2))}
  .st-hero{min-height:420px}
  .st-ctabar{display:none}
  #contact,#included,.st-foot{padding-left:max(40px,calc((100% - 1120px)/2));padding-right:max(40px,calc((100% - 1120px)/2))}
}
CSS;

echo ui_inline_css(['Headers', 'Buttons', 'Badges', 'Menu rows', 'Sticky CTA', 'Public hero'], $extra);
