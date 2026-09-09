<?php
/**
 * check-suppression.php — the pre-send safety gate for JD Mail.
 *
 * Cross-references the list you actually loaded (subscribers.json) against the
 * AWeber suppression list (everyone who ever unsubscribed). Answers the one
 * question that must be answered before JD Mail sends anything:
 *
 *     "Am I about to email someone who told me to stop?"
 *
 * READ ONLY. Opens both files for reading. Writes nothing, changes nothing.
 * No network of any kind. Runs on the cPanel box so no customer data moves.
 *
 * Usage:
 *   php check-suppression.php /home/johndel/bitrs-data/subscribers.json suppression-list.csv
 *   php check-suppression.php <subs.json> <suppression.csv> --write-clean=clean.txt
 *
 * Exit 0 = clean, 2 = suppressed addresses found (DO NOT SEND).
 * PHP 7.0+.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only.\n");
}

$subsPath = isset($argv[1]) ? $argv[1] : '';
$supPath  = isset($argv[2]) ? $argv[2] : '';
$writeTo  = null;
foreach ($argv as $a) {
    if (strpos($a, '--write-clean=') === 0) {
        $writeTo = substr($a, 14);
    }
}

if ($subsPath === '' || $supPath === '') {
    fwrite(STDERR, "Usage: php check-suppression.php <subscribers.json> <suppression-list.csv> [--write-clean=FILE]\n");
    exit(2);
}
foreach (array($subsPath, $supPath) as $p) {
    if (!is_file($p) || !is_readable($p)) {
        fwrite(STDERR, "FATAL: not readable: {$p}\n");
        exit(2);
    }
}

// ---------------------------------------------------- load subscribers.json --
$raw  = file_get_contents($subsPath);
$data = json_decode($raw, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "FATAL: invalid JSON in {$subsPath} — " . json_last_error_msg() . "\n");
    exit(2);
}

$loaded = array();          // normalised email => original row index/key
$shape  = 'unknown';
$badRows = 0;

$grab = function ($row) {
    if (is_string($row)) { return $row; }
    if (is_array($row)) {
        foreach (array('email', 'Email', 'EMAIL', 'address', 'mail', 'e') as $k) {
            if (isset($row[$k]) && is_string($row[$k])) { return $row[$k]; }
        }
    }
    return null;
};

if (is_array($data)) {
    $isList = array_keys($data) === range(0, count($data) - 1);
    if ($isList) {
        $shape = 'LIST of ' . count($data) . ' rows';
        foreach ($data as $i => $row) {
            $e = $grab($row);
            if ($e === null) { $badRows++; continue; }
            $loaded[strtolower(trim($e))] = $i;
        }
    } else {
        $wrapped = null;
        foreach (array('subscribers', 'list', 'rows', 'data', 'emails') as $k) {
            if (isset($data[$k]) && is_array($data[$k])) { $wrapped = $k; break; }
        }
        if ($wrapped !== null) {
            $shape = 'OBJECT, rows under "' . $wrapped . '"';
            foreach ($data[$wrapped] as $i => $row) {
                $e = $grab($row);
                if ($e === null) { $badRows++; continue; }
                $loaded[strtolower(trim($e))] = $i;
            }
        } else {
            $shape = 'MAP keyed by address (' . count($data) . ' keys)';
            foreach ($data as $k => $row) {
                $e = $grab($row);
                if ($e === null) { $e = $k; }
                if (!is_string($e)) { $badRows++; continue; }
                $loaded[strtolower(trim($e))] = $k;
            }
        }
    }
} else {
    fwrite(STDERR, "FATAL: top-level JSON is not an array/object.\n");
    exit(2);
}

// -------------------------------------------------- load the suppression CSV --
$sup = array();
$fh  = fopen($supPath, 'r');
$hdr = fgetcsv($fh);
$col = 0;
if ($hdr) {
    foreach ($hdr as $i => $h) {
        if (stripos(trim($h), 'email') !== false) { $col = $i; break; }
    }
}
while (($row = fgetcsv($fh)) !== false) {
    if (!isset($row[$col])) { continue; }
    $e = strtolower(trim($row[$col]));
    if ($e !== '' && strpos($e, '@') !== false) { $sup[$e] = true; }
}
fclose($fh);

// ------------------------------------------------------------------ compare --
$hits = array();
foreach (array_keys($loaded) as $e) {
    if (isset($sup[$e])) { $hits[] = $e; }
}
sort($hits);

$rule = str_repeat('=', 70);
echo "JD MAIL — PRE-SEND SUPPRESSION CHECK\n";
echo "run at      : " . date('Y-m-d H:i:s T') . "\n";
echo "loaded list : {$subsPath}\n";
echo "json shape  : {$shape}\n";
echo "suppression : {$supPath}\n";
echo "MUTATIONS   : none. Both files opened read-only.\n";
echo $rule . "\n\n";

printf("  addresses loaded in JD Mail : %d\n", count($loaded));
printf("  addresses on suppression    : %d\n", count($sup));
if ($badRows) {
    printf("  WARNING: %d row(s) in subscribers.json yielded no address\n", $badRows);
}

echo "\n" . $rule . "\n\n";

if ($hits) {
    printf("  *** %d SUPPRESSED ADDRESS(ES) FOUND IN THE LOADED LIST ***\n\n", count($hits));
    foreach ($hits as $e) { echo "    {$e}\n"; }
    echo "\n  These people unsubscribed in AWeber. Mailing them is a complaint\n";
    echo "  waiting to happen and, in most jurisdictions, unlawful.\n";
    echo "  REMOVE THEM BEFORE JD MAIL SENDS ANYTHING.\n";
} else {
    echo "  No suppressed addresses in the loaded list. Clean on this check.\n";
}

// ------------------------------------------------------------ known probes --
echo "\n" . $rule . "\n\nKNOWN-ADDRESS PROBES\n";
$probes = array(
    'mark@lyfordoffice.com' => array(true,  'best subscriber: 18 clicks, opened 36/36 in 2026'),
    'brm444@msn.com'        => array(true,  'confirmed clicker (3 clicks)'),
    'ghaleib@gmail.com'     => array(true,  'confirmed clicker (4 clicks), opened ~14/27 in 2026'),
    'dlucco@gmail.com'      => array(false, '330 sends, 10 opens, 0 clicks — dead weight'),
);
$fails = 0;
foreach ($probes as $addr => $spec) {
    list($want, $why) = $spec;
    $have = isset($loaded[$addr]);
    $ok   = ($have === $want);
    if (!$ok) { $fails++; }
    printf("  [%s] %-26s %-11s (expected %s) — %s\n",
        $ok ? 'ok' : '!!',
        $addr,
        $have ? 'PRESENT' : 'absent',
        $want ? 'present' : 'absent',
        $why);
}
if ($fails) {
    echo "\n  {$fails} probe(s) unexpected. If the engaged addresses are missing, the\n";
    echo "  selection that built this list cut people it should have kept.\n";
}

// ------------------------------------------------------------------ output --
if ($writeTo !== null && $hits) {
    $clean = array_values(array_diff(array_keys($loaded), $hits));
    sort($clean);
    file_put_contents($writeTo, implode("\n", $clean) . "\n");
    echo "\n  wrote " . count($clean) . " clean addresses -> {$writeTo}\n";
    echo "  (this is a plain list for you to rebuild from — subscribers.json was NOT modified)\n";
}

echo "\n" . $rule . "\n";
if ($hits) {
    printf("VERDICT: %d suppressed address(es). DO NOT SEND. Exit 2.\n", count($hits));
    exit(2);
}
echo "VERDICT: no suppressed addresses found. Exit 0.\n";
exit(0);
