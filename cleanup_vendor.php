<?php
$file = __DIR__ . '/public/vendor/index.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$total = count($lines);
echo "Total lines before: $total\n";

// Find "function viewRaffle" - keep the FIRST occurrence and delete everything after it
// until "let colombiaData = []" + "async function loadGeographyData"
// But we also need to remove the second "viewRaffle" and dead code around it

// Strategy: Find ALL the dead code blocks and remove them

// Find the FIRST viewRaffle
$firstViewRaffle = -1;
$firstLoadGeo = -1;
$deadCodeStart = -1;

for ($i = 0; $i < $total; $i++) {
    if (strpos($lines[$i], 'function viewRaffle') !== false && $firstViewRaffle === -1) {
        $firstViewRaffle = $i;
    }
    if (strpos($lines[$i], 'let colombiaData = []') !== false && $firstLoadGeo === -1) {
        $firstLoadGeo = $i;
    }
}

echo "First viewRaffle: line " . ($firstViewRaffle + 1) . "\n";
echo "First colombiaData: line " . ($firstLoadGeo + 1) . "\n";

// The dead code is everything between the FIRST viewRaffle+2 and colombiaData-1
// But we need to check: is colombiaData immediately followed by loadGeographyData?
if ($firstLoadGeo > $firstViewRaffle) {
    // Check if loadGeographyData follows
    $nextNonEmpty = -1;
    for ($i = $firstLoadGeo + 1; $i < $total; $i++) {
        if (trim($lines[$i]) !== '') {
            $nextNonEmpty = $i;
            break;
        }
    }
    
    if ($nextNonEmpty !== false && strpos($lines[$nextNonEmpty], 'async function loadGeographyData') !== false) {
        echo "colombiaData followed by loadGeographyData at line " . ($nextNonEmpty + 1) . "\n";
        
        // Dead code is from firstViewRaffle+1 to firstLoadGeo-1
        // Keep firstViewRaffle, keep colombiaData+loadGeographyData
        // Delete everything in between
        $newLines = array_slice($lines, 0, $firstViewRaffle + 1);
        // Add blank lines for spacing
        $newLines[] = '';
        $newLines[] = '';
        $newLines[] = '';
        // Add the rest from colombiaData onwards
        $newLines = array_merge($newLines, array_slice($lines, $firstLoadGeo));
        
        file_put_contents($file, implode("\n", $newLines));
        $newTotal = count($newLines);
        echo "Removed " . ($total - $newTotal) . " lines of dead code\n";
        echo "Total lines after: $newTotal\n";
    } else {
        echo "ERROR: colombiaData not followed by loadGeographyData\n";
        echo "Next non-empty after colombiaData: line " . ($nextNonEmpty + 1) . ": " . trim($lines[$nextNonEmpty]) . "\n";
    }
} else {
    echo "ERROR: Could not find viewRaffle or colombiaData\n";
}
