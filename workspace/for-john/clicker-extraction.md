# Pulling the 2024–25 clickers — attempted, and why I stopped

**Date:** 2026-09-09 · AWeber API, read-only.

---

## Result

**The API will not give you the clicker list. I tested the method and it captures about 6%.**

I stopped rather than spend hours producing a list that is both a quarter complete and biased in
exactly the direction we agreed to avoid. Details below, plus the ten names it did confirm and the
route that actually works.

---

## Correction — the tracking went dark much earlier than I said

I told you six months. It is closer to **twenty**.

| Broadcast | Date | Recipients | Unique clicks |
|---|---|---:|---:|
| 57436775 | 2024-03-13 | 1,481 | **46** |
| 57499804 | 2024-03-24 | 1,478 | **43** |
| 57439942 | 2024-03-14 | 1,479 | **29** |
| 57835129 | 2024-05-21 | 1,468 | **27** |
| 57891840 | 2024-05-31 | 925 | **18** |
| 58506952 | 2024-09-17 | 1,461 | **10** |
| **58998523** | **2024-12-17** | **1,456** | **36** ← last tracked big send |
| 59058861 | 2025-01-01 | 1,453 | **0** |
| 59161355 | 2025-01-24 | 1,449 | **0** |
| 59242522 | 2025-02-12 | 1,428 | **0** |
| …all big sends since | | | **0** |

**Click tracking on your large sends died between 2024-12-17 and 2025-01-01.** Small sends kept it
(2025-02-14: 6 clicks on 296 recipients; 2025-02-16: 5 on 296), which is the same big/small split
found in the 2026 data — so this is one long-running workflow problem, not two separate incidents.

**The good news:** 2024 is rich. Roughly 28 tracked broadcasts, 11–46 unique clickers each. The
union is plausibly 150–250 people. **That data exists in your AWeber account.** The API just will
not hand it over.

---

## Why the API cannot do it

There is no `clicks_by_subscriber` statistic. The 21 stats returned per broadcast include
`clicks_by_link` (URLs and counts, no people) and `opens_by_subscriber` — top 10 ranked **by opens**,
which happens to carry a `total_clicks` field per row.

That field is the only per-person click data exposed, and harvesting it fails on arithmetic:

| Broadcast | Unique clickers | Named in top-10 | Capture |
|---|---:|---:|---:|
| 58998523 | 36 | 5 | 14% |
| 57436775 | **46** | **3** | **6.5%** |

Across ~28 broadcasts, with heavy overlap between them, this yields perhaps 40–60 unique names out
of 150–250 — and every one of them is drawn from the *highest-opening* subscribers, which is
precisely the Apple-MPP-inflated population we established you cannot trust.

**A biased quarter of the list is worse than no list**, because you would treat it as the answer.
So I stopped.

The alternative — `get_subscriber` on all ~1,325 people — returns complete click history but ~11KB
each. That is roughly 14MB and 1,325 round trips. Technically possible across many sessions;
not a sensible use of your time or mine when the UI can do it directly.

---

## What I did confirm — 10 clickers

In `data/confirmed-clickers-partial.csv`. Every one is a human who clicked a real link.

| Email | Name | Clicks | List |
|---|---|---:|---|
| mark@lyfordoffice.com | Mark | **18** (lifetime) | 5395369 |
| ghaleib@gmail.com | | 4 (lifetime) | 4856330 |
| brm444@msn.com | Brad Manis | 3 | 4856330 |
| softomatrix@gmail.com | | 2 | 5395369 |
| deadparrotsoftware@gmail.com | Sid B | 1 | 4853895 |
| alun@alunhill.com | Alun Hill | 1 | 5097737 |
| dirkw@imopartners.com | Dirk Wagner | 1 | 5821078 |
| victor@maat.ws | Victor Maat | 1 | 4856330 |
| jayrnet1@gmail.com | Jay Reynolds | 1 | 5395369 |
| mike.pine@gmail.com | Michael Pine | 1 | 4856330 |

Treat this as a **seed, not a segment.** It is real but nowhere near complete.

Note the list column: these ten sit on six different lists. That is more evidence the list structure
is bookkeeping, not audience design.

---

## The route that works

**AWeber's UI holds this data.** For each broadcast it can report who clicked, and it supports
building a segment on click behaviour. That is a UI export, not an API pull.

What to do:

1. In AWeber, for each 2024 broadcast with clicks, pull the **clicked** report.
2. Or better, if the segment builder supports it: build one segment for "clicked any message in
   2024" and export it once.
3. Send me the CSV. I will dedupe it, cross-reference `subscription_method` and tags, and produce
   the tiered migration list.

I cannot give you the exact menu path — no access to your UI, and I am not going to invent one.

---

## Two things worth noticing in the click data

**Your links go to third parties.** The tracked destinations were
`https://johndelavera.softr.app/vault`, `https://go.clklk.com/orderfiresale`, and
`https://aisoft.paperform.co/`. Nothing wrong with that, but a click on a redirector tells you less
than a click on your own domain, and none of it lands on `johndelavera.com` where you could measure
what happened next.

**Attrition over two and a half years is tiny.** 1,494 recipients in January 2024, 1,322 in July
2026 — 172 lost in 31 months, across roughly a hundred campaigns. Whatever else is true about this
list, people are not leaving it.

---

## Where this leaves the plan

Unchanged in shape, blocked on one input. Tier 1 is still "clicked in 2024–25", the data still
exists, and it is still the right basis for the cut. It has to come out of the UI, not from here.

Everything downstream — dedupe, tiering, tagging via `update_subscriber`, the verification
counts — I can do the moment you hand me the export.
