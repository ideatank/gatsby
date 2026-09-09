<?php
/**
 * list-hygiene.php — offline hygiene sweep of the JD Mail subscriber list.
 *
 * READ ONLY. Opens subscribers.json for reading, never writes to it, never
 * deletes or modifies a subscriber row. The only network traffic is DNS
 * (MX / A resolution). No SMTP connection is ever opened to any host.
 *
 * Usage (on the cPanel box, from any directory):
 *   php list-hygiene.php /home/johndel/bitrs-data/subscribers.json
 *   php list-hygiene.php /home/johndel/bitrs-data/subscribers.json --no-dns
 *   php list-hygiene.php /home/johndel/bitrs-data/subscribers.json > sweep.txt
 *
 * PHP 7.0+. No composer, no extensions beyond the standard build.
 */

// ---------------------------------------------------------------- guards ----

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only. This script must never be served over HTTP.\n");
}

$path   = isset($argv[1]) ? $argv[1] : '';
$useDns = !in_array('--no-dns', $argv, true);

if ($path === '') {
    fwrite(STDERR, "Usage: php list-hygiene.php <path-to-subscribers.json> [--no-dns]\n");
    exit(2);
}
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "FATAL: not a readable file: {$path}\n");
    exit(2);
}

$raw = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "FATAL: could not read {$path}\n");
    exit(2);
}

$data = json_decode($raw, true);
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "FATAL: invalid JSON in {$path} — " . json_last_error_msg() . "\n");
    exit(2);
}

// ------------------------------------------------- shape detection, honest --
// The on-disk shape of subscribers.json is not documented in jd-mail-bible.
// This script does NOT guess. It reports the shape it found, extracts every
// address it can defend, and tells you how many rows it could not read.

$emails      = array();   // normalised address => first row index seen
$unreadable  = array();   // rows it could not extract an address from
$shapeNote   = 'unknown';

$extract = function ($row) {
    if (is_string($row)) {
        return $row;
    }
    if (is_array($row)) {
        foreach (array('email', 'Email', 'EMAIL', 'address', 'mail', 'e') as $k) {
            if (isset($row[$k]) && is_string($row[$k])) {
                return $row[$k];
            }
        }
    }
    return null;
};

if (is_array($data)) {
    $isList = array_keys($data) === range(0, count($data) - 1);

    if ($isList) {
        $shapeNote = 'top-level LIST of ' . count($data) . ' rows';
        foreach ($data as $i => $row) {
            $e = $extract($row);
            if ($e === null) { $unreadable[] = $i; continue; }
            $emails[strtolower(trim($e))] = $i;
        }
    } else {
        // Could be a map keyed by email, or a wrapper object such as
        // {"subscribers": [...]}. Handle the wrapper first.
        $wrapped = null;
        foreach (array('subscribers', 'list', 'rows', 'data', 'emails') as $k) {
            if (isset($data[$k]) && is_array($data[$k])) { $wrapped = $k; break; }
        }
        if ($wrapped !== null) {
            $shapeNote = 'wrapper OBJECT, list under key "' . $wrapped . '" ('
                       . count($data[$wrapped]) . ' rows)';
            foreach ($data[$wrapped] as $i => $row) {
                $e = $extract($row);
                if ($e === null) { $unreadable[] = $i; continue; }
                $emails[strtolower(trim($e))] = $i;
            }
        } else {
            $shapeNote = 'top-level MAP of ' . count($data) . ' keys (keys treated as addresses)';
            foreach ($data as $k => $row) {
                $e = $extract($row);
                if ($e === null) { $e = $k; }          // key is the address
                if (!is_string($e)) { $unreadable[] = $k; continue; }
                $emails[strtolower(trim($e))] = $k;
            }
        }
    }
} else {
    fwrite(STDERR, "FATAL: top-level JSON is not an array/object. Cannot proceed.\n");
    exit(2);
}

// ------------------------------------------------------------ classifiers --

$DISPOSABLE = array(
    'mailinator.com', '10minutemail.com', '10minutemail.net', 'guerrillamail.com',
    'guerrillamail.net', 'guerrillamail.org', 'guerrillamail.biz', 'sharklasers.com',
    'grr.la', 'temp-mail.org', 'tempmail.com', 'tempmailo.com', 'throwawaymail.com',
    'yopmail.com', 'yopmail.fr', 'getnada.com', 'nada.email', 'dispostable.com',
    'trashmail.com', 'trashmail.de', 'mytrashmail.com', 'maildrop.cc',
    'fakeinbox.com', 'spamgourmet.com', 'mailnesia.com', 'tempinbox.com',
    'emailondeck.com', 'moakt.com', 'mohmal.com', 'tempr.email', 'discard.email',
    'spam4.me', 'burnermail.io', 'anonaddy.me', 'mailcatch.com', 'inboxbear.com',
    'einrot.com', 'harakirimail.com', 'mailexpire.com', 'jetable.org',
);

