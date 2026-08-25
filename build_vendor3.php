<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
$src = __DIR__ . '/public/admin/index.php';
$dst = __DIR__ . '/public/vendor/index.php';

$source = file_get_contents($src);
if (!$source) die("Cannot read source\n");

$p = '<' . '?php';
$c = '?' . '>';
$NL = "\n";

// 1. Replace PHP header
$hdrOld = "\xef\xbb\xbf" . $p . "\nheader(\"Cache-Control: no-cache, no-store, must-revalidate\");\nheader(\"Pragma: no-cache\");\nheader(\"Expires: 0\");\n\nrequire_once __DIR__ . '/../../config/database.php';\n\$page_title = \"Panel de Administraci\u00f3n - MisRifas\";\n\$is_auth_page = isset(\$_GET['auth']) && in_array(\$_GET['auth'], ['login', 'register']);\n" . $c;

$hdrNew = $p . "\nsession_start();\nheader(\"Cache-Control: no-cache, no-store, must-revalidate\");\nheader(\"Pragma: no-cache\");\nheader(\"Expires: 0\");\n\nrequire_once __DIR__ . '/../../config/database.php';\nrequire_once __DIR__ . '/../../config/paths.php';\n\n\$vendorId = \$_SESSION['user_id'] ?? null;\n\$userRole = \$_SESSION['user_role'] ?? '';\n\nif (!\$vendorId || !in_array(\$userRole, ['vendor', 'super_admin'])) {\n    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/public/admin/index.php?auth=login');\n    exit;\n}\n\n\$page_title = \"Panel Vendedor - MisRifas\";\n" . $c;

$pos = strpos($source, $hdrOld);
if ($pos !== false) {
    $end = strpos($source, $c, $pos);
    $source = substr($source, 0, $pos) . $hdrNew . $NL . substr($source, $end + strlen($c));
    echo "1. Header replaced\n";
} else { die("Header not found\n"); }

// 2. Remove login/register block
$loginTag = $p . ' if ($is_auth_page): ' . $c;
$layoutTag = '<div class="admin-layout">';
$lp = strpos($source, $loginTag);
$layoutP = strpos($source, $layoutTag);
if ($lp !== false && $layoutP !== false) {
    $elseTag = $p . ' else: ' . $c;
    $ep = strpos($source, $elseTag, $lp);
    if ($ep !== false) {
        $source = substr($source, 0, $lp) . $NL . "    " . substr($source, $ep + strlen($elseTag));
        echo "2. Login/register removed\n";
    } else { die("else tag not found\n"); }
} else { die("login tag not found\n"); }

// 3. Remove final endif
$endifTag = $NL . $p . ' endif; ' . $c;
$source = str_replace($endifTag, '', $source);
echo "3. Endif removed\n";

// 4. Remove 4 nav items
$navSecs = ['comisiones', 'gestion-rifas', 'email-campaigns', 'banners'];
foreach ($navSecs as $s) {
    $before = $source;
    $source = preg_replace('/\n\s*<a href="#' . preg_quote($s, '/') . '"[^>]*>.*?<\/a>/s', '', $source);
    if ($before !== $source) echo "4. Nav '$s' removed\n";
}

// 5. Remove 4 HTML sections
$secIds = ['section-gestion-rifas', 'section-comisiones', 'section-banners', 'section-email-campaigns'];
foreach ($secIds as $sid) {
    $tag = '<div id="' . $sid . '" class="admin-section';
    $sp = strpos($source, $tag);
    if ($sp === false) { echo "5. SKIP $sid not found\n"; continue; }
    
    $cs = strrpos(substr($source, 0, $sp), $NL . "<!--");
    if ($cs !== false) $sp = $cs;
    
    $pos = strpos($source, $tag) + strlen($tag);
    $depth = 0;
    $endP = false;
    while ($pos < strlen($source)) {
        $oP = strpos($source, '<div', $pos);
        $cP = strpos($source, '</div>', $pos);
        if ($cP === false) break;
        if ($oP !== false && $oP < $cP) {
            $ch = substr($source, $oP + 4, 1);
            if (in_array($ch, [' ', '>', "\n", "\r", "\t"])) $depth++;
            $pos = $oP + 4;
        } else {
            $depth--;
            if ($depth < 0) { $endP = $cP + 6; break; }
            $pos = $cP + 6;
        }
    }
    if ($endP) {
        $source = substr($source, 0, $sp) . substr($source, $endP);
        echo "5. Section '$sid' removed\n";
    }
}

