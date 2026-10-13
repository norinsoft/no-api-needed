<?php
/**
 * ⚠️ SECURITY WARNING
 * This file performs server-side HTTP requests based on user input (SSRF).
 * Before deploying publicly, restrict outbound requests to a whitelist,
 * and block private IP ranges (127.0.0.0/8, 10.0.0.0/8, 169.254.0.0/16, ...).
 */

$url = $_GET['url'] ?? '';
if (!filter_var($url, FILTER_VALIDATE_URL)) { http_response_code(400); exit('bad url'); }

require __DIR__ . '/http_client.php';
$res = fetchHtml($url);
$html = $res['html'];
$finalUrl = $res['final_url'];
$contentType = $res['content_type'];

if ($html === false) { http_response_code(502); exit('fetch failed'); }

// غیر HTML رو مستقیم رد کن
if (stripos($contentType, 'text/html') === false) {
  header('Content-Type: ' . $contentType);
  echo $html; exit;
}

// حذف meta CSP که ممکنه اسکریپت ما رو بلاک کنه
$html = preg_replace('/<meta[^>]+http-equiv=["\']?Content-Security-Policy["\']?[^>]*>/i', '', $html);

// تزریق base برای اینکه لینک‌های نسبی به دامنه اصلی اشاره کنن
$baseTag = '<base href="' . htmlspecialchars($finalUrl, ENT_QUOTES) . '">';
if (preg_match('/<head([^>]*)>/i', $html)) {
  $html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $baseTag, $html, 1);
} else {
  $html = $baseTag . $html;
}

// اسکریپت picker رو inline می‌کنیم تا base URL روش اثر نذاره
$initialMode = ($_GET['mode'] ?? 'pick') === 'browse' ? 'browse' : 'pick';

$picker = <<<'JS'
<script>
(function(){
  const ORIGINAL_URL = window.__ORIGINAL_URL || location.href;
  let MODE = window.__INITIAL_MODE || 'pick';

  function cssPath(el){
    if(!(el instanceof Element)) return null;
    const path = [];
    while(el && el.nodeType === 1 && el !== document.documentElement){
      if(el.id){ path.unshift('#' + CSS.escape(el.id)); break; }
      let sel = el.nodeName.toLowerCase();
      const sibs = el.parentNode ? [...el.parentNode.children].filter(c => c.nodeName === el.nodeName) : [];
      if(sibs.length > 1) sel += ':nth-of-type(' + (sibs.indexOf(el) + 1) + ')';
      path.unshift(sel);
      el = el.parentNode;
    }
    return path.join(' > ');
  }

  // highlight box
  const hl = document.createElement('div');
  Object.assign(hl.style, {
    position:'fixed', pointerEvents:'none', border:'2px solid #e11',
    background:'rgba(225,17,17,0.12)', zIndex:2147483647,
    borderRadius:'2px', display:'none', transition:'all .05s'
  });
  function ensureHl(){ if (document.body && !hl.parentNode) document.body.appendChild(hl); }
  document.addEventListener('DOMContentLoaded', ensureHl);
  ensureHl();

  // پیام از والد
  window.addEventListener('message', e => {
    if (e.data?.type === 'set-mode') {
      MODE = e.data.mode;
      hl.style.display = 'none';
      document.documentElement.style.cursor = MODE === 'pick' ? 'crosshair' : '';
    }
  });

  // اعمال cursor اولیه
  document.documentElement.style.cursor = MODE === 'pick' ? 'crosshair' : '';

  // hover
  document.addEventListener('mouseover', e => {
    if (MODE !== 'pick') return;
    const r = e.target.getBoundingClientRect();
    Object.assign(hl.style, {
      display:'block', top:r.top+'px', left:r.left+'px',
      width:r.width+'px', height:r.height+'px'
    });
  }, true);

  document.addEventListener('mouseout', () => {
    if (MODE === 'pick') hl.style.display='none';
  }, true);

  // decode DuckDuckGo redirect
  function decodeDDG(href){
    try {
      const u = new URL(href, location.href);
      if (u.hostname.endsWith('duckduckgo.com')) {
        const uddg = u.searchParams.get('uddg');
        if (uddg) return uddg;
      }
    } catch(e){}
    return href;
  }

  // کلیک
  document.addEventListener('click', e => {
    if (MODE === 'pick') {
      e.preventDefault(); e.stopPropagation();
      const el = e.target;
      const selector = cssPath(el);
      const text = (el.innerText || el.textContent || '').trim().slice(0,200);

      fetch(window.__SAVE_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          url: ORIGINAL_URL,
          selector,
          sample: text,
          tag: el.tagName,
          el_id: el.id || ''
        })
      })
      .then(r => r.json())
      .then(res => parent.postMessage({type:'selector-saved', data:res}, '*'))
      .catch(err => console.error('save failed:', err));

      return false;
    } else {
      // حالت مرور: لینک‌ها رو رهگیری کن
      const a = e.target.closest && e.target.closest('a[href]');
      if (!a) return;
      e.preventDefault(); e.stopPropagation();
      let href = a.href; // با base حل می‌شه
      href = decodeDDG(href);
      if (!/^https?:/i.test(href)) return;
      parent.postMessage({type:'navigate', url: href}, '*');
      return false;
    }
  }, true);
})();
</script>
JS;

$picker = '<script>window.__ORIGINAL_URL=' . json_encode($finalUrl) . ';</script>'
        . '<script>window.__INITIAL_MODE=' . json_encode($initialMode) . ';</script>'
        . $picker;
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$dir     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$saveUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/save.php';

$picker = '<script>window.__SAVE_URL=' . json_encode($saveUrl) . ';</script>' . $picker;
if (preg_match('/<\/body>/i', $html)) {
  $html = preg_replace('/<\/body>/i', $picker . '</body>', $html, 1);
} else {
  $html .= $picker;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;