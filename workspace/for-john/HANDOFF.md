# HANDOFF — JD Mail / AWeber audit

**From:** Claude Code on the web (cloud container), session 2026-09-09
**To:** the next Claude Code session, running locally on JD's MacBook
**Repo:** `ideatank/gatsby`, branch `claude/jd-mail-hygiene-audit-clc6ob`, everything under `workspace/for-john/`

Read this first. It is the whole session compressed, including the things I got wrong.

---

## READ THIS BEFORE ANYTHING ELSE — two files are gone

I worked in an ephemeral cloud container. **It is being destroyed.** These were never committed
(they are customer data and do not belong in a git repo):

| File | Rows | Status |
|---|---:|---|
| `suppression-list.csv` | 471 | **JD has it — sent as a chat attachment. Ask him for it.** |
| `migration-list.csv` | 1,315 | **JD has it — sent as a chat attachment. Ask him for it.** |
| AWeber export ZIP (38 MB, 249 lists) | — | JD uploaded it; he has the original |

**Do not try to regenerate these from the API.** `suppression-list.csv` in particular is the
471-address opt-out set, and it is the single most important artifact produced. Get it from JD
before doing anything else.

To rebuild from the ZIP if needed: `python3 tools/analyse-export.py <unzipped_dir> --out <dir>`.

---

## What you can do that I could not

I had **no server access**. Verified, not assumed: `curl https://johndelavera.com/` returned
`CONNECT tunnel failed, 403`; no `~/bookgen-dev`, no `server-*` wrappers, no cPanel credentials.
Network egress is blocked by policy in the web container.

**On the Mac you have the `bookgen-cc-code` tooling and can reach the server.** That unblocks:

1. Reading `/home/johndel/bitrs-data/subscribers.json` — the list JD already loaded into JD Mail
2. Reading `/home/johndel/mail-data/mail.log` and `config.php`
3. Confirming whether `send-cron.php` exists

All three were blocked for me and all three are on the critical path. **Read `bookgen-cc-code` and
`dnx-safe-write` before touching anything on that box.**

---

## State of play, in one paragraph

JD is migrating from AWeber to his self-hosted JD Mail. He has already loaded ~400 subscribers into
JD Mail, selected by "opened" — but the AWeber backup contains no open data at all, so how that
selection was made is unresolved, and it may have cut his best subscribers. JD Mail has not sent
anything yet. The 471 opt-outs are **not** known to be loaded. Nothing may be sent until that is
checked.

---

## The single most urgent item

**`php tools/check-suppression.php /home/johndel/bitrs-data/subscribers.json suppression-list.csv`**

Exit 2 = do not send. It also probes four addresses whose real behaviour I established via the
AWeber API:

| Address | Expected | Why |
|---|---|---|
| `mark@lyfordoffice.com` | **present** | 18 clicks, opened 36 of 36 in 2026 — best subscriber found |
| `brm444@msn.com` | present | confirmed clicker (3) |
| `ghaleib@gmail.com` | present | confirmed clicker (4), opened ~14 of 27 in 2026 |
| `dlucco@gmail.com` | **absent** | 330 sends, 10 opens, 0 clicks, 0 revenue — dead weight |

Those four lines tell you whether the loaded selection kept the right people without needing to know
how it was built. Tested against pass, fail and missing-file cases.

---

## Findings, with confidence

### MEASURED — hard numbers, from the export or live DNS

**Bounce rate — the question that opened the engagement, finally answered.** Across 1,534 sends and
3,844,587 emails: **0.40% lifetime, 0.55% in 2026**, with **zero spam complaints in 2026**. The list
is clean and delivers. `Number Undeliverable` in `broadcasts/broadcast_summary.csv` is the source;
AWeber's stats *API* exposes no bounce metric, which is why I reported it UNMEASURED for most of the
session.

