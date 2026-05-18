<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/cw-asset-resolve.php';
require_once __DIR__ . '/includes/cw-sanitize-html.php';

/**
 * URL path prefix for this install (e.g. "/samsung-clon" locally, "" at domain docroot).
 */
function cw_install_base_path(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }

    $override = getenv('CW_BASE_PATH');
    if (is_string($override)) {
        if ($override === '' || $override === '/') {
            $path = '';
        } else {
            $path = str_starts_with($override, '/') ? $override : '/' . $override;
        }
        return $path;
    }

    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot !== '') {
        $root = realpath($docRoot);
        $here = realpath(__DIR__);
        if ($root !== false && $here !== false && $root === $here) {
            $path = '';
            return $path;
        }
    }

    $folder = basename(__DIR__);
    if (in_array($folder, ['public_html', 'www', 'httpdocs', 'htdocs', 'html', 'web'], true)) {
        $path = '';
        return $path;
    }

    $path = '/' . $folder;
    return $path;
}

/** Strip install prefix and legacy /public_html from a request path. */
function cw_normalize_request_path(string $path): string
{
    $base = cw_install_base_path();
    if ($base !== '') {
        if ($path === $base) {
            $path = '/';
        } elseif (str_starts_with($path, $base . '/')) {
            $path = substr($path, strlen($base));
        }
    }

    if ($path === '/public_html') {
        $path = '/';
    } elseif (str_starts_with($path, '/public_html/')) {
        $path = substr($path, strlen('/public_html'));
        if ($path === '') {
            $path = '/';
        }
    }

    return $path;
}

$baseUrl = 'http://localhost/samsung-clon';
if (!defined('CW_BASE_URL')) {
    $override = getenv('CW_BASE_URL');
    if (is_string($override) && $override !== '') {
        $base = $override;
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string)$_SERVER['HTTP_HOST'];
        $base = $scheme . '://' . $host . cw_install_base_path();
    } else {
        $base = $baseUrl;
    }

    define('CW_BASE_URL', rtrim($base, '/'));
}

function cw_inject_after_head_open(string $html, string $insertion): string
{
    $pos = stripos($html, '<head');
    if ($pos === false) {
        return $html;
    }

    $gt = strpos($html, '>', $pos);
    if ($gt === false) {
        return $html;
    }

    return substr($html, 0, $gt + 1) . $insertion . substr($html, $gt + 1);
}


