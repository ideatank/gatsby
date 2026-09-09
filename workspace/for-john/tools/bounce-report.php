<?php
/**
 * bounce-report.php — profile JD Mail's mail.log and extract the bounce record.
 *
 * READ ONLY. Opens mail.log for reading. Never writes, truncates or rotates it.
 * No network traffic of any kind.
 *
 * The on-disk format of mail.log is not documented in jd-mail-bible beyond
 * "append-only event log: gate attempts, sends, bounces, unsubs". This script
 * therefore PROFILES the file first — line count, date span, the distinct
 * event tokens actually present — and only then counts. If it finds no bounce
 * events it says UNMEASURED, not zero. An absent record is not a clean record.
 *
 * Usage:
 *   php bounce-report.php /home/johndel/mail-data/mail.log
 *   php bounce-report.php /home/johndel/mail-data/mail.log --sample=30
 *
 * PHP 7.0+.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit("CLI only.\n");
}

$path   = isset($argv[1]) ? $argv[1] : '';
$sample = 12;
foreach ($argv as $a) {
    if (strpos($a, '--sample=') === 0) {
        $sample = max(0, (int) substr($a, 9));
    }
}

if ($path === '') {
    fwrite(STDERR, "Usage: php bounce-report.php <path-to-mail.log> [--sample=N]\n");
    exit(2);
}
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "FATAL: not a readable file: {$path}\n");
    fwrite(STDERR, "If mail.log does not exist, that is itself the finding:\n");
    fwrite(STDERR, "  no log => no send record => bounce rate is UNMEASURED.\n");
    exit(2);
}

$fh = fopen($path, 'rb');
if ($fh === false) {
    fwrite(STDERR, "FATAL: could not open {$path}\n");
    exit(2);
}

// Token families. Deliberately broad — we are discovering the format, not
// asserting it. A line can match more than one family; each is counted once.
$FAMILIES = array(
    'hard_bounce' => array('hard bounce', 'hardbounce', 'bounce_hard', 'permanent failure',
                           '550', '551', '553', '554', 'user unknown', 'no such user',
                           'mailbox unavailable', 'does not exist', 'nxdomain'),
    'soft_bounce' => array('soft bounce', 'softbounce', 'bounce_soft', 'temporary failure',
                           '421', '450', '451', '452', 'try again', 'mailbox full',
                           'over quota', 'greylist', 'deferred', 'throttl'),
    'bounce_any'  => array('bounce'),
    'send_ok'     => array('sent', 'send_ok', 'delivered', 'queued', 'mail_sent'),
    'send_fail'   => array('send_fail', 'failed', 'failure', 'error', 'mail() returned false',
                           'mail_failed'),
    'unsub'       => array('unsub', 'unsubscribe', 'opt-out', 'optout'),
    'open'        => array('open', 'pixel'),
    'click'       => array('click'),
    'gate'        => array('gate', 'magic', 'login', 'token'),
);

$counts   = array_fill_keys(array_keys($FAMILIES), 0);
$samples  = array_fill_keys(array_keys($FAMILIES), array());
$campaign = array();       // campaign id => array(family => n)
$lines = 0; $blank = 0; $bytes = 0;
$firstTs = null; $lastTs = null;
$tokenTally = array();     // leading bracketed/first-field token frequency

while (($line = fgets($fh)) !== false) {
    $bytes += strlen($line);
    $line = rtrim($line, "\r\n");
    if (trim($line) === '') { $blank++; continue; }
    $lines++;
    $low = strtolower($line);

    // timestamp: first ISO-ish or [Y-m-d H:i:s] token
    if (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $m)) {
        $ts = strtotime($m[1]);
        if ($ts !== false) {
            if ($firstTs === null || $ts < $firstTs) { $firstTs = $ts; }
            if ($lastTs  === null || $ts > $lastTs)  { $lastTs  = $ts; }
        }
    }

    // crude event-token discovery: bracketed tokens and UPPER_SNAKE words
    if (preg_match_all('/\[([A-Za-z_][A-Za-z0-9_.-]{1,24})\]/', $line, $mm)) {
        foreach ($mm[1] as $t) {
            $t = strtolower($t);
            $tokenTally[$t] = isset($tokenTally[$t]) ? $tokenTally[$t] + 1 : 1;
        }
    }

    $cid = null;
    if (preg_match('/\b(?:campaign|cid|c)[=:_-]([A-Za-z0-9_.-]{3,40})/i', $line, $cm)) {
        $cid = $cm[1];
    }

    foreach ($FAMILIES as $fam => $needles) {
        foreach ($needles as $n) {
            if (strpos($low, $n) !== false) {
                $counts[$fam]++;
                if (count($samples[$fam]) < $sample) {
                    $samples[$fam][] = $line;
                }
                if ($cid !== null) {
                    if (!isset($campaign[$cid])) {
                        $campaign[$cid] = array_fill_keys(array_keys($FAMILIES), 0);
                    }
                    $campaign[$cid][$fam]++;
                }
                break;
            }
        }
    }
}
fclose($fh);

$rule = str_repeat('-', 68);

echo "JD MAIL — SEND / BOUNCE RECORD\n";
echo "generated : " . date('Y-m-d H:i:s T') . "\n";
echo "source    : {$path}\n";
echo "size      : " . number_format($bytes) . " bytes\n";
echo "lines     : {$lines} non-blank ({$blank} blank)\n";
echo "date span : " . ($firstTs ? date('Y-m-d H:i:s', $firstTs) : 'no parseable timestamp')
   . " .. " . ($lastTs ? date('Y-m-d H:i:s', $lastTs) : 'n/a') . "\n";
echo "MUTATIONS : none. Opened read-only.\n";
echo $rule . "\n\n";

echo "EVENT TOKENS DISCOVERED (bracketed, top 25)\n";
if ($tokenTally) {
    arsort($tokenTally);
    $i = 0;
    foreach ($tokenTally as $t => $n) {
        printf("  %-28s %6d\n", $t, $n);
        if (++$i >= 25) { break; }
    }
} else {
    echo "  (none — log does not use [TOKEN] event markers)\n";
}

echo "\n" . $rule . "\n\nLINE COUNTS BY FAMILY (keyword match, not authoritative parsing)\n";
foreach ($counts as $fam => $n) {
    printf("  %-14s %6d\n", $fam, $n);
}

echo "\n" . $rule . "\n\nBOUNCE RATE\n";
$attempted = $counts['send_ok'] + $counts['send_fail'];
$hard      = $counts['hard_bounce'];
$soft      = $counts['soft_bounce'];
$anyBounce = $counts['bounce_any'] + $hard + $soft;

if ($lines === 0) {
    echo "  UNMEASURED — the log is empty. No sends recorded.\n";
} elseif ($anyBounce === 0 && $attempted === 0) {
    echo "  UNMEASURED — no send events and no bounce events found in this log.\n";
    echo "  This is NOT a 0% bounce rate. It means the sender has never run,\n";
    echo "  or the sender does not record delivery outcomes at all.\n";
} elseif ($anyBounce === 0 && $attempted > 0) {
    echo "  attempted (send_ok + send_fail) : {$attempted}\n";
    echo "  hard bounces recorded           : 0\n";
    echo "  soft bounces recorded           : 0\n";
    echo "  UNMEASURED — sends are logged but no bounce outcome is ever written.\n";
    echo "  Bounce detection is unimplemented, so 0 here means 'not recorded',\n";
    echo "  not 'no bounces occurred'.\n";
} else {
    printf("  attempted (send_ok + send_fail) : %d\n", $attempted);
    printf("  hard bounces                    : %d\n", $hard);
    printf("  soft bounces                    : %d\n", $soft);
    if ($attempted > 0) {
        printf("  HARD BOUNCE RATE                : %.2f%%\n", ($hard / $attempted) * 100);
        printf("  soft bounce rate                : %.2f%%\n", ($soft / $attempted) * 100);
        echo "\n  Reference: mailbox providers begin throttling above ~2% hard bounce;\n";
        echo "  above ~5% is a reputation emergency.\n";
    } else {
        echo "  HARD BOUNCE RATE                : undefined (no attempts recorded)\n";
    }
}

if ($campaign) {
    echo "\n" . $rule . "\n\nPER-CAMPAIGN\n";
    foreach ($campaign as $cid => $c) {
        $att = $c['send_ok'] + $c['send_fail'];
        printf("  %-24s attempted=%-6d hard=%-5d soft=%-5d rate=%s\n",
            $cid, $att, $c['hard_bounce'], $c['soft_bounce'],
            $att > 0 ? sprintf('%.2f%%', ($c['hard_bounce'] / $att) * 100) : 'n/a');
    }
}

if ($sample > 0) {
    echo "\n" . $rule . "\n\nSAMPLE LINES (to verify the keyword matching is honest)\n";
    foreach ($samples as $fam => $rows) {
        if (!$rows) { continue; }
        echo "\n  [{$fam}]\n";
        foreach ($rows as $r) {
            echo "    " . (strlen($r) > 200 ? substr($r, 0, 200) . ' ...' : $r) . "\n";
        }
    }
}

echo "\n" . $rule . "\n";
echo "END OF REPORT. Keyword matching is a discovery tool. Once you confirm the\n";
echo "real log format from the samples above, tighten FAMILIES to exact tokens.\n";