$ROLE = array(
    'info', 'sales', 'admin', 'support', 'webmaster', 'postmaster', 'noreply',
    'no-reply', 'donotreply', 'do-not-reply', 'abuse', 'contact', 'help',
    'office', 'billing', 'accounts', 'marketing', 'hello', 'enquiries',
    'inquiries', 'team', 'hr', 'jobs', 'careers', 'security', 'root',
);

$buckets = array(
    'syntax_invalid' => array(),
    'undeliverable'  => array(),   // domain has neither MX nor A
    'disposable'     => array(),
    'role'           => array(),
    'clean'          => array(),
);
$dnsSkipped = array();             // when --no-dns is set
$dnsCache   = array();             // domain => true(resolvable)/false

$resolves = function ($domain) use (&$dnsCache) {
    if (isset($dnsCache[$domain])) {
        return $dnsCache[$domain];
    }
    // checkdnsrr returns false on lookup failure AND on "no such record".
    // MX first (the record that actually matters), then A as the RFC 5321
    // implicit-MX fallback.
    $ok = @checkdnsrr($domain, 'MX');
    if (!$ok) {
        $ok = @checkdnsrr($domain, 'A');
    }
    if (!$ok) {
        $ok = @checkdnsrr($domain, 'AAAA');
    }
    $dnsCache[$domain] = (bool) $ok;
    return $dnsCache[$domain];
};

// ------------------------------------------------------------------ sweep --

foreach (array_keys($emails) as $addr) {

    if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false || strpos($addr, '@') === false) {
        $buckets['syntax_invalid'][] = $addr;
        continue;
    }

    $at     = strrpos($addr, '@');
    $local  = substr($addr, 0, $at);
    $domain = substr($addr, $at + 1);

    if (in_array($domain, $DISPOSABLE, true)) {
        $buckets['disposable'][] = $addr;
        continue;
    }

    $localBase = $local;
    $plus = strpos($localBase, '+');
    if ($plus !== false) {
        $localBase = substr($localBase, 0, $plus);
    }
    if (in_array($localBase, $ROLE, true)) {
        $buckets['role'][] = $addr;
        continue;
    }

    if (!$useDns) {
        $dnsSkipped[] = $addr;
        continue;
    }

    if (!$resolves($domain)) {
        $buckets['undeliverable'][] = $addr;
        continue;
    }

    $buckets['clean'][] = $addr;
}

// ----------------------------------------------------------------- report --

$total = count($emails);
$line  = str_repeat('-', 68);

echo "JD MAIL — OFFLINE LIST HYGIENE SWEEP\n";
echo "generated : " . date('Y-m-d H:i:s T') . "\n";
echo "source    : {$path}\n";
echo "file size : " . number_format(strlen($raw)) . " bytes\n";
echo "json shape: {$shapeNote}\n";
echo "addresses : {$total} unique (after lowercase + trim)\n";
echo "dns checks: " . ($useDns ? 'ENABLED (MX/A/AAAA only — no SMTP)' : 'SKIPPED (--no-dns)') . "\n";
if ($unreadable) {
    echo "WARNING   : " . count($unreadable) . " row(s) yielded no address — indexes: "
       . implode(', ', array_slice($unreadable, 0, 40))
       . (count($unreadable) > 40 ? ' ...' : '') . "\n";
}
echo "MUTATIONS : none. This script opened the file read-only.\n";
echo $line . "\n\n";

echo "COUNTS\n";
$order = array('clean', 'role', 'disposable', 'undeliverable', 'syntax_invalid');
foreach ($order as $k) {
    $n   = count($buckets[$k]);
    $pct = $total > 0 ? sprintf('%5.1f%%', ($n / $total) * 100) : '  n/a';
    printf("  %-16s %6d   %s\n", $k, $n, $pct);
}
if ($dnsSkipped) {
    printf("  %-16s %6d   (unclassified: DNS disabled)\n", 'dns_skipped', count($dnsSkipped));
}
$dead = count($buckets['syntax_invalid']) + count($buckets['undeliverable']);
printf("\n  hard dead weight (syntax_invalid + undeliverable): %d  (%.1f%% of list)\n",
    $dead, $total > 0 ? ($dead / $total) * 100 : 0);
echo "\n" . $line . "\n";

foreach ($order as $k) {
    if ($k === 'clean') { continue; }
    echo "\n" . strtoupper($k) . " — " . count($buckets[$k]) . " address(es)\n";
    if (!$buckets[$k]) {
        echo "  (none)\n";
        continue;
    }
    sort($buckets[$k]);
    foreach ($buckets[$k] as $a) {
        echo "  {$a}\n";
    }
}

if ($useDns) {
    $bad = array();
    foreach ($dnsCache as $d => $ok) { if (!$ok) { $bad[] = $d; } }
    sort($bad);
    echo "\n" . $line . "\n";
    echo "\nNON-RESOLVING DOMAINS — " . count($bad) . " of " . count($dnsCache) . " distinct domains\n";
    foreach ($bad as $d) { echo "  {$d}\n"; }
}

echo "\n" . $line . "\n";
echo "END OF REPORT. No subscriber row was deleted or modified.\n";