function cw_rewrite_asset_urls_in_html(string $html): string
{
    $base = CW_BASE_URL;
    $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestPath, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }
    $path = cw_normalize_request_path($path);
    if (str_starts_with($path, '/pk/')) {
        $path = substr($path, 3);
    } elseif ($path === '/pk' || $path === '/pk/') {
        $path = '/';
    }

    $headInsert = '';
    if (!str_contains($html, 'assets/css/custom.css')) {
        $headInsert .= '<link rel="stylesheet" href="' . $base . '/assets/css/custom.css">';
    }
    if (str_contains($html, 'id="pathString"')) {
        $pathString = $path;
        if ($pathString === '/') {
            $pathString = '/pk/';
        } else {
            if (!str_starts_with($pathString, '/pk/')) {
                $pathString = '/pk' . $pathString;
            }
        }
        if ($pathString !== '/' && !str_ends_with($pathString, '/')) {
            $pathString .= '/';
        }
        $headInsert .= '<script id="cw-set-pathstring">(function(){
            var targetPath = ' . json_encode($pathString) . ';
            var targetPageUrl = ' . json_encode('https://www.samsung.com' . $pathString) . ';
            function setValues(){
                try{
                    var ps = document.getElementById("pathString");
                    if(ps && ps.value !== targetPath) ps.value = targetPath;
                    var pu = document.getElementById("pageUrl");
                    if(pu && pu.value !== targetPageUrl) pu.value = targetPageUrl;
                }catch(e){}
            }
            setValues();
            if(document.readyState === "loading"){
                document.addEventListener("DOMContentLoaded", setValues);
            }
            window.addEventListener("load", setValues);
            setInterval(setValues, 100);
        })();</script>';
    }
    if (!str_contains($html, 'id="cw-asset-root"')) {
        $headInsert .= '<script id="cw-asset-root">window.__CW_ASSET_ROOT=' . json_encode($base) . ';</script>';
    }
    if (!str_contains($html, 'id="cw-stubs"')) {
        $headInsert .= '<script id="cw-stubs">(function(){try{' .
            'var R=window.__CW_ASSET_ROOT||"";' .
            'function blocked(u){if(typeof u!=="string")return false;var x=u.toLowerCase();return/pageinfo$|front\\/b2c\\//i.test(u)||x.indexOf("facebook")!==-1||x.indexOf("googletagmanager")!==-1||x.indexOf("useinsider")!==-1||x.indexOf("sprinklr")!==-1||x.indexOf("livechat")!==-1||x.indexOf("media-tagging")!==-1||x.indexOf("smetrics")!==-1||x.indexOf("adobedtm")!==-1||x.indexOf("go-mpulse")!==-1||x.indexOf("decibelinsight")!==-1||x.indexOf("kampyle")!==-1||x.indexOf("beusable")!==-1||x.indexOf("api-recommender")!==-1;}' .
            'function fixRoot(u){if(typeof u!=="string")return u;if(blocked(u))return"about:blank";if(/^\\/(etc\\.clientlibs|assets|is\\/|content\\/|aemapi)\\//.test(u))return R+u;if(u.indexOf("http://localhost/etc.clientlibs/")===0)return u.replace("http://localhost/etc.clientlibs/",R+"/etc.clientlibs/");if(u.indexOf("http://localhost/assets/")===0)return u.replace("http://localhost/assets/",R+"/assets/");if(u.indexOf("http://localhost/is/")===0)return u.replace("http://localhost/is/",R+"/is/");if(u.indexOf("http://localhost/content/")===0)return u.replace("http://localhost/content/",R+"/content/");return u;}' .
            'function patchSetter(proto,prop){var d=Object.getOwnPropertyDescriptor(proto,prop);if(!d||!d.set)return;var s=d.set;d.set=function(v){if(prop==="src"&&blocked(v))return;return s.call(this,fixRoot(v));};}' .
            'patchSetter(HTMLImageElement.prototype,"src");patchSetter(HTMLLinkElement.prototype,"href");patchSetter(HTMLScriptElement.prototype,"src");' .
            'var _open=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(m,u){if(blocked(u))u="about:blank";else arguments[1]=fixRoot(u);return _open.apply(this,arguments);};' .
            'var emptyJson=function(){return Promise.resolve({ok:true,status:200,json:function(){return Promise.resolve({});},text:function(){return Promise.resolve("{}");}});};' .
            'var _fetch=window.fetch;window.fetch=function(i,n){var u=typeof i==="string"?i:(i&&i.url?i.url:"");if(blocked(u))return emptyJson();if(typeof i==="string"){i=fixRoot(i);}else if(i&&i.url){try{i=new Request(fixRoot(i.url),i);}catch(e){}}return _fetch.call(this,i,n);};' .
            'window._satellite=window._satellite||{};var _rs=function(cb){try{if(typeof cb==="function")cb({},{},window.Promise||{resolve:function(){}});}catch(e){}};for(var i=1;i<=20;i++){(function(n){window._satellite["_runScript"+n]=_rs;})(i);}' .
            'window._satellite.getVar=window._satellite.getVar||function(){return "";};window._satellite.setVar=window._satellite.setVar||function(){};window._satellite.track=window._satellite.track||function(){};window._satellite.pageBottom=function(){};window._satellite.pageTop=function(){};' .
            'window.fbq=window.fbq||function(){};window.fbq.queue=window.fbq.queue||[];window.fbq.loaded=true;window._fbq=window._fbq||window.fbq;' .
            'window.__beusablerumclient__=window.__beusablerumclient__||{load:function(){}};' .
            'window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};' .
            'window.tagLayer=window.tagLayer||[];window.cj=window.cj||{};window.MODAL_DIALOGS=window.MODAL_DIALOGS||{};window.KAMPYLE_ONSITE_SDK=window.KAMPYLE_ONSITE_SDK||{};' .
            'window.KAMPYLE_UTILS=window.KAMPYLE_UTILS||{setNestedPropertyValue:function(){}};window.KAMPYLE_DATA=window.KAMPYLE_DATA||{};window.KAMPYLE_FUNC=window.KAMPYLE_FUNC||{};window.MDIGITAL=window.MDIGITAL||{};' .
            'window.eddlDataLayer=window.eddlDataLayer||[];window.digitalData=window.digitalData||{};window.siteCode=window.siteCode||"";window.BOOMR=window.BOOMR||{page_ready:function(){}};' .
            '}catch(e){}})();</script>';
    }
    if (!str_contains($html, 'id="cw-hydrate-media"')) {
        $headInsert .= '<script id="cw-hydrate-media">(function(){function h(){try{' .
            'document.querySelectorAll("img[data-src],img[data-desktop-src],img[data-mobile-src],video[data-src],source[data-src]").forEach(function(el){' .
            '["data-src","data-desktop-src","data-mobile-src"].forEach(function(a){var v=el.getAttribute(a);if(v&&!el.getAttribute("src")){el.setAttribute("src",v);}});' .
            'if(el.tagName==="VIDEO"&&el.getAttribute("data-src")&&!el.getAttribute("src")){el.setAttribute("src",el.getAttribute("data-src"));}' .
            '});' .
            '}catch(e){}}' .
            'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",h);}else{h();}' .
            'setTimeout(h,400);setTimeout(h,1200);})();</script>';
    }
    if (!str_contains($html, 'id="cw-hide-cookie"')) {
        $headInsert .= '<style id="cw-hide-cookie">' .
            '.cookie-bar,.cookie-bar *{display:none!important;}' .
            '#onetrust-consent-sdk,.ot-sdk-container,.ot-sdk-row,#ot-sdk-btn,.ot-floating-button{display:none!important;}' .
            '.truste-overlay,.truste-box-overlay,.truste-consent-track,.truste-popup-container{display:none!important;}' .
            '</style>' .
            '<script id="cw-hide-cookie-js">(function(){function r(){try{' .
            'var s=[".cookie-bar","#onetrust-consent-sdk",".ot-sdk-container",".truste-overlay",".truste-box-overlay",".truste-popup-container"];' .
            'for(var i=0;i<s.length;i++){var n=document.querySelectorAll(s[i]);for(var j=0;j<n.length;j++){n[j].remove();}}' .
            '}catch(e){}}' .
            'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",r);}else{r();}' .
            'setTimeout(r,250);setTimeout(r,1000);})();</script>';
    }
    if (!str_contains($html, 'id="cw-force-lazy"')) {
        $headInsert .= '<script id="cw-force-lazy">(function(){function f(){try{' .
            'var l=window.sg&&window.sg.common&&window.sg.common.lazyLoad;' .
            'if(!l||!document.body||typeof l.forceLoad!=="function")return;' .
            'l.forceLoad(document.body);' .
            '}catch(e){}}' .
            'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",f);}else{f();}' .
            'setTimeout(f,500);setTimeout(f,1500);})();</script>';
    }

    if (!str_contains($html, 'id="cw-fix-pk"')) {
        $headInsert .= '<script id="cw-fix-pk">(function(){var b=' . json_encode($base) . ';function u(v){if(!v||typeof v!=="string")return v;var t=v.trim();if(t==="/pk"||t==="/pk/")return b+"/";if(t.slice(0,4)==="/pk/")return b+"/"+t.slice(4);if(t==="pk"||t==="pk/")return b+"/";if(t.slice(0,3)==="pk/")return b+"/"+t.slice(3);var m=t.match(/^(https?:)?\\/\\/(?:[a-z0-9-]+\\.)*samsung\\.com\\/pk(?:\\/|$)(.*)$/i);if(m)return b+"/"+(m[2]||"");return v;}function f(e){if(!e||!e.getAttribute)return;var a=["href","src","action"];for(var i=0;i<a.length;i++){var k=a[i];var v=e.getAttribute(k);if(!v)continue;var nv=u(v);if(nv!==v)e.setAttribute(k,nv);}}function s(){try{var n=document.querySelectorAll("[href],[src],[action]");for(var i=0;i<n.length;i++)f(n[i]);}catch(e){}}if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",s);}else{s();}setTimeout(s,500);setTimeout(s,1500);try{var o=new MutationObserver(function(ms){for(var i=0;i<ms.length;i++){var m=ms[i];if(m.type==="attributes"){f(m.target);continue;}var an=m.addedNodes;if(!an)continue;for(var j=0;j<an.length;j++){var nd=an[j];if(!nd)continue;f(nd);if(nd.querySelectorAll){var q=nd.querySelectorAll("[href],[src],[action]");for(var k=0;k<q.length;k++)f(q[k]);}}}});o.observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:["href","src","action"]});}catch(e){}})();</script>';
    }
    if ($headInsert !== '') {
        $html = cw_inject_after_head_open($html, $headInsert);
    }

    $html = preg_replace(
        '~<script\b[^>]*\bsrc=(["\'])(?://|https?://)?maps\.googleapis\.com[^>]*>\s*</script>~is',
        '',
        $html
    ) ?? $html;

    if (str_contains($html, $base)) {
        $html = str_replace($base, $base, $html);
    }
    $html = preg_replace(
        '~\b(href|src|action)=(["\'])(?:https?:)?//www\.samsung\.com/pk/?([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b(href|action)=(["\'])//www\.samsung\.com/pk/?([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b(href|src|action)=(["\'])(?:https?:)?//(?:[a-z0-9-]+\.)*samsung\.com/pk/?([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b(href|src|action)=(["\'])/pk/?([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b(href|src|action)=(["\'])pk/?([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~(["\'])(?:https?:)?//(?:[a-z0-9-]+\.)*samsung\.com/pk/?~i',
        '$1' . $base . '/',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~(["\'])/pk(?=/|["\'\s?#])~i',
        '$1' . $base,
        $html
    ) ?? $html;
    $html = preg_replace(
        '~(["\'])pk(?=/|["\'\s?#])~i',
        '$1' . $base,
        $html
    ) ?? $html;

    $html = preg_replace('~(["\'(])/(etc\.clientlibs|aemapi|assets|iam|is|content)/~', '$1' . $base . '/$2/', $html) ?? $html;
    $html = preg_replace('~(["\'(])(?:\.\./)+(etc\.clientlibs|aemapi|assets|iam|is|content)/~', '$1' . $base . '/$2/', $html) ?? $html;

    $html = preg_replace_callback(
        '~(["\'])(/content/samsung/assets/[^"\']+)~',
        static function (array $m) use ($base): string {
            $url = preg_replace('/(&quot;|&#0?38;).*$/', '', $m[2]) ?? $m[2];
            $resolved = cw_resolve_public_asset_path($url);
            if ($resolved === null) {
                return $m[0];
            }
            return $m[1] . $base . $resolved . $m[1];
        },
        $html
    ) ?? $html;

    $html = str_replace($base . '/image/', $base . '/is/image/', $html);
    $html = preg_replace('~(["\'])(?:https?:)?//searchapi\.samsung\.com/v6/~i', '$1' . $base . '/v6/', $html);

    $html = preg_replace_callback(
        '~(https?:)?//([a-z0-9.-]+)(/[^\s"\'<>]+)~i',
        static function (array $m) use ($base): string {
            $scheme = $m[1] ?: 'https:';
            $host = strtolower($m[2] ?? '');
            $rest = $m[3] ?? '';

            if ($host === '' || $host === 'localhost' || str_starts_with($host, 'localhost:')) {
                return $m[0];
            }

            $suffix = '';
            while ($rest !== '') {
                $last = substr($rest, -1);
                if ($last === ')' || $last === ']' || $last === '}' || $last === ';' || $last === ',') {
                    $suffix = $last . $suffix;
                    $rest = substr($rest, 0, -1);
                    continue;
                }
                break;
            }

            $url = $scheme . '//' . $host . $rest;
            $url = str_replace(['&amp;', '&#038;'], '&', $url);

            $path = parse_url($url, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return $m[0];
            }

            $query = parse_url($url, PHP_URL_QUERY);
            $fragment = parse_url($url, PHP_URL_FRAGMENT);
            $needsHash = (is_string($query) && $query !== '') || (is_string($fragment) && $fragment !== '');

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $allowed = [
                'js', 'mjs', 'css',
                'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico',
                'woff', 'woff2', 'ttf', 'otf', 'eot',
                'mp4', 'webm', 'm4v', 'mov',
                'mp3', 'wav', 'm4a', 'aac',
                'pdf', 'json',
            ];
            if ($ext === '') {
                $dir = trim(dirname($path), '/');
                $baseName = basename($path);
                if ($baseName === '') {
                    $baseName = 'index';
                }
                if ($needsHash) {
                    $baseName .= '-' . substr(sha1($url), 0, 10);
                }

                $dirPrefix = $dir !== '' ? ($dir . '/') : '';
                $localDirFs = __DIR__ . '/assets/remote/' . $host . '/' . $dirPrefix;
                if (!is_dir($localDirFs)) {
                    return $m[0];
                }
                $entries = scandir($localDirFs);
                if (!is_array($entries)) {
                    return $m[0];
                }
                $prefix = $baseName . '.';
                $candidates = [];
                foreach ($entries as $entry) {
                    if (!is_string($entry) || $entry === '.' || $entry === '..') {
                        continue;
                    }
                    if (str_starts_with($entry, $prefix)) {
                        $candidates[] = $entry;
                    }
                }
                if (!$candidates) {
                    return $m[0];
                }
                sort($candidates, SORT_STRING);
                $picked = $candidates[0];
                $localRel = 'assets/remote/' . $host . '/' . $dirPrefix . $picked;
                return $base . '/' . $localRel . $suffix;
            }
            if (!in_array($ext, $allowed, true)) {
                return $m[0];
            }

            $filename = basename($path);
            if ($needsHash) {
                $dot = strrpos($filename, '.');
                $stem = $dot === false ? $filename : substr($filename, 0, $dot);
                $extWithDot = $dot === false ? '' : substr($filename, $dot);
                $filename = $stem . '-' . substr(sha1($url), 0, 10) . $extWithDot;
            }

            $dir = trim(dirname($path), '/');
            $localRel = 'assets/remote/' . $host . '/' . ($dir !== '' ? ($dir . '/') : '') . $filename;
            $localFs = __DIR__ . '/' . $localRel;
            if (!is_file($localFs)) {
                return $m[0];
            }

            return $base . '/' . $localRel . $suffix;
        },
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b(href|src|action)=(["\'])/(?!/)(?!etc\.clientlibs/|aemapi/|assets/|iam/|is/|content/)([^"\']*)\2~i',
        '$1=$2' . $base . '/$3$2',
        $html
    ) ?? $html;

    $html = preg_replace(
        '~\b((?:window\.)?location)\.href\s*=\s*(["\'])/(?!/)([^"\']*)\2~i',
        '$1.href=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b((?:window\.)?location)\.href\s*=\s*(["\'])/pk/?([^"\']*)\2~i',
        '$1.href=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b((?:window\.)?location)\.href\s*=\s*(["\'])pk/([^"\']*)\2~i',
        '$1.href=$2' . $base . '/$3$2',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b((?:window\.)?location)\.(assign|replace)\(\s*(["\'])/(?!/)([^"\']*)\3\s*\)~i',
        '$1.$2($3' . $base . '/$4$3)',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b((?:window\.)?location)\.(assign|replace)\(\s*(["\'])/pk/?([^"\']*)\3\s*\)~i',
        '$1.$2($3' . $base . '/$4$3)',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~\b((?:window\.)?location)\.(assign|replace)\(\s*(["\'])pk/([^"\']*)\3\s*\)~i',
        '$1.$2($3' . $base . '/$4$3)',
        $html
    ) ?? $html;

    $quotedBase = preg_quote($base, '~');
    $html = preg_replace('~' . $quotedBase . '/pk(?=/|\?|#|["\'\s])~', $base, $html) ?? $html;

    $html = cw_sanitize_html($html);

    $html = preg_replace(
        '~</body>~i',
        '
    <script>
        (function() {
            var savedProductHTML = "";
            var contentWrap = null;
            var noResultEl = null;
            
            function saveProductList() {
                if (!contentWrap) {
                    contentWrap = document.querySelector(".js-pfv2-content-wrap");
                }
                if (contentWrap && contentWrap.innerHTML.trim().length > 0) {
                    savedProductHTML = contentWrap.innerHTML;
                }
            }
            
            function lockProductList() {
                if (!contentWrap) {
                    contentWrap = document.querySelector(".js-pfv2-content-wrap");
                }
                if (!noResultEl) {
                    noResultEl = document.querySelector(".pd21-product-finder__no-result");
                }
                
                if (noResultEl) {
                    noResultEl.style.display = "none";
                    noResultEl.setAttribute("hidden", "true");
                    noResultEl.remove();
                }
                
                if (contentWrap && savedProductHTML) {
                    contentWrap.innerHTML = savedProductHTML;
                }
            }
            
            setTimeout(saveProductList, 300);
            setTimeout(saveProductList, 800);
            setTimeout(saveProductList, 1500);
            setTimeout(saveProductList, 2500);
            
            setTimeout(lockProductList, 3000);
            setTimeout(lockProductList, 3500);
            setTimeout(lockProductList, 4000);
            setTimeout(lockProductList, 4500);
            setTimeout(lockProductList, 5000);
        })();
    </script>
</body>',
        $html
    ) ?? $html;

    return $html;
}

function cw_start_asset_url_rewrite(): void
{
    static $started = false;
    if ($started) {
        return;
    }
    $started = true;

    ob_start('cw_rewrite_asset_urls_in_html');
    register_shutdown_function(static function (): void {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
    });
}
