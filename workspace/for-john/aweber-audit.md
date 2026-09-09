# AWeber — what the account actually shows

**Date:** 2026-09-09 · **Source:** AWeber API, read-only. No sends, no edits, no subscriber changes.
**Account holds 249 lists.**

This supersedes several assumptions in `jd-mail-hygiene-audit.md`, which were drawn from
`jd-mail-bible` (dated 2026-04-27) rather than from the sending system itself.

---

## 1. You are actively mailing ~1,325 people, and it is working

Five most recent broadcasts (targeting includes list 5395369, *VIP Active Turbo People*):

| Sent | Subject | Recipients | Unique opens | Open rate | Unique clicks |
|------|---------|-----------:|-------------:|----------:|--------------:|
| 2026-07-27 | Storefront now Included /// Re: 33 Non-Fiction books | 1,322 | 271 | **20.5%** | **0** |
| 2026-07-26 | 33 Non-Fiction books. One owner each. | 1,324 | 284 | **21.5%** | **0** |
| 2026-06-27 | Just added: over 100 to choose from (coupon) | 1,327 | 264 | **19.9%** | **0** |
| 2026-06-23 | Thank you — and a few hours left | 1,327 | 310 | **23.4%** | **0** |
| 2026-06-22 | Ends tonight: I write the book, you own it ($97) | 1,327 | 280 | **21.1%** | **0** |

A ~21% open rate on a commercial offer list is healthy. `total_sends` (~1,325) exceeds any single
list's size, so these broadcasts fan out across several lists — **~1,325 is your real reachable
audience**, not the 441 in the bible and not the 394 on the main list.

**This is a live, revenue-carrying channel.** Migrating it is not standing up a new toy; it is
moving production.

### The trend is strongly positive

| Period | Typical open rate |
|--------|------------------:|
| 2020 broadcasts (4,300–4,500 recipients) | 7–9% |
| 2026 broadcasts (~1,325 recipients) | 20–23% |
| *Turbo AI Book Creator* (26 recipients, 2025) | **63–78%** |

Smaller and more recent beats bigger and older, by a factor of ten. That is the whole argument for
list hygiene, and your own data already proves it.

---

## 2. FINDING — click tracking reports zero on every recent broadcast

`unique_clicks: 0` on all five sends above. Meanwhile the 2020 broadcasts recorded 31, 56 and 66
unique clicks, so tracking demonstrably worked then.

271 people opened "33 Non-Fiction books. One owner each." and the system recorded **not one click**.
On a $97 offer.

Either click tracking has been switched off, the links are unwrapped, or something broke. It cannot
be that nobody clicked — the sends coincide with a storefront that takes orders.

**Consequence:** you are running paid offers with opens as your only signal. Opens are the weakest
metric in email (inflated by Apple Mail Privacy Protection and image proxies). Clicks are the one
that tracks revenue. You have been flying on the wrong instrument for at least three months.

**This is worth more than everything in the DNS report.** Check the AWeber broadcast settings for
click tracking before the next send.

---

## 3. FINDING — bounce data does not exist in AWeber's API

Full stats for broadcast 48323681 returned 21 metrics: opens, clicks, sales, unsubscribes, webhits,
hourly and daily breakdowns, top subscribers. **There is no bounce or delivery-failure metric of any
kind.**

So the hard-bounce rate remains **UNMEASURED** — not because I could not reach `mail.log`, but
because the system that has been doing the sending for six years does not expose it here either.

That has a direct consequence for JD Mail: **you have no baseline.** When JD Mail reports its first
bounce number there will be nothing to compare it against. Build the bounce path anyway; just know
it will be measuring for the first time, not verifying.

---

## 4. FINDING — "John Delavera's List" (394 subscribers) is dead weight

| | |
|---|---|
| Subscribed | 394 |
| Unsubscribed | 65 |
| **Newest subscriber joined** | **2020-10-21** |
| **Last broadcast** | **2021-01-08** |

Nobody has joined in almost six years. Nobody has been mailed in five years and eight months.
Sample addresses carry tags like `use-sell-member`, `turboplr`, `vip` — a product era that ended.

**Do not mail this list.** Not from AWeber, and above all not from JD Mail as a first campaign.
Addresses abandoned for years get recycled by providers into spam traps; hitting one from a
brand-new shared IP is how a domain gets blocked rather than merely filtered. A list this old is
not a warm asset waiting to be reactivated — it is the single most dangerous thing you could point
a new sender at.

If you want to try reactivation, it is its own project, done from AWeber's established reputation,
in batches of 50, with the openers kept and everyone else deleted. Not from JD Mail.

---

## 5. The account is cluttered

249 lists; roughly 200 have zero subscribers. Names like `ontra_delavo_NOT`,
`members_monthly_trial_7777`, `Turbo Ebook 001 - Customers` through `009`, `Sensei Branding`,
`contest_timb`. Several near-duplicates (`Internet Marketing Chronicles` and `... 2`;
`ontra_*` × 7).

Not urgent, and not a deliverability problem. But when you migrate, migrating 249 lists is a very
different job from migrating the four that carry the ~1,325 active people. **Establish which lists
the recent broadcasts actually target before designing JD Mail's segment model** — otherwise you
will faithfully reproduce six years of dead structure in a new system.

---

## 6. What this means for the JD Mail decision

My earlier advice assumed a 441-address list that had barely been used. That was wrong, and the
correction cuts both ways.

**Against migrating soon:** this is a working channel producing ~21% opens on live commercial
offers. Moving it onto an untested self-built sender, sending via PHP `mail()` through shared Exim
on an IP whose reputation is pooled with every other tenant on `gutsy.simpleology.com`, risks a
revenue stream that currently functions. That is a materially bigger risk than I described.

**For building anyway:** the warming clock is real and unchanged. ~1,325 engaged subscribers cannot
move to a cold IP at once — that migration needs months of graduated sending, so the sender has to
exist long before it carries the load.

**Also:** I previously estimated AWeber at "roughly $30/month" based on 441 subscribers. With ~1,325
active across 249 lists the real figure is higher, so the cost argument for leaving is stronger than
I credited. It is still not the lever that replaces a salary.

### Revised recommendation

1. **Fix click tracking.** Before the next send. Highest value, lowest effort, and it is the
   instrument that tells you which offers convert.
2. **Two DNS edits** from `dns-records.md`. Five minutes, zero risk.
3. **Build `send-cron.php`** — but its first job is *not* migration. Point it at a 20–50 address
   segment of genuinely recent, engaged subscribers and send something real, weekly, while AWeber
   keeps carrying the money. That warms the IP and proves the sender at the same time.
4. **Leave the 394-subscriber list where it is.** It is not migration inventory.
5. Decide which of the 249 lists are real before designing segments.

---

*All figures read from the AWeber API on 2026-09-09. Read-only: no subscriber was added, modified,
moved or removed, and no broadcast was created, altered or sent.*