// 6. Remove banners-form and campaign-form event listeners (the CRASH BUGS)
// Find document.getElementById('banners-form').addEventListener( ... });
// Pattern: starts at the line, ends with closing });
function removeEventListener($code, $id) {
    $search = "document.getElementById('" . $id . "').addEventListener(";
    $sp = strpos($code, $search);
    if ($sp === false) return false;
    
    $lineStart = strrpos(substr($code, 0, $sp), "\n");
    $braces = 0;
    $started = false;
    for ($i = $sp; $i < strlen($code); $i++) {
        if ($code[$i] === '{') { $braces++; $started = true; }
        if ($code[$i] === '}') { $braces--; }
        if ($started && $braces === 0) {
            $lineEnd = strpos($code, "\n", $i + 1);
            return substr($code, 0, $lineStart) . substr($code, $lineEnd !== false ? $lineEnd : strlen($code));
        }
    }
    return false;
}

$r1 = removeEventListener($source, 'banners-form');
if ($r1 !== false) { $source = $r1; echo "6. banners-form listener removed\n"; }

$r2 = removeEventListener($source, 'campaign-form');
if ($r2 !== false) { $source = $r2; echo "6. campaign-form listener removed\n"; }

// 7. Remove dead functions: renderBannerSlide, loadBannersConfig
function removeFunction($code, $name) {
    $pattern = '/\n[ \t]+function ' . preg_quote($name, '/') . '\s*\(/';
    if (!preg_match($pattern, $code, $m)) return false;
    $funcStart = strrpos(substr($code, 0, $m[0]), "\n");
    $braces = 0;
    $started = false;
    for ($i = $m[0]; $i < strlen($code); $i++) {
        if ($code[$i] === '{') { $braces++; $started = true; }
        if ($code[$i] === '}') { $braces--; }
        if ($started && $braces === 0) {
            return substr($code, 0, $funcStart) . substr($code, $i + 2);
        }
    }
    return false;
}

while (($r = removeFunction($source, 'renderBannerSlide')) !== false) { $source = $r; echo "7. renderBannerSlide removed\n"; }
while (($r = removeFunction($source, 'loadBannersConfig')) !== false) { $source = $r; echo "7. loadBannersConfig removed\n"; }
while (($r = removeFunction($source, 'loadCampaigns')) !== false) { $source = $r; echo "7. loadCampaigns removed\n"; }
while (($r = removeFunction($source, 'loadEmailSettings')) !== false) { $source = $r; echo "7. loadEmailSettings removed\n"; }

// 8. Remove super_admin hide logic
$source = preg_replace("/\s*\/\/\s*Ocultar secciones exclusivas.*?if \(navGestionRifas\).*?'n/s", $NL, $source);
$source = preg_replace("/\s*\/\/\s*Re-verificar rol.*?'\n/s", $NL, $source);
echo "8. Hide logic removed\n";

// 9. Remove nav vars
$source = preg_replace('/\s*var navComisiones\s*=.*?;\n/', '', $source);
$source = preg_replace('/\s*var navCampaigns\s*=.*?;\n/', '', $source);
$source = preg_replace('/\s*var navBanners\s*=.*?;\n/', '', $source);
$source = preg_replace('/\s*var navGestionRifas\s*=.*?;\n/', '', $source);
echo "9. Nav vars removed\n";

// 10. Clean titles in switchTo
$source = preg_replace("/rifas:\s*'Mis Rifas',\s*\n/", '', $source);
$source = preg_replace("/comisiones:\s*'Comisiones',\s*\n/", '', $source);
$source = preg_replace("/banners:\s*'Gesti[^']*',\s*\n/", '', $source);
$source = preg_replace("/'gestion-rifas':\s*'Gesti[^']*',\s*\n/", '', $source);
$source = preg_replace("/'email-campaigns':\s*'Camp[^']*',\s*\n/", '', $source);
echo "10. Titles cleaned\n";

