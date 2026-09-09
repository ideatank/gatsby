# Which lists do the live broadcasts actually target?

**Date:** 2026-09-09 · Read-only AWeber API. No sends, no edits, no subscriber changes.

---

## Short answer

**The API cannot tell you directly — but a subscriber-level test settles it: you are mailing
essentially everyone, and that includes the list I told you was dormant.**

I owe you a correction on that, and it matters. Details below.

---

## Why the direct question has no direct answer

The five 2026 broadcasts are filed under **one** list — 5395369, *VIP Active Turbo People* (265
subscribers) — yet each reports `total_sends` ≈ 1,325.

I swept every list in the account with a meaningful subscriber count. **None of the others carries
those broadcast IDs or those June/July 2026 dates.** Last activity per list:

| List | Subs | Last broadcast filed |
|---|---:|---|
| VIP Active Turbo People (5395369) | 265 | **2026-07-27** |
| Turbo AI Book Creator (6860534) | 21 | 2025-07-15 |
| DELAVO Gold Lifetime (5990552) | 7 | 2025-07-08 |
| John Delavera's Subscribers (5097737) | 140 | 2022-04-14 |
| Turbo PLR customers (5206191) | 47 | 2021-11-16 |
| TurboPLR subscribers (5563298) | 20 | 2021-06-27 |
| **John Delavera's List (5821078)** | **394** | **2021-01-08** |
| Use-Sell.com Customers (5878687) | 20 | 2020-12-18 |
| ontra_delavo (4856330) | 174 | 2020-10-18 |
| Feedback - Courses platform (5750707) | 182 | 2020-09-07 |
| ontra_all (4874535) | 28 | 2020-03-05 |
| Internet Marketing Chronicles (5463541) | 21 | 2019-12-06 |
| ontra_delavo_NOT (4863899) | 66 | 2018-12-31 |
| contest_timb, NotificationList, TurboEbooks Updates, Kindle's readers, Medium Publications | 68/62/6/1/2 | **never** |

The same mismatch appears throughout history — a broadcast filed under a 7-subscriber list reaching
503 people (2025-07-08), under a 28-subscriber list reaching 1,258 (2020), under a 66-subscriber
list reaching 5,149 (2018).

**Conclusion:** AWeber files a multi-target broadcast under a single primary list and reports the
global recipient count in `total_sends`. The full target set is not exposed on this API surface.
Anything more precise would have to come from the AWeber web UI's broadcast editor.

---

## The test that settles it anyway

I pulled one subscriber from `John Delavera's List` — the list whose newest signup is 2020-10-21 and
whose last *filed* broadcast was 2021-01-08:

**`dlucco@gmail.com`** · imported 2020-10-21 · tag `use-sell-member` · `subscription_method: import`

Their recent activity:

```
2026-07-27  sent_message   ← "Storefront now Included /// Re: 33 Non-Fiction books"
2026-07-26  sent_message   ← "33 Non-Fiction books. One owner each."
2026-06-27  sent_message   ← "Just added: over 100 to choose from (coupon)"
2026-06-23  sent_message   ← "Thank you — and a few hours left"
2026-06-22  sent_message   ← "Ends tonight: I write the book, you own it ($97)"
```

All five. **The list is not dormant. Its people are in your current sends.**

---

## CORRECTION to the earlier audit

In `aweber-audit.md` I wrote that the 394-subscriber list was "dead weight", that its people had not
been contacted since 2021, and that the danger was making it your *first JD Mail campaign*.

**The first two are wrong and the third understates it.** The list-level metadata is dormant; the
people are not. They are receiving every campaign you send. The risk is not waiting at migration —
**it is live right now, on AWeber's reputation.**

I inferred "dormant list" from broadcast metadata and did not test a member until now. That is the
same mistake I flagged in the bible: trusting a record instead of the system.

---

## What that one subscriber's numbers show

```
messages_sent : 330
unique_opens  : 10
open_rate     : 3%
unique_clicks : 0
click_rate    : 0
sales_revenue : 0
```

**330 emails. 10 opens. Zero clicks, ever. Zero revenue.**

Their visible history runs from 2023-09-22 to 2026-07-27 — roughly 100 consecutive sends marked
`Unopened`, with three exceptions.

Device breakdown on those 10 opens: **81.8% "Proxy"**. That is Gmail/Apple image proxying, which
fires without a human looking. Real engagement is lower than 3%.

This person was **imported**, not opted in, six years ago, and has been mailed 330 times since.

---

## What this means for the 21% open rate

~1,325 recipients at ~21% opens is not one uniformly warm audience. It is a mix: a genuinely engaged
core, plus a long tail like the subscriber above dragging the average down and — more importantly —
telling every mailbox provider that a meaningful share of your recipients never engage.

Your own data shows what happens when the tail is removed. *Turbo AI Book Creator*, 26 recent
opt-ins, runs **63–78%** opens. Same sender, same domain, same reputation. The difference is
entirely who is on the list.

---

## Full census — correcting a second error

I earlier said "roughly 200 of 249 lists are empty." That came from assuming the API paginates in
descending size order. It does not — position 239 held a 21-subscriber list. I enumerated all 249:

| | |
|---|---:|
| Total lists | 249 |
| Lists with ≥1 subscriber | **140** |
| Empty lists | **109** |
| Total list memberships | **2,107** |
| Actual recipients per broadcast | **~1,325** |

2,107 counts memberships, not people — heavy overlap, since a buyer lands on a product list, a
customer list and a general list. **~1,325 after dedupe is consistent with "the whole account
minus unsubscribes."** That is a blast, not a segment.

The tags on your primary list show you once thought otherwise:
`vip_active`, `vip_any_opens_since_january_2020`, `jdvip_unsub`, `328`.

`vip_any_opens_since_january_2020` is an engagement segment you built and, on this evidence, stopped
using. The instinct was right.

---

## Consequences for JD Mail's design

**1. Do not port 249 lists.** You do not have 249 audiences. You have one audience of ~1,325 and a
pile of product labels. The correct model is a single subscriber table plus tags — which is what
`subscribers.json` with a flag field already is. Reproducing the list structure would faithfully
recreate six years of accumulated mess in a system built to escape it.

**2. Segment by engagement before you migrate anything.** Three buckets:
   - opened in the last 90 days → migrate first, warm the new IP with these
   - opened in the last 12 months → migrate second
   - 300+ sends and near-zero opens → **do not migrate**

**3. The cleanup belongs in AWeber, now, not in JD Mail later.** Suppressing the dead tail on
AWeber's established reputation is safe. Carrying it onto a cold shared IP is not. Every send you
make between now and migration to people like `dlucco@gmail.com` is teaching mailbox providers
something you will not be able to unteach.

---

## Revised priority order

1. **Suppress the non-openers in AWeber.** Biggest deliverability win available, costs nothing, and
   it is reversible — suppress rather than delete. Your open rate should jump immediately.
2. **Fix click tracking** (`unique_clicks: 0` across all five 2026 broadcasts, while 2020 broadcasts
   recorded 31–66). You are running $97 offers with no click data.
3. Two DNS edits from `dns-records.md`.
4. Build `send-cron.php`, pointed at the 90-day engaged segment only.

Item 1 moved to the top because of what this investigation found. It was previously a migration
task. It is now a live one.

---

*One open question I could not close from the API: exactly which lists are ticked as targets on each
broadcast. That lives in the AWeber web UI. It matters less than it did — the subscriber-level test
shows the effective answer is "nearly all of them."*
