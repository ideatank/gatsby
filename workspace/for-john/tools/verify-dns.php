<?php
/**
 * verify-dns.php — assert the state of SPF, DKIM and DMARC for a domain.
 *
 * READ ONLY. DNS queries only. No SMTP, no HTTP, no filesystem writes.
 *
 * Run it BEFORE a zone edit to capture the baseline, and AFTER to prove the
 * change landed. DNS is cached — allow for the zone's TTL before trusting an
 * "after" run, and re-run until it flips.
 *
 * Usage:
 *   php verify-dns.php johndelavera.com
 *   php verify-dns.php johndelavera.com --selector=default
 *
 * Exit codes are conclusions, not decoration:
 *   0 = clean          — no errors, no warnings
 *   1 = warnings only  — valid records, but posture worth improving
 *   2 = errors         — a record is missing, malformed, or self-contradictory
 *
 * PHP 7.0+.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only.\n");
}

$domain   = isset($argv[1]) ? strtolower(trim($argv[1])) : '';
$selector = 'default';
foreach ($argv as $a) {
    if (strpos($a, '--selector=') === 0) {
        $selector = substr($a, 11);
    }
}
if ($domain === '') {
    fwrite(STDERR, "Usage: php verify-dns.php <domain> [--selector=NAME]\n");
    exit(2);
}

$ERRORS   = array();
$WARNINGS = array();
$OK       = array();

function err($m)  { global $ERRORS;   $ERRORS[]   = $m; }
function warn($m) { global $WARNINGS; $WARNINGS[] = $m; }
function ok($m)   { global $OK;       $OK[]       = $m; }

function txt_records($name) {
    $r = @dns_get_record($name, DNS_TXT);
    if (!$r) { return array(); }
    $out = array();
    foreach ($r as $x) {
        // Long TXT records arrive split into 255-byte strings; dns_get_record
        // exposes both the joined 'txt' and the parts in 'entries'.
        if (isset($x['entries']) && is_array($x['entries'])) {
            $out[] = implode('', $x['entries']);
        } elseif (isset($x['txt'])) {
            $out[] = $x['txt'];
        }
    }
    return $out;
}

$rule = str_repeat('=', 70);
echo "DNS AUTHENTICATION VERIFY — {$domain}\n";
echo "run at : " . date('Y-m-d H:i:s T') . "\n";
echo "method : dns_get_record() — DNS only, no SMTP\n";
echo $rule . "\n";

// ------------------------------------------------------------------- SPF ---

echo "\n[SPF] {$domain} TXT\n";
$all  = txt_records($domain);
$spfs = array();
foreach ($all as $t) {
    if (stripos($t, 'v=spf1') === 0) { $spfs[] = $t; }
}

if (!$spfs) {
    err('SPF: no v=spf1 record found.');
    echo "  (none)\n";
} elseif (count($spfs) > 1) {
    err('SPF: ' . count($spfs) . ' v=spf1 records published. RFC 7208 permits exactly one; '
      . 'multiple records are a permerror and SPF fails outright.');
    foreach ($spfs as $s) { echo "  {$s}\n"; }
} else {
    $spf = $spfs[0];
    echo "  {$spf}\n\n";

    $terms   = preg_split('/\s+/', trim($spf));
    $lookups = 0;
    $ips     = array();
    $allQual = null;
    $sawPtr  = false;
    $sawA    = false;
    $sawMx   = false;

    foreach ($terms as $term) {
        if ($term === '' || stripos($term, 'v=spf1') === 0) { continue; }
        $qual = '+';
        $t    = $term;
        if (strlen($t) && strpos('+-~?', $t[0]) !== false) {
            $qual = $t[0];
            $t    = substr($t, 1);
        }
        $lower = strtolower($t);

        if ($lower === 'all') { $allQual = $qual; continue; }
        if ($lower === 'a'  || strpos($lower, 'a:')  === 0) { $lookups++; $sawA  = true; continue; }
        if ($lower === 'mx' || strpos($lower, 'mx:') === 0) { $lookups++; $sawMx = true; continue; }
        if (strpos($lower, 'include:')  === 0) { $lookups++; continue; }
        if (strpos($lower, 'exists:')   === 0) { $lookups++; continue; }
        if (strpos($lower, 'redirect=') === 0) { $lookups++; continue; }
        if (strpos($lower, 'ptr') === 0)       { $lookups++; $sawPtr = true; continue; }
        if (strpos($lower, 'ip4:') === 0) { $ips[] = substr($t, 4); continue; }
        if (strpos($lower, 'ip6:') === 0) { $ips[] = substr($t, 4); continue; }
    }

    printf("  DNS-lookup mechanisms : %d of 10 permitted\n", $lookups);
    if ($lookups > 10) {
        err("SPF: {$lookups} DNS-lookup mechanisms exceeds the RFC 7208 limit of 10 — permerror.");
    } else {
        ok("SPF lookup count {$lookups}/10.");
    }
    echo "  (nested include: chains are NOT followed by this script — the count above\n";
    echo "   is exact only when there are no include: terms.)\n";

    echo "  authorized addresses  : " . (count($ips) ? implode(', ', $ips) : '(none listed literally)') . "\n";
    foreach ($ips as $ip) {
        $bare = preg_replace('#/\d+$#', '', $ip);
        $ptr  = @gethostbyaddr($bare);
        printf("      %-18s PTR %s\n", $ip, ($ptr && $ptr !== $bare) ? $ptr : '(no reverse DNS)');
    }

    echo "  all mechanism         : " . ($allQual === null ? 'MISSING' : $allQual . 'all') . "\n";
    if ($allQual === null) {
        err('SPF: no "all" mechanism. Unlisted senders get a neutral result — the record authorizes nothing.');
    } elseif ($allQual === '+') {
        err('SPF: "+all" authorizes the entire internet to send as this domain. Remove immediately.');
    } elseif ($allQual === '?') {
        warn('SPF: "?all" (neutral) gives receivers no signal. Use ~all or -all.');
    } elseif ($allQual === '~') {
        ok('SPF ends in ~all (softfail) — correct while sender inventory is still being confirmed.');
    } elseif ($allQual === '-') {
        ok('SPF ends in -all (hardfail) — strictest. Only correct once every sender is enumerated.');
    }

    if ($sawPtr) {
        err('SPF: the "ptr" mechanism is deprecated by RFC 7208 and ignored or penalised by receivers. Remove it.');
    }
    if ($sawA) {
        warn('SPF: "a" authorizes whatever the web A record points at. If the site ever moves behind '
           . 'a CDN or shared proxy, those shared IPs gain the right to send as this domain. '
           . 'Drop it if the mail host is already covered by ip4: or mx.');
    }
    if ($sawMx) {
        ok('SPF includes "mx" — self-maintaining if the mail host IP changes.');
    }
}

// ------------------------------------------------------------------ DKIM ---

$dkName = $selector . '._domainkey.' . $domain;
echo "\n[DKIM] {$dkName} TXT\n";
$dk = txt_records($dkName);
if (!$dk) {
    err("DKIM: no record at {$dkName}. Mail from this domain is unsigned, or signed with a different selector.");
    echo "  (none)\n";
} else {
    foreach ($dk as $d) {
        echo "  " . (strlen($d) > 120 ? substr($d, 0, 120) . ' ...[' . strlen($d) . ' bytes]' : $d) . "\n";
        if (stripos($d, 'v=DKIM1') === false) {
            warn("DKIM: record at {$dkName} does not begin with v=DKIM1.");
        }
        if (preg_match('/(^|;)\s*p=\s*;/', $d) || preg_match('/(^|;)\s*p=\s*$/', $d)) {
            err('DKIM: empty p= tag — the key has been REVOKED. Signatures will not verify.');
            continue;
        }
        if (preg_match('/p=([A-Za-z0-9+\/=]+)/', $d, $m)) {
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($m[1], 64, "\n") . "-----END PUBLIC KEY-----\n";
            $k   = @openssl_pkey_get_public($pem);
            if ($k === false) {
                warn('DKIM: public key present but could not be parsed by openssl. Check for truncation.');
            } else {
                $det  = openssl_pkey_get_details($k);
                $bits = isset($det['bits']) ? $det['bits'] : 0;
                echo "  key size: {$bits} bits\n";
                if ($bits < 1024) {
                    err("DKIM: {$bits}-bit key is below the 1024-bit floor and will be rejected.");
                } elseif ($bits < 2048) {
                    warn("DKIM: {$bits}-bit key. Several providers now downgrade sub-2048 keys. Rotate to 2048.");
                } else {
                    ok("DKIM key {$bits}-bit at selector \"{$selector}\".");
                }
            }
        }
    }
}

// ----------------------------------------------------------------- DMARC ---

$dmName = '_dmarc.' . $domain;
echo "\n[DMARC] {$dmName} TXT\n";
$dm = array();
foreach (txt_records($dmName) as $t) {
    if (stripos($t, 'v=DMARC1') === 0) { $dm[] = $t; }
}

if (!$dm) {
    err("DMARC: no record at {$dmName}. No policy, and no aggregate reporting.");
    echo "  (none)\n";
} elseif (count($dm) > 1) {
    err('DMARC: ' . count($dm) . ' v=DMARC1 records published. RFC 7489 requires exactly one; '
      . 'receivers treat multiple as no policy at all.');
} else {
    $rec = $dm[0];
    echo "  {$rec}\n\n";

    $tags = array();
    $dupe = array();
    foreach (explode(';', $rec) as $part) {
        $part = trim($part);
        if ($part === '') { continue; }
        $kv = explode('=', $part, 2);
        $k  = strtolower(trim($kv[0]));
        $v  = isset($kv[1]) ? trim($kv[1]) : '';
        if (isset($tags[$k])) {
            $dupe[$k] = true;
            echo "  DUPLICATE: {$k} seen again with value \"{$v}\" (first was \"{$tags[$k]}\")\n";
        } else {
            $tags[$k] = $v;
        }
    }

    if ($dupe) {
        err('DMARC: duplicate tag(s) [' . implode(', ', array_keys($dupe)) . ']. RFC 7489 §6.4 requires '
          . 'each tag once; a record with a syntax error MUST be ignored by a conforming verifier — '
          . 'which silently discards the entire policy AND the rua reporting.');
    } else {
        ok('DMARC record has no duplicate tags.');
    }

    $p   = isset($tags['p'])   ? strtolower($tags['p'])  : null;
    $sp  = isset($tags['sp'])  ? strtolower($tags['sp']) : null;
    $pct = isset($tags['pct']) ? $tags['pct'] : '100';

    printf("  p    : %s\n", $p === null ? 'MISSING' : $p);
    printf("  sp   : %s\n", $sp === null ? '(inherits p)' : $sp);
    printf("  pct  : %s\n", $pct);
    printf("  fo   : %s\n", isset($tags['fo']) ? $tags['fo'] : '(default 0)');
    printf("  adkim: %s   aspf: %s\n",
        isset($tags['adkim']) ? $tags['adkim'] : 'r (default)',
        isset($tags['aspf'])  ? $tags['aspf']  : 'r (default)');

    if ($p === null) {
        err('DMARC: no p= tag. The record is invalid.');
    } elseif ($p === 'none') {
        warn('DMARC: p=none — monitoring only. Nothing is ever quarantined or rejected on your behalf. '
           . 'Correct while building sender inventory; not a destination.');
    } elseif ($p === 'quarantine') {
        ok('DMARC p=quarantine — failing mail is filtered.');
    } elseif ($p === 'reject') {
        ok('DMARC p=reject — failing mail is refused. Strongest posture.');
    }

    if ($sp !== null && $p !== null && $sp === 'none' && $p !== 'none') {
        warn('DMARC: sp=none while p=' . $p . ' — subdomains are left unprotected and become the '
           . 'spoofing path of least resistance. Match sp to p, or remove sp so it inherits.');
    }

    if (isset($tags['rua']) && $tags['rua'] !== '') {
        $n = count(explode(',', $tags['rua']));
        ok("DMARC rua present ({$n} destination" . ($n === 1 ? '' : 's') . ") — aggregate reports are being collected.");
        echo "  rua  : " . $tags['rua'] . "\n";
        warn('DMARC: rua is published, but that only proves reports are SENT. Confirm you can actually '
           . 'OPEN one and find your own sending IP in it. Collected-but-unread is the same as absent.');
    } else {
        err('DMARC: no rua= address. Aggregate reports are the only free instrument that tells you '
          . 'whether your mail authenticates in the wild. Add one.');
    }

    if (isset($tags['ruf']) && $tags['ruf'] !== '') {
        echo "  ruf  : " . $tags['ruf'] . "\n";
        warn('DMARC: ruf= forensic reports can include headers and content of failing messages, sent to '
           . 'whatever address is listed. Most large providers never send them. Keep it only if the '
           . 'destination is one you control and trust.');
    }
}

// ---------------------------------------------------------------- verdict ---

echo "\n" . $rule . "\n";
foreach ($OK as $m)       { echo "  OK    {$m}\n"; }
foreach ($WARNINGS as $m) { echo "  WARN  {$m}\n"; }
foreach ($ERRORS as $m)   { echo "  ERROR {$m}\n"; }

echo "\n" . $rule . "\n";
printf("VERDICT: %d error(s), %d warning(s).\n", count($ERRORS), count($WARNINGS));
if ($ERRORS) {
    echo "Exit 2 — at least one record is missing, malformed, or self-contradictory.\n";
    exit(2);
}
if ($WARNINGS) {
    echo "Exit 1 — records are valid; posture can be improved.\n";
    exit(1);
}
echo "Exit 0 — clean.\n";
exit(0);