**DNS.** SPF, DKIM (selector `default`, RSA 2048) and DMARC all present and valid. **DMARC carries a
duplicate `fo` tag** (`fo=0` and `fo=1`) — RFC 7489 §6.4 says a conforming verifier must ignore a
record with a syntax error, which would silently discard the policy *and* the `rua` reporting.
Replacement records staged in `dns-records.md`. `tools/verify-dns.php johndelavera.com` currently
exits 2 on this; the flip to exit 1 is how you prove the fix landed. **Not yet applied.**

**Click tracking is off, and it is a per-broadcast checkbox.** `click_tracking_enabled: false`. Read
on nine broadcasts, perfect correlation with recorded clicks. On 2026-03-13 two sends went out the
same day on the same list: 1,368 recipients tracking off / 0 clicks, and 279 recipients tracking on
/ 8 clicks. **Big sends have it off, small sends have it on** — one long-running workflow problem,
most likely AWeber carrying settings forward through duplicated broadcasts. Last tracked big send
was **2024-12-17**; every one since reports 0. Sent broadcasts cannot be fixed. Neither
`create_broadcast` nor `update_broadcast` exposes the field — it is UI-only. **Durable fix: one
clean master broadcast with tracking on, verified, then always duplicate from that.**

**List composition.** 1,339 unique active, 471 unique unsubscribed, 2,107 memberships across 249
lists (140 non-empty), 34% on more than one list. **24 people are unsubscribed on one list and still
subscribed on another** — they are receiving campaigns after opting out. Already excluded from
`migration-list.csv`; still need suppressing in AWeber.

**Acquisition is dead, and the 2020 "peak" was an import.** Date stamps: 236 people added
2020-10-20 and 223 on 2020-10-21 to one list; 279 on 2020-09-06 to another; the `ontra_*` lists
loaded in 2017–18. 577 of the 581 people dated 2020 have no source URL and no add method. Genuine
acquisition all time: 29 signups in 2019 from `internetmarketingchronicles.com` carrying `?bb_aff=`
affiliate codes — the only repeatable channel in eleven years — and **40 signups from every owned
property combined in the five and a half years since**. New subscribers: 6 in 2024, 4 in 2025,
**0 in 2026**.

**The live acquisition bug.** 2024 cohort: 28 signups, 22 unsubscribed, and **14 of those left on
the same day they joined**, all via `my_web_form` onto one list, including four on 2024-08-05.
Imported cohorts churn 17–35% over five years; recent form cohorts churn 52% and 78% within months.
Three candidate causes — the signup sequence drives them off, something opts them in involuntarily
(checkout or download flow), or automated submissions. **The export cannot distinguish them. The
ten-minute test is signing up to his own form and watching the first 24 hours.**

### INFERRED — flagged as such, not verified

- `send-cron.php` likely does not exist, so JD Mail has probably never sent a campaign. From
  `jd-mail-bible` dated 2026-04-27, **four and a half months stale**. **Verify on the box.**
- Bounce detection is specced as "check `mail()` return value". That is a weak signal — it reports
  handoff to the local MTA, not delivery. **Design the bounce path around a monitored
  `Return-Path:` mailbox instead**, or JD Mail will report ~0% bounce on whatever is actually dead.

---

## Corrections I made to my own earlier claims

Do not inherit these. Each was wrong in the session before I corrected it:

1. **"The 394-subscriber list is dormant, don't mail it."** Wrong. Only its *metadata* is stale — a
   member imported 2020-10-21 received all five 2026 broadcasts. I inferred dormancy from broadcast
   metadata without testing a member.
2. **"~200 of 249 lists are empty."** Wrong — 109 are empty, 140 non-empty. I assumed the API
   paginates by size; it does not.
3. **"AWeber costs roughly $30/month."** Based on a 441-subscriber figure that was itself wrong.
4. **"Tier on `Add Method` (webform vs import)."** Wrong — 92.2% of the field is blank; only 105 of
   1,339 carry any method. I generalised a three-person sample too far.
