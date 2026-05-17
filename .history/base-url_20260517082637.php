<?php
declare(strict_types=1);
$baseUrl = 'http://localhost/samsung-clon';
if (!defined('CW_BASE_URL')) {
    $override = getenv('CW_BASE_URL');
    if (is_string($override) && $override !== '') {
        $base = $override;
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $isHttps ? 'https' : 'http';
        $host = (string)$_SERVER['HTTP_HOST'];
        $basePath = '/' . basename(__DIR__);
        $base = $scheme . '://' . $host . $basePath;
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
    $baseDir = '/' . basename(__DIR__);
    if (str_starts_with($path, $baseDir . '/')) {
        $path = substr($path, strlen($baseDir));
    } elseif ($path === $baseDir) {
        $path = '/';
    }
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
    if (!str_contains($html, 'id="cw-stubs"')) {
        $headInsert .= '<script id="cw-stubs">(function(){try{' .
            'window.google=window.google||{};' .
            'window.google.maps=window.google.maps||{};' .
            'window.google.maps.places=window.google.maps.places||{};' .
            'window.google.maps.places.Autocomplete=function(){};' .
            'window.google.maps.places.Autocomplete.prototype.addListener=function(){};' .
            'window.google.maps.places.Autocomplete.prototype.setComponentRestrictions=function(){};' .
            'window.google.maps.places.Autocomplete.prototype.setBounds=function(){};' .
            'window.google.maps.places.Autocomplete.prototype.setFields=function(){};' .
            'window.google.maps.places.Autocomplete.prototype.getPlace=function(){return {};};' .
            'window.google.maps.Map=function(){};' .
            'window.google.maps.Map.prototype.addListener=function(){};' .
            'window.google.maps.Map.prototype.setCenter=function(){};' .
            'window.google.maps.Map.prototype.setZoom=function(){};' .
            'window.google.maps.Map.prototype.panTo=function(){};' .
            'window.google.maps.Marker=function(){};' .
            'window.google.maps.Marker.prototype.addListener=function(){};' .
            'window.google.maps.Marker.prototype.setMap=function(){};' .
            'window.google.maps.Marker.prototype.setPosition=function(){};' .
            'window.google.maps.InfoWindow=function(){};' .
            'window.google.maps.InfoWindow.prototype.addListener=function(){};' .
            'window.google.maps.InfoWindow.prototype.open=function(){};' .
            'window.google.maps.InfoWindow.prototype.setContent=function(){};' .
            'window.google.maps.Geocoder=function(){};' .
            'window.google.maps.Geocoder.prototype.geocode=function(request, callback){callback([], "OK");};' .
            'window.google.maps.LatLng=function(){};' .
            'window.google.maps.LatLngBounds=function(){};' .
            'window.google.maps.LatLngBounds.prototype.extend=function(){};' .
            'window.google.maps.LatLngBounds.prototype.contains=function(){return false;};' .
            'window.google.maps.Size=function(){};' .
            'window.google.maps.Point=function(){};' .
            'window.google.maps.MarkerImage=function(){};' .
            'window.google.maps.DirectionsService=function(){};' .
            'window.google.maps.DirectionsService.prototype.route=function(request, callback){callback({}, "OK");};' .
            'window.google.maps.DirectionsRenderer=function(){};' .
            'window.google.maps.DirectionsRenderer.prototype.setMap=function(){};' .
            'window.google.maps.DirectionsRenderer.prototype.setDirections=function(){};' .
            'window._satellite=window._satellite||{};' .
            'window._satellite.getVar=window._satellite.getVar||function(){return "";};' .
            'window._satellite.setVar=window._satellite.setVar||function(){};' .
            'window._satellite.track=window._satellite.track||function(){};' .
            'window._satellite.__registerScript=function(){};' .
            'window._satellite.pageBottom=function(){};' .
            'window._satellite.pageTop=function(){};' .
            'for(var i=0;i<=200;i++){var k="_runScript"+i;window._satellite[k]=window._satellite[k]||function(cb){try{if(typeof cb==="function"){cb({},null,Promise);}}catch(e){}};}' .
            'window.dataLayer=window.dataLayer||[];' .
            'window.gtag=window.gtag||function(){window.dataLayer.push(arguments);};' .
            'window.poc_gtag=window.poc_gtag||function(){};' .
            'window.fbq=window.fbq||function(){(window.fbq.q=window.fbq.q||[]).push(arguments);};' .
            'window._fbq=window._fbq||window.fbq;' .
            'window.__beusablerumclient__=window.__beusablerumclient__||{};' .
            'window.KAMPYLE_ONSITE_SDK=window.KAMPYLE_ONSITE_SDK||{};' .
            'window.MODAL_DIALOGS=window.MODAL_DIALOGS||{};' .
            'window.eddlDataLayer=window.eddlDataLayer||[];' .
            'window.digitalData=window.digitalData||{};' .
            'window.eddl=window.eddl||{};' .
            'window.eddl.push=function(){};' .
            'window.siteCode=window.siteCode||"";' .
            'function isBlockedUrl(url){if(typeof url!=="string")return false;var lc=url.toLowerCase();return lc.indexOf("samsung.com/chat")!==-1||lc.indexOf("/chat/api")!==-1;}' .
            'var _XHR=window.XMLHttpRequest;window.XMLHttpRequest=function(){var x=new _XHR();var _open=x.open;x.open=function(){var url=arguments[1];if(isBlockedUrl(url)){return;}return _open.apply(x,arguments);};return x;};' .
            'var _fetch=window.fetch;window.fetch=function(){var url=arguments[0];if(typeof url==="string"&&isBlockedUrl(url)){return new Promise(function(resolve){resolve({ok:true,json:function(){return new Promise(function(r){r({});});},text:function(){return new Promise(function(r){r("");});}});});}else if(url&&url.url&&isBlockedUrl(url.url)){return new Promise(function(resolve){resolve({ok:true,json:function(){return new Promise(function(r){r({});});},text:function(){return new Promise(function(r){r("");});}});});}return _fetch.apply(window,arguments);};' .
            '}catch(e){}})();</script>';
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

    if (str_contains($html, $base)) {
        $html = str_replace($base, $base, $html);
    }
    $html = preg_replace(
        '~\b(href|src|action)=(["\'])(?:https?:)?//www\.samsung\.com/pk/?([^"\']*)\2~i',
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

    $html = preg_replace('~(["\'(])/(etc\.clientlibs|aemapi|assets|iam|is)/~', '$1' . $base . '/$2/', $html) ?? $html;
    $html = preg_replace('~(["\'(])(?:\.\./)+(etc\.clientlibs|aemapi|assets|iam|is)/~', '$1' . $base . '/$2/', $html) ?? $html;

    $html = str_replace($base . '/image/', $base . '/is/image/', $html);
    $html = preg_replace('~(["\'])(?:https?:)?//searchapi\.samsung\.com/v6/~i', '$1' . $base . '/v6/', $html);

    $html = preg_replace(
        '~<script[^>]*fbevents\.js[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~<script[^>]*connect\.facebook\.net[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~<script[^>]*fb\.facebook\.net[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~<noscript[^>]*>.*?facebook.*?</noscript>~is',
        '',
        $html
    ) ?? $html;
    
    $html = preg_replace(
        '~<script[^>]*live-chat[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~<script[^>]*samsung\.com/chat[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    $html = preg_replace(
        '~<script[^>]*sdk\.prd\.js[^>]*>.*?</script>~is',
        '',
        $html
    ) ?? $html;
    
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
        '~\b(href|src|action)=(["\'])/(?!/)(?!etc\.clientlibs/|aemapi/|assets/|iam/|is/)([^"\']*)\2~i',
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
                    console.log("Saved product list");
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
                    console.log("Locked product list");
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
