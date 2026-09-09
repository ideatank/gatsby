# SPF & DMARC — tightened records, staged

**Date:** 2026-09-09 · **Domain:** `johndelavera.com` · **Zone editor:** cPanel → Zone Editor

I cannot reach your DNS from this container, so these are copy-paste values plus the tooling to
prove each one landed. Every record below was linted before it reached this page (`lint-record.php`,
exit 0). **Do not skip the verify step between stages** — DNS caches, and a change you assume took
is a change you have not made.

---

## The three IPs, identified

Reverse DNS settles what they are:

| IP | PTR | Reading |
|----|-----|---------|
| `50.28.87.135` | `gutsy.simpleology.com` | Your cPanel box. Also the `A` for `johndelavera.com` **and** for `mail.johndelavera.com` (the sole MX). |
| `169.60.154.200` | `c8.9a.3ca9.ip4.static.sl-reverse.com` | SoftLayer / IBM Cloud |
| `206.221.182.218` | **(no reverse DNS at all)** | Unidentifiable |
| `75.126.204.26` | `1a.cc.7e4b.ip4.static.sl-reverse.com` | SoftLayer / IBM Cloud |

`sl-reverse.com` is SoftLayer, the infrastructure AWeber ran on. Two of the three are almost
certainly AWeber-era; the third has no reverse DNS whatsoever, which is the worst state an
authorized sender can be in.

**Consequence for staging:** these cannot be removed while AWeber is still sending. Deleting them
mid-migration breaks your live mail. They come out at cutover, in Stage 3.

---

## STAGE 1 — apply today. Provably zero risk.

Two edits. Neither changes what is authorized to send; both remove a defect.

### 1a. SPF

**Replace:**
```
v=spf1 ip4:50.28.87.135 +a +mx +ip4:169.60.154.200 +ip4:206.221.182.218 +ip4:75.126.204.26 ~all
```

**With:**
```
v=spf1 ip4:50.28.87.135 mx ip4:169.60.154.200 ip4:206.221.182.218 ip4:75.126.204.26 ~all
```

**What changed, and why it is zero risk:**

- **Dropped `+a`.** `a` means "whatever the A record of `johndelavera.com` points at". That is
  `50.28.87.135` — *already listed literally* as `ip4:50.28.87.135`. So removing `a` removes
  exactly zero authorization today. It removes future risk: the day you put the website behind
  Cloudflare or any shared proxy, `+a` would silently hand that proxy's shared IPs the right to
  send mail as you. This is the whole tightening, and it costs nothing.
- **Dropped the redundant `+` qualifiers.** `+` is the default in SPF; `+ip4:` and `ip4:` are
  identical. Cosmetic — the record becomes readable.
- **Kept `mx`.** It costs one DNS lookup and self-maintains: if your host migrates the box and
  updates the MX, SPF follows automatically instead of failing silently. *Caveat: if you ever point
  MX at a third-party inbound filtering service, `mx` would authorize that service to send as you.
  Revisit if that day comes.*
- **Kept `~all`.** Softfail is correct while the sender inventory is unconfirmed. Hardening comes
  in Stage 3, after the reports tell you who actually sends.

Lookup count drops 2 → 1. Record length 88 bytes, fits one TXT string.

### 1b. DMARC — this is the actual bug fix

**Replace:**
```
v=DMARC1;p=none;sp=none;adkim=r;aspf=r;pct=100;fo=0;rf=afrf;ri=86400;rua=mailto:bd154549761d.a@dmarcinput.com,mailto:dmarc-contact@johndelavera.com; ruf=mailto:bd154549761d.f@dmarcinput.com; fo=1
```

**With:**
```
v=DMARC1; p=none; sp=none; adkim=r; aspf=r; pct=100; fo=1; rf=afrf; ri=86400; rua=mailto:bd154549761d.a@dmarcinput.com,mailto:dmarc-contact@johndelavera.com; ruf=mailto:bd154549761d.f@dmarcinput.com
```

**What changed:** exactly one thing. `fo` appeared twice — `fo=0` in the middle and `fo=1` tacked
on the end. RFC 7489 §6.4 requires each tag once, and says a conforming verifier MUST ignore a
record with a syntax error. That is not a cosmetic complaint: a strict verifier discards the whole
record, which takes your `rua` reporting down with it. The two values also contradicted each other
(`fo=0` = report only if SPF *and* DKIM both fail; `fo=1` = report if *either* fails).

Kept `fo=1` — more signal while you are standing up a new sender. Everything else is byte-identical
in meaning; only the whitespace is normalised. **Nothing about your mail's treatment changes.** The
record simply stops being invalid.

Record length 198 bytes, fits one TXT string.

### Verify Stage 1

```bash
php tools/verify-dns.php johndelavera.com; echo "exit=$?"
```

Before the edit this exits **2** with:

