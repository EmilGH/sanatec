<style>
/* Kept inline deliberately: the whole sheet is a few kilobytes, and one
   request beats two on a phone with one bar of signal at a cenote. */
:root{color-scheme:dark;--bg:#061e27;--panel:#0b2a35;--ink:#f1f8f7;--muted:#a9c2c8;--aqua:#55dce0;--line:#274650}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:24px}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
a{color:inherit;text-underline-offset:5px}
a:focus-visible,button:focus-visible,[tabindex]:focus-visible{outline:3px solid var(--aqua);outline-offset:6px}
a:hover{color:var(--aqua)}
p{color:var(--muted)}

.skip{position:absolute;left:-9999px;top:0;background:var(--aqua);color:#06232b;padding:12px 18px;border-radius:0 0 6px 0;font-weight:600;z-index:20}
.skip:focus{left:0}

.wrap{width:min(1120px,calc(100% - 48px));margin:auto}
header{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:26px 0;border-bottom:1px solid var(--line)}
.brand{text-decoration:none;font-size:23px;font-weight:700;letter-spacing:-1px;white-space:nowrap}
.brand span{color:var(--aqua)}
.headnav{display:flex;align-items:center;gap:28px;font-size:14px}
.headnav nav{display:flex;gap:28px}
.headnav a{text-decoration:none}

.langs{display:flex;gap:8px;align-items:center;border-left:1px solid var(--line);padding-left:20px}
.langs a{padding:4px 8px;border-radius:4px;color:var(--muted)}
.langs a[aria-current="true"]{background:#143c49;color:var(--aqua);font-weight:600}

.hero{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center;padding:76px 0 70px}
.eyebrow{text-transform:uppercase;letter-spacing:2.5px;color:var(--aqua);font-size:12px;font-weight:700;margin:0 0 20px}
h1{font-size:clamp(38px,5.2vw,70px);font-weight:500;line-height:1.04;letter-spacing:-2.5px;margin:0 0 24px}
h1 em{font-family:Georgia,serif;color:var(--aqua);font-weight:400}
.intro{max-width:440px;font-size:18px}
.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
.button{display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:12px 22px;text-decoration:none;border:1px solid var(--line);border-radius:5px;font-weight:600;transition:background .15s}
.button.primary{background:var(--aqua);border-color:var(--aqua);color:#06232b}
.button:hover{background:#164452;color:#fff}
.button.primary:hover{background:#8af1ee;color:#06232b}

.visual{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#020f17}
.brand-image{position:relative;aspect-ratio:738/226;overflow:hidden}
.brand-image img{display:block;width:100%;height:auto;transform:translateY(-8.9%)}
.visual-note{padding:24px 28px;border-top:1px solid var(--line);display:flex;justify-content:space-between;gap:20px;color:var(--muted);font-size:14px}
.visual-note strong{color:var(--aqua);font-weight:500}

.section{padding:52px 0 24px;border-top:1px solid var(--line);margin-bottom:40px}
.section-top{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:26px}
.section-top .eyebrow{margin-bottom:8px}
h2{font-size:clamp(28px,4vw,42px);line-height:1.15;font-weight:500;letter-spacing:-1px;margin:0}
.source{font-size:14px;color:var(--muted);white-space:nowrap}

.table-shell{border:1px solid var(--line);border-radius:10px;overflow:hidden}
table{width:100%;border-collapse:collapse;text-align:left}
caption{padding:16px 22px;text-align:left;background:var(--panel);color:var(--muted);font-size:14px}
thead{background:#103440;color:#a6d6db}
th,td{padding:17px 22px;border-bottom:1px solid var(--line)}
thead th{font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:1px}
tbody th{font-weight:500}
td{color:var(--muted);font-variant-numeric:tabular-nums}
.training td:nth-child(2),.adventures td.price{color:var(--aqua)}
tbody tr:last-child>*{border-bottom:0}
tbody tr:hover{background:#0a2934}
.training th:first-child{width:55%}
.adventures th:first-child{width:32%}
.adventures td:last-child{font-size:14px;max-width:170px}
.note{font-size:14px;margin:16px 0 0}

/* What is included / not included */
.includes{display:grid;grid-template-columns:1fr 1fr;gap:28px;background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:32px 36px}
.includes h3{margin:0 0 14px;font-size:15px;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;color:var(--aqua)}
.includes h3.excluded{color:#e2a49a}
.includes ul{margin:0;padding:0;list-style:none;color:var(--muted);font-size:15px}
.includes li{padding:7px 0 7px 26px;position:relative}
.includes li::before{position:absolute;left:0;top:7px;font-weight:700}
.includes .in li::before{content:"✓";color:var(--aqua)}
.includes .out li::before{content:"×";color:#e2a49a;font-size:18px;line-height:1.35}

/* Where to find the shop */
.location{display:flex;flex-wrap:wrap;gap:36px;align-items:start;padding:30px 0 0;border-top:1px solid var(--line);margin-top:34px}
.location h3{margin:0 0 10px;font-size:15px;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;color:var(--aqua)}
.location address{font-style:normal;color:var(--muted);font-size:15px}

.contact{display:flex;align-items:center;justify-content:space-between;gap:36px;background:var(--panel);border:1px solid var(--line);padding:40px;border-radius:12px;margin:12px 0 56px}
.contact p{margin-bottom:0}
.contact .actions{margin-top:0;flex-shrink:0}
.contact a[href^="tel:"]{color:var(--aqua);text-decoration:underline}

footer{border-top:1px solid var(--line);padding:24px 0 32px;display:flex;justify-content:space-between;gap:20px;color:var(--muted);font-size:14px}
.mobile-cta{display:none}
.scroll-hint{display:none}

@media(max-width:860px){
  .includes{grid-template-columns:1fr;gap:22px;padding:26px}
}
@media(max-width:760px){
  .wrap{width:calc(100% - 32px)}
  header{padding:18px 0;gap:12px}
  .brand{font-size:19px}
  .headnav{gap:14px;font-size:13px}
  .headnav nav{gap:14px}
  .langs{padding-left:12px;gap:4px}
  .hero{grid-template-columns:1fr;gap:32px;padding:42px 0}
  .hero h1{max-width:550px;letter-spacing:-1.5px}
  .intro{max-width:none}
  .visual{max-width:560px}
  .section{padding-top:32px;margin-bottom:28px}
  .section-top{align-items:start;flex-direction:column;gap:14px}
  th,td{padding:14px 12px}
  .training th:first-child{width:47%}
  .training td{font-size:14px}
  .adventures{min-width:660px}
  .adventure-scroll{overflow-x:auto}
  .contact{padding:26px;flex-direction:column;align-items:start;gap:24px;margin-bottom:32px}
  .contact .actions{width:100%}
  .location{gap:22px}
  footer{flex-direction:column;padding-bottom:100px}
  .mobile-cta{display:flex;position:fixed;bottom:0;left:0;right:0;gap:10px;padding:12px 16px calc(12px + env(safe-area-inset-bottom));background:#061e27;border-top:1px solid var(--line);z-index:10}
  .mobile-cta .button{flex:1;padding:10px 16px}
  .visual-note{padding:18px 20px;flex-direction:column;gap:3px}
  .scroll-hint{display:block}
}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
</style>
