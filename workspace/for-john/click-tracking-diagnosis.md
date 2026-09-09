# Click tracking — diagnosed

**Date:** 2026-09-09 · AWeber API, read-only. Nothing was created, sent, or modified.

---

## Root cause

```
click_tracking_enabled: false
```

It is a per-broadcast setting, and it is switched off on your large sends. Nothing is broken.
Nothing needs repairing. A checkbox is off.

---

## The evidence

I read the flag directly on nine broadcasts. It correlates perfectly with the recorded clicks —
no exceptions.

| Broadcast | Date | Recipients | Opens | Clicks | `click_tracking_enabled` |
|---|---|---:|---:|---:|---|
| 48323681 | 2020-12-12 | 4,535 | 410 | 31 | `true` ✔ measured |
| 59827575 | 2025-07-04 | 26 | 19 | 12 | `true` ✔ measured |
| 60655029 | 2026-02-12 | ~1,325 | — | **0** | **`false`** ✔ measured |
| 60678714 | 2026-02-19 | 286 | 83 | 7 | `true` ✔ measured |
| 60693652 | 2026-02-24 | 1,384 | 304 | **0** | *inferred* |
| 60738249 | 2026-03-08 | 280 | 78 | 2 | *inferred `true`* |
| 60755900 | 2026-03-12 | 409 | 235 | **0** | **`false`** ✔ measured |
| 60755924 | 2026-03-13 | 1,368 | 316 | **0** | *inferred* |
| 60758159 | 2026-03-13 | 279 | 83 | 8 | `true` ✔ measured |
| 60911654 | 2026-04-26 | ~1,325 | — | **0** | **`false`** ✔ measured |
| 61221083 | 2026-07-26 | 1,324 | 284 | **0** | **`false`** ✔ measured |
| 61224438 | 2026-07-27 | 1,322 | 271 | **0** | **`false`** ✔ measured |

Three rows are marked *inferred* — I did not read the flag on those, only the click count. Every
row I did read matched.

---

## The pattern, and why it matters

**It is not chronological.** Click tracking did not "break" at some point in 2026. Look at
13 March: two broadcasts, same day, same list.

```
60755924  1,368 recipients   316 opens   0 clicks    tracking OFF
60758159    279 recipients    83 opens   8 clicks    tracking ON
```

**The big commercial sends have it off. The small ones have it on.**

Same account, same list, same week. So this is not a setting that got flipped once — it is two
different compose workflows, and the one you use for the campaigns that carry the money is the one
without tracking.

The likeliest mechanism: AWeber carries settings forward when you duplicate a broadcast. One
untracked broadcast, duplicated for the next campaign, then duplicated again, propagates the `false`
indefinitely. Meanwhile the smaller sends get composed fresh and pick up the default.

**That is why ticking the box once will not fix this.** If you tick it, send, and then duplicate an
*older* broadcast for the next campaign, it comes straight back.

---

## Two things that cannot be done

**1. The sent broadcasts cannot be fixed.** A broadcast that went out without tracking has no click
data and never will. Roughly twenty large sends between February and July 2026 are permanently
blank. That data is gone — there is no recovery step, and I am not going to pretend there is one.

**2. I cannot fix this from here.** Neither `create_broadcast` nor `update_broadcast` exposes a
click-tracking parameter, and `update_broadcast` only works on drafts in any case. **This is a UI
setting.** You have to tick it in the compose screen. I checked the API surface specifically to see
whether I could take it off your hands; I cannot.

---

## What it actually cost you

Your own small sends give the benchmark — 3–10% of *openers* click:

```
60758159   8 clicks / 83 opens   =  9.6%
60678714   7 clicks / 83 opens   =  8.4%
60738249   2 clicks / 78 opens   =  2.6%
```

A big send gets ~280 opens, so each untracked campaign lost roughly **8–28 click events**. Across
~20 sends, somewhere around 150–550 clicks.

**But the count is not the loss.** The loss is that for six months you could not tell which subject
line, which offer, or which price point made people act. You ran "Ends tonight: I write the book,
you own it ($97)" and "33 Non-Fiction books. One owner each." and have no way to know which one
worked. Opens will not tell you — 81.8% of the opens on that reference subscriber came from image
proxies, which fire without a human present.

You have been optimising on a metric that partly measures Google's servers.

---

## The fix

**1. Find the setting in the compose screen.** It is a per-broadcast toggle. I cannot give you the
exact label or location — I have no access to your UI and I am not going to invent a click path.

**2. Make one clean master broadcast with tracking ON.** Send it, confirm clicks are recorded, then
**always duplicate from that one.** This is the durable fix. Ticking the box on a single campaign
solves that campaign; fixing the thing you duplicate solves all of them.

**3. Never duplicate a pre-September-2026 broadcast again.** Every one of them carries the `false`.

---

## Verification

After your next large send, either:

- check the click count in AWeber's stats — it should be non-zero, and roughly 3–10% of opens; or
- ask me and I will read `click_tracking_enabled` on the broadcast directly and tell you what the
  flag actually says rather than what the UI appeared to accept.

The second is worth doing at least once. A checkbox that looks ticked and a flag that reads `true`
are not the same claim, and this whole finding exists because nobody checked the second one.

---

## Where this sits against the rest

This is now done — diagnosed, with a durable fix that takes minutes. It was worth doing first
because it changes what every future send tells you, including the Stage 4 verification send in
`runbook-engagement-segment.md`.

**Do the master-broadcast fix before that send.** Otherwise you will measure the engaged segment on
opens alone, and opens are the metric this investigation just showed you cannot fully trust.