// 11. Clean switchTo loaders
$source = preg_replace("/\s*if \(section === 'rifas'\) loadAllRaffles\(\);\s*\n/", '', $source);
$source = preg_replace("/\s*if \(section === 'comisiones'\) loadCommissions\(\);\s*\n/", '', $source);
$source = preg_replace("/\s*if \(section === 'banners'\) loadBannersConfig\(\);\s*\n/", '', $source);
$source = preg_replace("/\s*if \(section === 'gestion-rifas'\) loadGestionRaffles\(\);\s*\n/", '', $source);
echo "11. Loaders cleaned\n";

// 12. Fix token checks
$tc1 = "const token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        window.location.href = BASE_PATH + '/public/admin/index.php?auth=login';\n    }";
$tc1r = "const token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        fetch(BASE_PATH + '/api/auth/me.php', { credentials: 'same-origin' })\n            .then(function(r) { return r.json(); })\n            .then(function(data) {\n                if (data.success && data.data && data.data.token) {\n                    localStorage.setItem('misrifas_token', data.data.token);\n                    localStorage.setItem('misrifas_user', JSON.stringify(data.data));\n                    location.reload();\n                } else { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; }\n            })\n            .catch(function() { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; });\n    }";
if (strpos($source, $tc1) !== false) { $source = str_replace($tc1, $tc1r, $source); echo "12. Token check 1 fixed\n"; }

$tc2 = "var token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        window.location.href = BASE_PATH + '/public/admin/index.php?auth=login';\n        return;\n    }";
$tc2r = "var token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        fetch(BASE_PATH + '/api/auth/me.php', { credentials: 'same-origin' })\n            .then(function(r) { return r.json(); })\n            .then(function(data) {\n                if (data.success && data.data && data.data.token) {\n                    localStorage.setItem('misrifas_token', data.data.token);\n                    localStorage.setItem('misrifas_user', JSON.stringify(data.data));\n                    location.reload();\n                } else { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; }\n            })\n            .catch(function() { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; });\n        return;\n    }";
if (strpos($source, $tc2) !== false) { $source = str_replace($tc2, $tc2r, $source); echo "12. Token check 2 fixed\n"; }

// 13. Fix switchSection -> switchTo
$source = str_replace("switchSection('dashboard')", "switchTo('dashboard')", $source);
echo "13. switchSection fixed\n";

// 14. Fix Boletas API
$oldT = "API.get('/user/tickets.php', { user_id: user.id })";
$newT = "API.get('/user/tickets.php', { user_id: user.id, source: 'vendor' })";
if (strpos($source, $oldT) !== false) { $source = str_replace($oldT, $newT, $source); echo "14. Boletas API fixed\n"; }

// 15. Fix Profile save
$oldP = "API.post('/user/update_profile.php', formData)";
$newP = "fetch(BASE_PATH + '/api/user/update_profile.php', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, body: formData }).then(function(r) { return r.json(); })";
if (strpos($source, $oldP) !== false) { $source = str_replace($oldP, $newP, $source); echo "15. Profile save fixed\n"; }

// 16. Guard loadSettings commission crash
$oldCS = "toggleCommissionUI(data.settings);";
$newCS = "if (data.settings && document.getElementById('commission-enabled')) toggleCommissionUI(data.settings);";
if (strpos($source, $oldCS) !== false) { $source = str_replace($oldCS, $newCS, $source); echo "16. loadSettings guarded\n"; }

// Save
file_put_contents($dst, $source);
$finalLines = count(explode($NL, $source));
echo "\nDone: $finalLines lines\n";

// Final verify
$checks = ['nav-comisiones', 'nav-gestion-rifas', 'nav-campaigns', 'nav-banners',
    'section-comisiones', 'section-gestion-rifas', 'section-email-campaigns', 'section-banners',
    'is_auth_page', 'banners-form', 'campaign-form', 'loadBannersConfig',
    'renderBannerSlide', 'loadCampaigns', 'loadEmailSettings', 'switchSection',
    'navComisiones', 'navCampaigns', 'navBanners', 'navGestionRifas'];
$issues = [];
foreach ($checks as $c) {
    if (strpos($source, $c) !== false) $issues[] = $c;
}
echo empty($issues) ? "VERIFIED CLEAN\n" : "ISSUES: " . implode(', ', $issues) . "\n";
