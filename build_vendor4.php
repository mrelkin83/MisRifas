<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
$src = __DIR__ . '/public/admin/index.php';
$dst = __DIR__ . '/public/vendor/index.php';

$source = file_get_contents($src);
if (!$source) die("Cannot read source\n");

// Normalize line endings
$source = str_replace("\r\n", "\n", $source);
$source = str_replace("\r", "\n", $source);

// 1. Replace PHP header
// Match the old header exactly as it appears
$lines = explode("\n", $source);

// Find line 1-8 (header)
// Replace with new header including session_start
$newLines = [];
$newLines[] = "<?php";
$newLines[] = "session_start();";
$newLines[] = $lines[1]; // header("Cache-Control...")
$newLines[] = $lines[2]; // header("Pragma...")
$newLines[] = $lines[3]; // header("Expires...")
$newLines[] = "";
$newLines[] = $lines[5]; // require_once database
$newLines[] = "require_once __DIR__ . '/../../config/paths.php';";
$newLines[] = "";
$newLines[] = '$vendorId = $_SESSION[\'user_id\'] ?? null;';
$newLines[] = '$userRole = $_SESSION[\'user_role\'] ?? \'\';';
$newLines[] = "";
$newLines[] = "if (!\$vendorId || !in_array(\$userRole, ['vendor', 'super_admin'])) {";
$newLines[] = "    header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/public/admin/index.php?auth=login');";
$newLines[] = "    exit;";
$newLines[] = "}";
$newLines[] = "";
$newLines[] = "\$page_title = \"Panel Vendedor - MisRifas\";";
$newLines[] = "?>";

// Skip lines 1-8 from original, add newLines, continue with line 9
$lines = array_merge($newLines, array_slice($lines, 8));
$source = implode("\n", $lines);
echo "1. Header replaced\n";

// 2. Remove login/register block
$isAuthLine = -1;
$layoutLine = -1;
foreach ($lines as $i => $line) {
    if ($isAuthLine === -1 && strpos($line, '<?php if ($is_auth_page): ?>') !== false) $isAuthLine = $i;
    if ($isAuthLine !== -1 && $layoutLine === -1 && strpos($line, '<div class="admin-layout">') !== false) $layoutLine = $i;
}
echo "2. is_auth_line=" . ($isAuthLine + 1) . " layout_line=" . ($layoutLine + 1) . "\n";

if ($isAuthLine === -1 || $layoutLine === -1) die("Cannot find login/layout markers\n");

// Find <?php else: ?> between isAuthLine and layoutLine
$elseLine = -1;
$elseSearch = '<' . '?php else: ?' . '>';
for ($i = $isAuthLine; $i < $layoutLine; $i++) {
    if (strpos($lines[$i], $elseSearch) !== false) { $elseLine = $i; break; }
}
if ($elseLine === -1) die("Cannot find else marker\n");

// Remove from isAuthLine to elseLine inclusive
$newLines = array_slice($lines, 0, $isAuthLine);
$newLines[] = "    "; // continuation - removed login/register
$newLines = array_merge($newLines, array_slice($lines, $elseLine + 1));
$lines = $newLines;
$source = implode("\n", $lines);
echo "2. Login/register removed\n";