```
ERROR DMARC: duplicate tag(s) [fo]. RFC 7489 §6.4 requires each tag once ...
WARN  SPF: "a" authorizes whatever the web A record points at ...
```

After the edit — allow for the zone's TTL, re-run until it flips — both lines are gone and it
should exit **1** (warnings only: `p=none`, plus the two standing reminders about `rua` readership
and `ruf`). **If it still exits 2, the edit did not take.** Do not proceed to Stage 2.

---

## STAGE 1b — optional, low risk, your call

```
v=DMARC1; p=none; sp=quarantine; adkim=r; aspf=r; pct=100; fo=1; ri=86400; rua=mailto:bd154549761d.a@dmarcinput.com,mailto:dmarc-contact@johndelavera.com
```

`sp=quarantine` protects **subdomains** while leaving root-domain mail at `p=none` — untouched. You
send `From: contact@johndelavera.com`, the root domain, so this costs your real mail nothing while
closing the subdomain spoofing path.

**The honest caveat:** I cannot prove nothing sends as a subdomain. If some forgotten script sends
as `mail.johndelavera.com` or `bounces.johndelavera.com`, this would start filtering it. Low risk,
not zero. If you want certainty first, skip 1b and let the Stage 2 reports tell you — they
enumerate subdomain senders too. (This version also drops `ruf`; see Stage 2.)

---

## STAGE 2 — after 2–4 weeks of reading aggregate reports

**Do not do this until you have opened an actual report and found `50.28.87.135` in it.** That
single check is what converts DMARC from a checkbox into an instrument, and it is the gate for
everything below.

```
v=DMARC1; p=quarantine; sp=quarantine; pct=10; adkim=r; aspf=r; fo=1; ri=86400; rua=mailto:bd154549761d.a@dmarcinput.com,mailto:dmarc-contact@johndelavera.com
```

Then walk the percentage up, verifying at each step and watching the reports for legitimate mail
starting to fail:

```
pct=10  →  pct=25  →  pct=50  →  pct=100  →  p=reject; sp=reject
```

Give each step at least one full reporting cycle (`ri=86400` = daily). If a legitimate sender
appears in the failure column, stop and fix alignment before continuing.

**`ruf` is dropped here.** Forensic reports can carry headers and content of failing messages —
including your subscribers' addresses — to whatever address is listed. Most large providers never
send them anyway. Keep it only if you are certain you control `dmarcinput.com`.

---

## STAGE 3 — at AWeber cutover

Once AWeber is confirmed sending nothing for this domain, remove the three legacy IPs:

```
v=spf1 ip4:50.28.87.135 mx ~all
```

Optional final hardening, **only** once the DMARC reports have enumerated every legitimate sender:

```
v=spf1 ip4:50.28.87.135 mx -all
```

Be honest about the payoff: with DMARC at `p=reject`, `~all` versus `-all` changes very little,
because DMARC is doing the enforcing. `-all` matters mainly for receivers doing SPF-only checks.
It is polish, not protection — and it is the one change here that can hard-fail legitimate mail if
you missed a sender. Low urgency; do it last or not at all.

---

## Order of operations, and the one thing not to skip

1. **Stage 1a + 1b (SPF `+a`, DMARC `fo`).** Today. Zero risk. Verify exit code flips 2 → 1.
2. **Open a DMARC aggregate report. Find `50.28.87.135` in it.** ← *the gate*
3. Stage 2 ramp, one step per reporting cycle.
4. Stage 3 at cutover.

Step 2 is not optional and not a formality. Steps 3 and 4 tighten enforcement — if the reporting
is not actually reaching you, you are tightening blind, and the first thing you will learn is that
your own campaign stopped being delivered.

---

## Paste safety

Every record above is on one line, plain ASCII, no smart quotes. If you retype rather than paste,
or if it passes through a chat client or word processor first, lint it before it touches the zone:

```bash
php tools/lint-record.php dmarc 'v=DMARC1; p=none; ...'
php tools/lint-record.php spf   'v=spf1 ip4:50.28.87.135 mx ~all'
```

Exit 2 means do not paste. It catches duplicate tags, invalid values, malformed `mailto:`,
`+all`, the deprecated `ptr` mechanism, curly quotes, stray line breaks, and terms placed after
`all` where they are dead. In cPanel's Zone Editor, enter the value **without** surrounding
double quotes — the panel adds its own.

---

## One thing outside the scope of this request

`50.28.87.135` reverse-resolves to `gutsy.simpleology.com` — a shared Liquid Web box. Reverse DNS
exists, which is what Gmail's bulk-sender rules require, so this is not a blocker. But it does mean
your sending reputation is **pooled with every other tenant on that server**, and you are about to
move 441 subscribers onto it via PHP `mail()` through shared Exim. That is the weakest sending
posture available, and none of the DNS tightening above changes it.

Not something to solve this week, and not part of what you asked for. But if the first campaign
underdelivers despite clean SPF/DKIM/DMARC, the shared IP is where to look — not at the records.
