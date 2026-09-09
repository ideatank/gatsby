# JD Mail — List Hygiene & Authentication Audit

**Date:** 2026-09-09
**Scope:** read-only. No SMTP connection was opened to any host, at any point.
**Branch:** `claude/jd-mail-hygiene-audit-clc6ob`

---

## Summary — read this and nothing else

| # | Task | Status | Result |
|---|------|--------|--------|
| 1 | Bounce record from `mail.log` | **NOT RUN** | The file is not reachable from this environment. Tool written and tested; run it on the box. |
| 2 | Offline hygiene sweep of `subscribers.json` | **NOT RUN** | Same. Tool written and tested; run it on the box. |
| 3 | SPF / DKIM / DMARC | **DONE — real measurements** | All three exist. `rua=` **is present**. Three concrete defects found. |

**The headline you asked about:** `rua=` is not missing. You are not flying blind — you have the
instrument. Whether anyone is *reading* it is a different question, and it is now the open one
(see Finding 3).

---

## Why Tasks 1 and 2 could not be executed

This session runs in an ephemeral cloud container whose only checkout is
`ideatank/gatsby` — a stock Gatsby starter (`src/{components,images,pages}`,
`gatsby-config.js`, `package.json`). It is not the JD Mail host and has no
connection to it.

Verified absent:

```
$ for p in /home/johndel /home/johndel/mail-data /home/johndel/bitrs-data; do
    [ -e "$p" ] && echo "EXISTS: $p" || echo "MISSING: $p"; done
MISSING: /home/johndel
MISSING: /home/johndel/mail-data
MISSING: /home/johndel/bitrs-data

$ find / -name 'subscribers.json' -o -name 'mail.log' 2>/dev/null
(no results)

$ git ls-files | grep -iE 'mail|subscri|campaign'
(none)
```

So `mail.log` and `bitrs-data/subscribers.json` cannot be read from here. Anything I reported
about their contents would be invented. I am not going to invent it.

### What the bible implies about Task 1 — flagged as inference, not measurement

`jd-mail-bible` lists under **What Needs Building**:

> ### 1. `send-cron.php` — throttled batch sender
> [...] Bounce detection: check `mail()` return value, log failures to `mail.log`

If that is still accurate, then **`send-cron.php` does not exist, no campaign has ever been sent
through JD Mail, and bounce detection has never been written.** In that case the hard-bounce rate
is not low — it is **UNMEASURED**, and it will stay unmeasured through your first real campaign
unless bounce logging ships *with* the sender rather than after it.

This is inference from a bible dated 2026-04-27. **Confirm it against the box before acting on it.**

Two further points that matter more than the number:

1. `mail()` return value is a **weak** bounce signal. It tells you the message was handed to the
   local MTA, not that it was delivered. Real hard bounces arrive asynchronously as DSNs in the
   `Return-Path` mailbox, minutes to hours later. A sender that only checks `mail()` will record a
   ~0% bounce rate on a list that is 30% dead. **Design the bounce path around a monitored
   `Return-Path:` mailbox, not around the `mail()` return value.** This is the single most
   important thing in this document.
2. The list was loaded from AWeber at 441 subscribers. AWeber was suppressing its own bounces
   silently. Those suppressions almost certainly did **not** come across in the export — meaning
   your 441 includes addresses AWeber had already stopped mailing. Your first send is the first
   time those get touched again, all at once, from a brand-new sending reputation. That is the
   textbook way to burn a domain.

---

## Task 3 — Authentication records (MEASURED)

Method: `dns_get_record()` over the container's resolver. Raw output in
`evidence/dns-2026-09-09.txt`.

### SPF — `johndelavera.com` TXT

```
v=spf1 ip4:50.28.87.135 +a +mx +ip4:169.60.154.200 +ip4:206.221.182.218 +ip4:75.126.204.26 ~all
```

- Syntactically valid. DNS-lookup cost is 2 (`a`, `mx`) — far inside the RFC 7208 limit of 10. No risk of `permerror`.
- `50.28.87.135` is both the `A` record for `johndelavera.com` and the `A` for `mail.johndelavera.com` (the sole `MX`, priority 10). That is the cPanel box that actually sends. Correct and authorised.
- **The other three IPs are not your mail host** (see Finding 2).

### DKIM — `default._domainkey.johndelavera.com` TXT