5. **"Click tracking died about six months ago."** Wrong — it died between 2024-12-17 and
   2025-01-01, roughly twenty months.
6. **"442 new subscribers in 2020" presented as growth.** Wrong — it was a bulk import.
7. **I committed 10 customer email addresses to this repo.** Removed, `data/.gitignore` now blocks
   `*.csv` and `*.zip`. The commit is still in history — offer JD a history rewrite if he wants it
   purged properly.

The pattern in 1, 4 and 6: I generalised from metadata or a tiny sample instead of testing an
instance. Test an instance.

---

## Tools

All read-only, CLI-only, no network except DNS where noted. All tested against pass, fail and
malformed inputs.

| Tool | Purpose |
|---|---|
| `check-suppression.php` | **Pre-send gate.** subscribers.json vs the 471 opt-outs. Exit 2 = do not send. |
| `verify-dns.php` | Asserts live SPF/DKIM/DMARC. Exit 0/1/2. Currently exits 2. |
| `lint-record.php` | Offline lint of a candidate SPF/DMARC string before pasting into the zone. |
| `analyse-export.py` | Aggregates the AWeber export; produces the two CSVs. |
| `acquisition-forensics.py` | Signup-source analysis by year and era. |
| `list-hygiene.php` | Classifies a subscriber list: syntax, no-MX, disposable, role, clean. |
| `bounce-report.php` | Profiles `mail.log` then counts. Distinguishes "no log", "no bounce record" and real data rather than collapsing all three to 0%. Never run against a real log — do this on the Mac. |

---

## Open items, in priority order

1. **Run `check-suppression.php`.** Nothing sends until this is clean.
2. **Get `suppression-list.csv` from JD** and load it into JD Mail as a permanent never-send set.
3. **Suppress the 24 cross-list opt-outs in AWeber.** They are being mailed now.
4. **Resolve how the ~400 were selected.** The backup has no open data, so it came from somewhere
   else — most likely an AWeber UI segment (good) or something unreliable (rebuild). The probe
   output settles it.
5. **Fix click tracking** — master broadcast with tracking on, then always duplicate from it.
6. **Apply the two Stage 1 DNS edits** from `dns-records.md`; verify the exit code flips 2 → 1.
7. **Read the box**: does `send-cron.php` exist? what is in `mail.log`? Everything about JD Mail's
   state is inference until this is done.
8. **The acquisition bug.** Sign up to his own form and watch 24 hours. This is worth more than
   items 5–7 combined, and nothing in this folder addresses it.

---

## Standing cautions

- **Reputation does not transfer.** `50.28.87.135` reverse-resolves to `gutsy.simpleology.com`, a
  shared Liquid Web box. JD Mail sends via PHP `mail()` through shared Exim on an IP pooled with
  other tenants. Do not move 1,339 engaged people onto it at once — warm on 20–50 for weeks while
  AWeber carries the money.
- **Do not cancel AWeber** until JD Mail has done 3–4 clean sends at comparable open rates. The
  overlap cost is trivial against losing the channel.
- **Customer data does not go in the repo.** `data/.gitignore` enforces it.
- **Opens are not trustworthy here.** Proxy traffic was 65–82% of opens across every subscriber
  profiled. One subscriber showed a 100% open rate that turned out to be Apple Mail Privacy
  Protection; what proved him real was 18 clicks. Clicks discriminate, opens do not — and clicks
  are blank from 2025-01 onward.

---

## The thing worth saying out loud

Eight documents in this folder are maintenance on a 1,339-person list that was assembled by import
between 2017 and 2020, has gained roughly 40 people through JD's own properties in five and a half
years, currently loses half of new signups within a day, and has not gained a single member in 2026.

The list is well run — 0.55% bounce, zero complaints, open rate up from 12.5% to 30.4%. **He got
much better at talking to them. There just aren't any new ones.**

Migrating it off AWeber saves a subscription fee and gains control. It does not make the list grow.
If the next session only has time for one thing, make it item 8.
