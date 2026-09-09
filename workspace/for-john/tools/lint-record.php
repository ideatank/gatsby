<?php
/**
 * lint-record.php — check a candidate SPF or DMARC record string BEFORE you
 * paste it into the zone editor. Offline: no DNS, no network, no filesystem.
 *
 * Usage:
 *   php lint-record.php spf   'v=spf1 ip4:1.2.3.4 mx ~all'
 *   php lint-record.php dmarc 'v=DMARC1; p=none; fo=1; rua=mailto:x@y.tld'
 *
 * Exit 0 = clean, 1 = warnings, 2 = errors. Quote the record in SINGLE quotes
 * so the shell does not eat the semicolons or expand anything.
 *
 * PHP 7.0+.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only.\n");
}

$kind = isset($argv[1]) ? strtolower($argv[1]) : '';
$rec  = isset($argv[2]) ? $argv[2] : '';

if (($kind !== 'spf' && $kind !== 'dmarc') || $rec === '') {
    fwrite(STDERR, "Usage: php lint-record.php <spf|dmarc> '<record string>'\n");
    fwrite(STDERR, "Use SINGLE quotes so the shell leaves semicolons alone.\n");
    exit(2);
}

$E = array();
$W = array();
$O = array();

echo "LINT " . strtoupper($kind) . " (offline — no DNS consulted)\n";
echo str_repeat('-', 70) . "\n";
echo $rec . "\n\n";

// -------------------------------------------------------- shared checks ----

$len = strlen($rec);
echo "length: {$len} bytes\n";
if ($len > 255) {
    $W[] = "Record is {$len} bytes. A single TXT character-string caps at 255; the record must be "
         . "published as multiple chunks. Most control panels do this automatically — verify with "
         . "verify-dns.php after publishing that it reassembles correctly.";
} else {
    $O[] = "Fits in one 255-byte TXT string ({$len} bytes).";
}
if ($rec !== trim($rec)) {
    $W[] = 'Record has leading or trailing whitespace. Trim it before pasting.';
}
if (strpos($rec, "\n") !== false || strpos($rec, "\r") !== false) {
    $E[] = 'Record contains a line break. It must be a single line.';
}
if (preg_match("/[\xE2\x80\x98\xE2\x80\x99\xE2\x80\x9C\xE2\x80\x9D]/", $rec)) {
    $E[] = 'Record contains a smart/curly quote. Retype it — a copy through a word processor '
         . 'or chat client will silently corrupt the record.';
}
if ($rec !== '' && ($rec[0] === '"' || substr($rec, -1) === '"')) {
    $W[] = 'Record is wrapped in literal double quotes. Most panels add the quoting themselves; '
         . 'pasting them can produce a record whose value literally starts with a quote character.';
}

// ------------------------------------------------------------------ SPF ----

if ($kind === 'spf') {
    if (stripos($rec, 'v=spf1') !== 0) {
        $E[] = 'SPF must begin exactly with "v=spf1".';
    }
    $terms   = preg_split('/\s+/', trim($rec));
    $lookups = 0;
    $allSeen = false;
    $afterAll = false;

    foreach ($terms as $i => $term) {
        if ($term === '' || stripos($term, 'v=spf1') === 0) { continue; }
        if ($allSeen) { $afterAll = true; }

        $t = $term;
        if (strlen($t) && strpos('+-~?', $t[0]) !== false) { $t = substr($t, 1); }
        $lower = strtolower($t);

        if ($lower === 'all') { $allSeen = true; continue; }
        if ($lower === 'a' || strpos($lower, 'a:') === 0)   { $lookups++; continue; }
        if ($lower === 'mx' || strpos($lower, 'mx:') === 0) { $lookups++; continue; }
        if (strpos($lower, 'include:') === 0)  { $lookups++; continue; }
        if (strpos($lower, 'exists:') === 0)   { $lookups++; continue; }
        if (strpos($lower, 'redirect=') === 0) { $lookups++; continue; }
        if (strpos($lower, 'ptr') === 0) {
            $lookups++;
            $E[] = 'SPF uses the deprecated "ptr" mechanism (RFC 7208 §5.5). Remove it.';
            continue;
        }
        if (strpos($lower, 'ip4:') === 0) {
            $val  = substr($t, 4);
            $bare = preg_replace('#/(\d+)$#', '', $val);
            $mask = null;
            if (preg_match('#/(\d+)$#', $val, $mm)) { $mask = (int) $mm[1]; }
            if (filter_var($bare, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                $E[] = "SPF: \"{$val}\" is not a valid IPv4 address.";
            } elseif ($mask !== null && ($mask < 0 || $mask > 32)) {
                $E[] = "SPF: \"{$val}\" has an invalid IPv4 prefix length.";
            } elseif ($mask !== null && $mask < 24) {
                $W[] = "SPF: {$val} authorizes " . number_format(pow(2, 32 - $mask))
                     . ' addresses. Narrow it unless you genuinely control that whole block.';
            }
            continue;
        }
        if (strpos($lower, 'ip6:') === 0) {
            $bare = preg_replace('#/(\d+)$#', '', substr($t, 4));
            if (filter_var($bare, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                $E[] = "SPF: \"" . substr($t, 4) . "\" is not a valid IPv6 address.";
            }
            continue;
        }
        $E[] = "SPF: unrecognised term \"{$term}\". Typo, or a mechanism no receiver will understand.";
    }

    echo "dns-lookup mechanisms: {$lookups} of 10\n";
    if ($lookups > 10) {
        $E[] = "SPF: {$lookups} DNS-lookup mechanisms exceeds the RFC 7208 limit of 10 (permerror). "
             . 'Note: nested include: chains are not followed here, so the true count may be higher.';
    } else {
        $O[] = "Lookup count {$lookups}/10 (direct terms only — include: chains not expanded).";
    }
    if (!$allSeen) {
        $E[] = 'SPF: no "all" mechanism. Unlisted senders get a neutral result and the record '
             . 'authorizes nothing in practice.';
    }
    if ($afterAll) {
        $W[] = 'SPF: terms appear AFTER the "all" mechanism. Evaluation stops at "all", so those '
             . 'terms are dead. Move them before it.';
    }
    if (strpos(strtolower($rec), '+all') !== false) {
        $E[] = 'SPF: "+all" authorizes the entire internet to send as this domain.';
    }
}

// ---------------------------------------------------------------- DMARC ----

if ($kind === 'dmarc') {
    if (stripos($rec, 'v=DMARC1') !== 0) {
        $E[] = 'DMARC must begin exactly with "v=DMARC1" (the v tag must come first).';
    }
    $tags = array();
    foreach (explode(';', $rec) as $part) {
        $part = trim($part);
        if ($part === '') { continue; }
        if (strpos($part, '=') === false) {
            $E[] = "DMARC: fragment \"{$part}\" is not a tag=value pair.";
            continue;
        }
        $kv = explode('=', $part, 2);
        $k  = strtolower(trim($kv[0]));
        $v  = trim($kv[1]);
        if (isset($tags[$k])) {
            $E[] = "DMARC: DUPLICATE TAG \"{$k}\" (first \"{$tags[$k]}\", again \"{$v}\"). "
                 . 'RFC 7489 §6.4 — a conforming verifier MUST ignore a record with a syntax error, '
                 . 'discarding the policy AND the rua reporting.';
        } else {
            $tags[$k] = $v;
        }
    }

    if (!isset($tags['p'])) {
        $E[] = 'DMARC: missing required p= tag.';
    } elseif (!in_array(strtolower($tags['p']), array('none', 'quarantine', 'reject'), true)) {
        $E[] = 'DMARC: p="' . $tags['p'] . '" is not one of none|quarantine|reject.';
    } else {
        $O[] = 'p=' . strtolower($tags['p']) . ' is valid.';
    }

    if (isset($tags['sp']) && !in_array(strtolower($tags['sp']), array('none', 'quarantine', 'reject'), true)) {
        $E[] = 'DMARC: sp="' . $tags['sp'] . '" is not one of none|quarantine|reject.';
    }
    if (isset($tags['pct'])) {
        if (!ctype_digit($tags['pct']) || (int) $tags['pct'] < 0 || (int) $tags['pct'] > 100) {
            $E[] = 'DMARC: pct="' . $tags['pct'] . '" must be an integer 0-100.';
        }
    }
    if (isset($tags['fo'])) {
        foreach (explode(':', $tags['fo']) as $f) {
            if (!in_array(trim($f), array('0', '1', 'd', 's'), true)) {
                $E[] = 'DMARC: fo value "' . $f . '" is not one of 0, 1, d, s.';
            }
        }
    }
    foreach (array('adkim', 'aspf') as $al) {
        if (isset($tags[$al]) && !in_array(strtolower($tags[$al]), array('r', 's'), true)) {
            $E[] = "DMARC: {$al}=\"" . $tags[$al] . '" must be r (relaxed) or s (strict).';
        }
    }
    if (isset($tags['ri']) && !ctype_digit($tags['ri'])) {
        $E[] = 'DMARC: ri="' . $tags['ri'] . '" must be an integer number of seconds.';
    }

    foreach (array('rua', 'ruf') as $rt) {
        if (!isset($tags[$rt]) || $tags[$rt] === '') { continue; }
        foreach (explode(',', $tags[$rt]) as $uri) {
            $uri = trim($uri);
            if (stripos($uri, 'mailto:') !== 0) {
                $E[] = "DMARC: {$rt} entry \"{$uri}\" must start with mailto:";
                continue;
            }
            $addr = preg_replace('/!\d+[kmgt]?$/i', '', substr($uri, 7));
            if (filter_var($addr, FILTER_VALIDATE_EMAIL) === false) {
                $E[] = "DMARC: {$rt} address \"{$addr}\" is not a valid email address.";
            }
        }
    }

    if (!isset($tags['rua']) || $tags['rua'] === '') {
        $E[] = 'DMARC: no rua= address. Without it you get a policy and no visibility — '
             . 'the reports are the whole point of starting at p=none.';
    } else {
        $O[] = 'rua present — aggregate reports will be sent.';
    }
    if (isset($tags['p']) && strtolower($tags['p']) === 'none') {
        $W[] = 'p=none is monitoring only. Correct as a starting point, not as a destination.';
    }
    if (isset($tags['ruf'])) {
        $W[] = 'ruf= forensic reports can carry headers and content of failing messages. '
             . 'Keep only if you control and trust the destination.';
    }

    echo 'tags: ' . implode(', ', array_keys($tags)) . "\n";
}

// --------------------------------------------------------------- verdict ---

echo "\n" . str_repeat('-', 70) . "\n";
foreach ($O as $m) { echo "  OK    {$m}\n"; }
foreach ($W as $m) { echo "  WARN  {$m}\n"; }
foreach ($E as $m) { echo "  ERROR {$m}\n"; }
echo str_repeat('-', 70) . "\n";
printf("VERDICT: %d error(s), %d warning(s).\n", count($E), count($W));
if ($E) { echo "Exit 2 — do NOT paste this record.\n"; exit(2); }
if ($W) { echo "Exit 1 — valid, but read the warnings first.\n"; exit(1); }
echo "Exit 0 — clean.\n";
exit(0);