```
v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvywa9/CRQ6FSRX5iFT3RUy5Ewh5czvBNfCuSA
hIKsAp0jbvEkI+4066Xk890pRSUMosJU4T3lw0MgLSTxzYGtG/o8bXPwyddHIsUTlGYB3zzbixXqt9qQmyvjERfycBGRN6CW/E
HosxU+kLupEtFZ+LDFoONUNuVNyjJ+ikQ2N+FfUfr/JG2/CIDU7NGgM7UwN0grNOdd0sxngUnAfT4OoTVgJ1yhYCnCjYfOu1NQ
egKobATmctbbqAk1xezYyqeolMgFUFHtGWBd9VluRRou0ZS6f5iJt/j/PriN2FU94wb70fHjD1f8d2zpJRnt5l3QptqXDHn9Dm
DSwIfDokBlwIDAQAB;
```

- **Selector: `default`** — the cPanel/Exim default. This is the selector JD Mail signs with, because JD Mail sends through PHP `mail()`, which hands off to Exim, which signs with cPanel's key. You do not have a separate application selector, and you do not need one.
- Key parsed cleanly. **RSA 2048-bit.** Correct — 1024 is now rejected or downgraded by several providers.
- Probed and found absent: `mail`, `dkim`, `k1`, `s1`, `s2`. No stale duplicate selectors, which is good.

### DMARC — `_dmarc.johndelavera.com` TXT

```
v=DMARC1;p=none;sp=none;adkim=r;aspf=r;pct=100;fo=0;rf=afrf;ri=86400;
rua=mailto:bd154549761d.a@dmarcinput.com,mailto:dmarc-contact@johndelavera.com;
ruf=mailto:bd154549761d.f@dmarcinput.com; fo=1
```

| Tag | Value | Reading |
|-----|-------|---------|
| `p` | `none` | Monitor only. Nothing is ever quarantined or rejected on your behalf. |
| `sp` | `none` | Subdomains likewise unprotected. |
| `adkim` / `aspf` | `r` / `r` | Relaxed alignment. Fine. |
| `pct` | `100` | Policy would apply to all mail — but the policy is `none`. |
| `rua` | 2 addresses | **Aggregate reports ARE being collected.** |
| `ruf` | 1 address | Forensic reports too. |
| `ri` | `86400` | Daily aggregate reports. Standard. |
| `fo` | **`0` and `1`** | **Defect — see Finding 1.** |

---

## Findings, ranked

### Finding 1 — the DMARC record has a duplicate `fo` tag (real defect, cheap fix)

`fo` appears twice: `fo=0` near the front and `fo=1` at the very end.

```
TAG COUNTS: v=1 p=1 sp=1 adkim=1 aspf=1 pct=1 fo=2 rf=1 ri=1 rua=1 ruf=1
DUPLICATE TAG: fo appears 2 times
```

RFC 7489 §6.4 requires each tag to appear once; a record with syntax errors "MUST be ignored" by
a conforming verifier. In practice parsers diverge — some take the first, some the last, some
discard the whole record. The two values also contradict each other: `fo=0` means "report only if
*both* SPF and DKIM fail", `fo=1` means "report if *either* fails".

**Risk:** a strict parser discards your entire DMARC record, and you silently lose the reporting you
think you have. This is not theoretical — it is exactly the failure that makes people believe DMARC
is working when it is not.

**Fix:** delete one. Keep `fo=1` (report on any failure — more signal while you are migrating off
AWeber and standing up a new sender) and remove the `fo=0`. One tag edit in cPanel's DNS zone editor.

### Finding 2 — three IPs in SPF are not your mail host and are probably stale AWeber-era authorisations

```
169.60.154.200
206.221.182.218
75.126.204.26
```

None is `50.28.87.135`. Each of these is a standing authorisation for *someone* to send mail as
`johndelavera.com` and pass SPF. While AWeber is live that is correct and necessary. The moment you
cut over to JD Mail, they become an attack surface: whoever holds those IPs next can send SPF-passing
mail as you.

**This is a migration-completion task, not an emergency.** But put it on the cutover checklist now,
because it is exactly the kind of thing that never gets done later. Identify each one before you
remove it — do not blind-delete, or you will break AWeber mid-migration.

Also worth noting: `+a +mx` authorises the web server itself. If anything on that shared host is ever
compromised, it can send SPF-passing mail as you. Tightening `+a +mx` to explicit `ip4:` entries is a
defensible hardening step once the sender is stable.

### Finding 3 — the reporting address you can actually read is the one that matters

`rua=` points at two places:

- `bd154549761d.a@dmarcinput.com` — a third-party DMARC processor. That hashed local-part is an
  account identifier. **Do you still have login access to that dashboard?** If it was set up during
  a trial, or by a host, or years ago, the reports are being collected by a service you cannot read.
  That is functionally identical to having no `rua` at all, which is what you suspected.