// 3. Remove final endif
$endifLine = -1;
$endifSearch = '<' . '?php endif; ?' . '>';
foreach ($lines as $i => $line) {
    if (trim($line) === $endifSearch) $endifLine = $i;
}
if ($endifLine !== -1) {
    array_splice($lines, $endifLine, 1);
if ($endifLine !== -1) {
    array_splice($lines, $endifLine, 1);
    $source = implode("\n", $lines);
    echo "3. Endif removed (line " . ($endifLine + 1) . ")\n";
}

// Re-read after modifications
$source = implode("\n", $lines);

// Remove final endif
$endifSearch = '<' . '?php endif; ?' . '>';
$endifLine = -1;
foreach ($lines as $i => $line) {
    if (trim($line) === $endifSearch) $endifLine = $i;
}
if ($endifLine !== -1) {
    array_splice($lines, $endifLine, 1);
    $source = implode("\n", $lines);
    echo "3. Endif removed\n";
}

// Re-read
$source = implode("\n", $lines);

// 4. Remove 4 nav items
$navSecs = ['comisiones', 'gestion-rifas', 'email-campaigns', 'banners'];
foreach ($navSecs as $s) {
    $before = $source;
    $source = preg_replace('/\n\s*<a href="#' . preg_quote($s, '/') . '"[^>]*>.*?<\/a>/s', '', $source);
    if ($before !== $source) echo "4. Nav '$s' removed\n";
}

// 5. Remove 4 HTML sections (using depth tracking)
$secIds = ['section-gestion-rifas', 'section-comisiones', 'section-banners', 'section-email-campaigns'];
foreach ($secIds as $sid) {
    $tag = '<div id="' . $sid . '" class="admin-section';
    $sp = strpos($source, $tag);
    if ($sp === false) { echo "5. SKIP $sid\n"; continue; }
    
    $cs = strrpos(substr($source, 0, $sp), "\n<!--");
    if ($cs !== false) $sp = $cs;
    
    $pos = $sp + strlen($tag);
    $depth = 0;
    $endP = false;
    while ($pos < strlen($source) && $depth >= 0) {
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

// 6. Remove banners-form and campaign-form event listeners
function removeEL($code, $id) {
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
            return substr($code, 0, $lineStart) . ($lineEnd !== false ? substr($code, $lineEnd) : '');
        }
    }
    return false;
}

$r = removeEL($source, 'banners-form');
if ($r !== false) { $source = $r; echo "6. banners-form listener removed\n"; }
$r = removeEL($source, 'campaign-form');
if ($r !== false) { $source = $r; echo "6. campaign-form listener removed\n"; }

// 7. Remove dead functions
function removeFunc($code, $name) {
    $pattern = '/\n[ \t]+function ' . preg_quote($name, '/') . '\s*\(/';
    $count = 0;
    while (preg_match($pattern, $code, $m)) {
        $funcStart = strrpos(substr($code, 0, $m[0]), "\n");
        $braces = 0;
        $started = false;
        $endP = false;
        for ($i = $m[0]; $i < strlen($code); $i++) {
            if ($code[$i] === '{') { $braces++; $started = true; }
            if ($code[$i] === '}') { $braces--; }
            if ($started && $braces === 0) {
                $endP = $i + 2; break;
            }
        }
        if ($endP) {
            $code = substr($code, 0, $funcStart) . substr($code, $endP);
            $count++;
        } else break;
    }
    return $count;
}

$c1 = removeFunc($source, 'renderBannerSlide');
$c2 = removeFunc($source, 'loadBannersConfig');
$c3 = removeFunc($source, 'loadCampaigns');
$c4 = removeFunc($source, 'loadEmailSettings');
if ($c1) echo "7. renderBannerSlide x$c1 removed\n";
if ($c2) echo "7. loadBannersConfig x$c2 removed\n";
if ($c3) echo "7. loadCampaigns x$c3 removed\n";
if ($c4) echo "7. loadEmailSettings x$c4 removed\n";

// 8. Remove super_admin hide logic  
$source = preg_replace("/\n\s*\/\/\s*Ocultar secciones exclusivas.*?if \(navGestionRifas\).*?\n/s", "\n", $source);
$source = preg_replace("/\n\s*\/\/\s*Re-verificar rol.*?\n/s", "\n", $source);
echo "8. Hide logic removed\n";

// 9. Remove nav vars
foreach (['navComisiones', 'navCampaigns', 'navBanners', 'navGestionRifas'] as $v) {
    $before = $source;
    $source = preg_replace('/\s*var ' . $v . '\s*=.*?;\n/', '', $source);
    if ($before !== $source) echo "9. var $v removed\n";
}

// 10. Clean switchTo titles and loaders
$removeFromTitles = ['rifas', 'comisiones', 'banners', 'gestion-rifas', 'email-campaigns'];
$removeFromLoaders = ['rifas', 'comisiones', 'banners', 'gestion-rifas', 'email-campaigns'];

foreach ($removeFromTitles as $s) {
    $source = preg_replace("/,\s*'" . preg_quote($s, '/') . "':\s*'[^']*'/\n/", '', $source);
}
foreach ($removeFromLoaders as $s) {
    $source = preg_replace("/\s*if \(section === '" . preg_quote($s, '/') . "'\)[^{]+\n/", '', $source);
    $source = preg_replace("/\s*if \(section === '" . preg_quote($s, '/') . "'\)\s*\{[^}]+load[A-Z][^;]+;\s*\}\s*\n/", '', $source);
}
// Specifically handle email-campaigns which has a block
$source = preg_replace("/\s*if \(section === 'email-campaigns'\)\s*\{[^}]+\}\s*\n/", '', $source);
echo "10. Titles and loaders cleaned\n";

// 11. Fix token checks
function fixTokenCheck($src, $old, $new) {
    if (strpos($src, $old) === false) return false;
    return str_replace($old, $new, $src);
}

$tcOld1 = "const token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        window.location.href = BASE_PATH + '/public/admin/index.php?auth=login';\n    }";
$tcNew1 = "const token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        fetch(BASE_PATH + '/api/auth/me.php', { credentials: 'same-origin' })\n            .then(function(r) { return r.json(); })\n            .then(function(data) {\n                if (data.success && data.data && data.data.token) {\n                    localStorage.setItem('misrifas_token', data.data.token);\n                    localStorage.setItem('misrifas_user', JSON.stringify(data.data));\n                    location.reload();\n                } else { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; }\n            })\n            .catch(function() { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; });\n    }";
if (fixTokenCheck($source, $tcOld1, $tcNew1)) echo "11. Token check 1 fixed\n";

$tcOld2 = "var token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        window.location.href = BASE_PATH + '/public/admin/index.php?auth=login';\n        return;\n    }";
$tcNew2 = "var token = localStorage.getItem('misrifas_token');\n    if (!token) {\n        fetch(BASE_PATH + '/api/auth/me.php', { credentials: 'same-origin' })\n            .then(function(r) { return r.json(); })\n            .then(function(data) {\n                if (data.success && data.data && data.data.token) {\n                    localStorage.setItem('misrifas_token', data.data.token);\n                    localStorage.setItem('misrifas_user', JSON.stringify(data.data));\n                    location.reload();\n                } else { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; }\n            })\n            .catch(function() { window.location.href = BASE_PATH + '/public/admin/index.php?auth=login'; });\n        return;\n    }";
if (fixTokenCheck($source, $tcOld2, $tcNew2)) echo "11. Token check 2 fixed\n";

// 12. Fix switchSection -> switchTo
$source = str_replace("switchSection('dashboard')", "switchTo('dashboard')", $source);
echo "12. switchSection fixed\n";

// 13. Fix Boletas API  
$source = str_replace(
    "API.get('/user/tickets.php', { user_id: user.id })",
    "API.get('/user/tickets.php', { user_id: user.id, source: 'vendor' })",
    $source
);
echo "13. Boletas API fixed\n";

// 14. Fix Profile save
$source = str_replace(
    "API.post('/user/update_profile.php', formData)",
    "fetch(BASE_PATH + '/api/user/update_profile.php', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, body: formData }).then(function(r) { return r.json(); })",
    $source
);
echo "14. Profile save fixed\n";

// 15. Guard loadSettings commission crash
$source = str_replace(
    "toggleCommissionUI(data.settings);",
    "if (data.settings && document.getElementById('commission-enabled')) toggleCommissionUI(data.settings);",
    $source
);
echo "15. loadSettings guarded\n";

// Save
file_put_contents($dst, $source);
$finalLines = count(explode("\n", $source));
echo "\nSaved: $finalLines lines\n";

// Final verification
$issues = [];
foreach (['nav-comisiones', 'nav-gestion-rifas', 'nav-campaigns', 'nav-banners',
    'section-comisiones', 'section-gestion-rifas', 'section-email-campaigns', 'section-banners',
    'is_auth_page', 'banners-form', 'campaign-form', 'loadBannersConfig',
    'renderBannerSlide', 'loadCampaigns', 'loadEmailSettings', 'switchSection',
    'navComisiones', 'navCampaigns', 'navBanners', 'navGestionRifas'] as $c) {
    if (strpos($source, $c) !== false) $issues[] = $c;
}
echo empty($issues) ? "VERIFIED CLEAN - all checks passed\n" : "ISSUES REMAINING: " . implode(', ', $issues) . "\n";