- `dmarc-contact@johndelavera.com` — your own mailbox. **Is anyone reading it?** Aggregate reports
  are gzipped XML; they are unreadable by eye. If they are landing in a mailbox nobody opens, same
  outcome.

**Action:** before the first JD Mail campaign, confirm you can open one aggregate report and see
your own sending IP in it. That single check converts DMARC from a checkbox into an instrument.

### Finding 4 — `p=none` is correct today and wrong soon

`p=none` is the right posture while you are standing up a new sender: enforcement before you know
your own sending sources will bounce your own mail. Do not change it yet.

Change it *after* you have read a few weeks of aggregate reports and confirmed that every legitimate
source aligns. Then `p=quarantine; pct=10` and walk it up. Nothing in Findings 1–3 should be
deferred until then; those are independent.

---

## Tools delivered — run these on the box

Both are CLI-only (they refuse to run over HTTP), open their inputs read-only, and never write to,
delete from, or reorder a subscriber row. Neither opens an SMTP connection.

### `tools/list-hygiene.php` — Task 2

```bash
php tools/list-hygiene.php /home/johndel/bitrs-data/subscribers.json > sweep-$(date +%F).txt
```

Classifies every address as `syntax_invalid`, `undeliverable` (domain has no MX, A or AAAA),
`disposable`, `role`, or `clean`. Prints counts, percentages, the full address list for every
non-clean class, and the distinct non-resolving domains. `--no-dns` skips resolution for a fast
syntax-only pass.

The on-disk shape of `subscribers.json` is not documented in `jd-mail-bible`, so the script does not
guess: it detects and **prints** the shape it found (top-level list / wrapper object / map keyed by
address), extracts addresses from any of `email`, `Email`, `EMAIL`, `address`, `mail`, `e`, or from
plain strings, and reports the index of every row it could not read rather than silently dropping it.

**Tested** against all three shapes plus malformed JSON and a missing file. Sample run on a fixture:

```
json shape: top-level LIST of 7 rows
addresses : 6 unique (after lowercase + trim)
WARNING   : 1 row(s) yielded no address — indexes: 6
COUNTS
  clean                 2    33.3%
  role                  1    16.7%
  disposable            1    16.7%
  undeliverable         1    16.7%
  syntax_invalid        1    16.7%
```

### `tools/bounce-report.php` — Task 1

```bash
php tools/bounce-report.php /home/johndel/mail-data/mail.log --sample=20
```

Profiles the log first — byte count, line count, date span, and the distinct `[TOKEN]` event
markers actually present — then counts by family and computes the hard-bounce rate, per campaign
where a campaign id is present. It prints sample matching lines so you can verify the keyword
matching is honest before trusting the numbers.

It distinguishes three states that a naive script would collapse into "0%":

| State | Output |
|-------|--------|
| log empty | `UNMEASURED — the log is empty. No sends recorded.` |
| sends logged, no bounce events ever | `UNMEASURED — sends are logged but no bounce outcome is ever written.` |
| real bounce events present | attempted / hard / soft / rate, per campaign |

**Tested** against all three states plus a missing file. On a missing `mail.log` it exits 2 with:

```
If mail.log does not exist, that is itself the finding:
  no log => no send record => bounce rate is UNMEASURED.
```

Keyword matching is a **discovery** tool, not a parser. Once you have run it once and seen the real
log format in the sample output, tighten the `$FAMILIES` array to the exact tokens JD Mail writes.

---

## What I would do next, in order

1. **Fix the duplicate `fo` tag.** Five minutes in the cPanel zone editor. Removes the risk that your entire DMARC record is discarded.
2. **Open one DMARC aggregate report** and find your own IP in it. Confirms the instrument works.
3. **Run `bounce-report.php`** on the real `mail.log`. Establishes whether you have a send record at all.
4. **Run `list-hygiene.php`** on the real `subscribers.json`. Report only — decide on removals after you see the numbers.
5. **Before the first campaign:** build the bounce path around a monitored `Return-Path:` mailbox, not the `mail()` return value. Then send to your 50 most recently-active subscribers first, not to all 441.
6. **On AWeber cutover:** identify and remove the three stale SPF IPs.

Item 5 is the one that protects the asset. A 441-address list is small enough that a bad first send
does no damage by volume — but it does damage by *pattern*, and the pattern is what mailbox
providers score. Warm it slowly.

---

*Tasks 1 and 2 report NOT RUN because the data was not reachable, not because the checks were
skipped. Task 3's numbers are live DNS measurements taken 2026-09-09.*
