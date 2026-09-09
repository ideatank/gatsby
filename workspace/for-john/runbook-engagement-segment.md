# Runbook — build the engaged segment, send to it, keep the rest

**Written 2026-09-09.** Operating procedure for AWeber. Execute in order. Each stage has a
verification step and a rollback. Do not skip a verification because the previous stage "obviously"
worked.

---

## What this does

Builds a positive inclusion segment of people who have opened at least one email in 2026, and sends
to that instead of to everyone.

**What it does not do:** it does not unsubscribe anyone, delete anyone, or touch anyone outside the
tag. The non-engaged remain exactly as they are, still subscribed, still reachable if you change
your mind. Nothing here is irreversible before Stage 5, and Stage 5 is optional.

## Why inclusion, not suppression

An exclusion rule that fails mails **everyone** — the outcome you are preventing. An inclusion rule
that fails mails **nobody**. Same target, opposite failure mode. Always build the rule whose
failure is quiet.

---

## Things I could not verify — check these yourself

I have read-only visibility into your AWeber data and none into its UI or list settings. Treat the
following as unverified:

1. **Exact menu labels and click paths.** AWeber's UI changes; I am not guessing at it below.
2. **Whether adding a subscriber to a new list triggers a confirmation email.** This depends on that
   list's opt-in settings. **Stage 0 exists solely to test this.** Get it wrong at scale and you
   send 400 unexpected "please confirm" emails to the only people still reading you.
3. **Whether AWeber segments can span lists.** My understanding is they are per-list. If that holds,
   and your broadcasts fan across many lists, you need either per-list segments or one consolidated
   list. Confirm before choosing a path at Stage 2.

---

## Baseline — the numbers you are starting from

| | |
|---|---:|
| Recipients per broadcast | ~1,325 |
| Typical unique opens | 264–310 (**~21%**) |
| Unique clicks recorded | **0** (see Stage 6) |
| Lists in account / non-empty | 249 / 140 |
| Total list memberships | 2,107 |
| Broadcasts sent in 2026 (Feb 12 – Jul 27) | **27** |

Reference case — a real subscriber on your list today:

```
imported 2020-10-21, never opted in directly
messages_sent : 330      unique_opens : 10 (3%)
unique_clicks : 0        revenue      : 0
81.8% of those opens came from image proxies, not humans
```

**Success target:** first send to the segment should show **45–60% opens**. If it does not, stop and
re-read Stage 4's abort conditions.

---

## Stage 0 — the single-address test  *(15 minutes, do not skip)*

**Purpose:** find out what AWeber does when you add or tag someone, before doing it 400 times.

1. Pick an address you control that is **not** already on the target list.
2. Do exactly what you plan to do at scale — add it to the new list, or apply the tag.
3. **Watch that inbox for five minutes.**

**Verify:** did a confirmation email arrive? Did anything at all arrive?

- **Nothing arrived** → the mechanism is silent. Proceed.
- **A confirmation email arrived** → **stop.** Bulk-adding will spam your best subscribers. Switch
  to the tagging path (Stage 2, Path A), which modifies existing records rather than creating new
  subscriptions, or change the list's opt-in setting first and re-test.

**Rollback:** remove the test address. Nothing else has been touched.

**Record:** `Stage 0 run at ______ · confirmation email sent? YES / NO · path chosen: A / B`

---

## Stage 1 — collect the openers

**Definition of engaged, first pass: opened *any* broadcast between 2026-02-12 and 2026-07-27.**

Be generous on the first cut. Someone who opened none of 27 emails across six months is
unambiguously disengaged; you do not need a tighter test, and a tighter test costs you revenue.
You can always narrow later. You cannot un-lose a customer you cut by mistake.

Your 2026 broadcasts, newest first:

| ID | Date | Subject |
|---|---|---|
| 61224438 | 07-27 | Storefront now Included /// Re: 33 Non-Fiction books |
| 61221083 | 07-26 | 33 Non-Fiction books. One owner each. |
| 61126245 | 06-27 | Just added: over 100 to choose from (coupon) |
| 61109807 | 06-23 | Thank you — and a few hours left |
| 61107335 | 06-22 | Ends tonight: I write the book, you own it ($97) |
| 61104435 | 06-21 | Half price until Monday — your name on a real book |
| 61071637 | 06-11 | Re: closes tonight (this is the last reminder) |
| 61067498 | 06-10 | Re: now it's 23 |
| 61060012 | 06-08 | Re: I extended it — here's why |
| 61055073 | 06-06 | I built you a NEW author page. Now pick the books |
| 60984622 | 05-17 | A coupon shaped like a Sunday |
| 60943341 | 05-05 | JASM Newsletter #1 — bundle deals. Today only |
| 60933711 | 05-02 | Happy Weekend: Coupons and Buy 2 get 1 Free |
| 60911654 | 04-26 | My Dear Diary: 17:15 — Sunday, April 26, 2026 |
| 60886600 | 04-19 | Nobody has seen this yet. Not even Charles. |
| 60836128 | 04-04 | JASM bonus and offer expire in some hours |
| 60832999 | 04-03 | I forgot the order link. It was 3 AM. |
| 60831598 | 04-02 | A marketer dies. His autoresponder keeps sending. |
| 60771157 | 03-17 | Tonight at midnight, JASM's deal closes |
| 60765188 | 03-16 | I wrote this NON-FICTION book in under 1 hour |
| 60762860 | 03-15 | My fault... |
| 60755924 | 03-13 | Something new and BIG is coming. For 10 Founders |
| 60693652 | 02-24 | You get $197 worth of extras to every $97 order |
| 60666572 | 02-16 | 6 titles are gone. 22 left |
| 60659002 | 02-14 | Ready? Quick Update...28 instead of 14 |
| 60656758 | 02-13 | I made it easier. 14 stories. Pick one. |
| 60655029 | 02-12 | 2 books this week on Amazon. Want yours? |

*Caveat: this table was reconstructed from one subscriber's message history. If that person did not
receive a given 2026 broadcast, it will be missing here. Cross-check against your broadcast list in
the UI.*

**Shortcut worth trying first:** pulling openers from 27 broadcasts by hand is a long afternoon. The
five most recent sends alone will capture most of the genuinely active audience — a regular opener
rarely misses five in a row. Start with the top five rows; add older ones only if the resulting
count comes in under ~300.

**Verify:** dedupe the addresses. Expected union: **350–450**.

- **Under 250** → you are cutting too deep. Add more broadcasts from the table.
- **Over 600** → check you have not accidentally captured "sent" rather than "opened".

**Record:** `Broadcasts used: ______ · unique openers: ______ · collected on ______`

---

## Stage 2 — tag them

Choose one path based on what Stage 0 told you.

### Path A — tag in place *(default; use this if Stage 0 showed a confirmation email, or if you are unsure)*

Apply tag **`engaged_2026`** to each opener, on the list they already belong to. This modifies an
existing record. It creates no new subscription and triggers no confirmation email.

I can do this for you via the API — `update_subscriber` with `tags_add`, one call per subscriber.
Give me the addresses and their list IDs and I will apply the tag and report the count applied,
the count skipped, and any failures.

Cost: AWeber segments appear to be per-list, so you may need one segment definition per list that
holds engaged people. Tedious, but it risks nothing.

### Path B — consolidate onto one list *(only if Stage 0 was silent)*

Create a list named **`Engaged 2026`** and add the openers to it. Cleaner to send to afterwards;
one segment, one target.

**Do not use "move".** Moving removes people from their source list, which destroys the product and
purchase history encoded in your list structure. Add, do not move.

**Verify (both paths):** re-count the tag or the new list. It must match your Stage 1 union. A
mismatch means the operation partially failed — find out which addresses are missing before
proceeding.

**Rollback:** Path A — remove the tag (`tags_remove`). Path B — delete the new list. Neither touches
the original subscriptions.

**Record:** `Path: A / B · attempted: ______ · applied: ______ · failed: ______`

---

## Stage 3 — verify the segment before you send to it

Do all three:

1. **Count** matches Stage 1 within a handful. If not, stop.
2. **Spot-check five addresses** in the segment. Each must show a real open in 2026. If any shows
   zero 2026 opens, your collection at Stage 1 was wrong — go back.
3. **Spot-check that a known dead address is NOT in it.** Use `dlucco@gmail.com` — 330 sends, 10
   lifetime opens, none in 2026. If that address is in your engaged segment, the segment is wrong.

That third check is the one that matters. It is cheap, and it is the one that catches a filter
that silently matched everybody.

**Record:** `Count OK ___ · 5 spot-checks passed ___ · dlucco@gmail.com absent ___`

---

## Stage 4 — the first clean send

Send **one** real campaign to the segment only. Not a test, not a "hello" — something you would
have sent anyway, so the result is comparable to your baseline.

**Verify against baseline (~21%):**

| Result | Reading | Action |
|---|---|---|
| **45–60%+ opens** | Working as expected | Proceed to Stage 5 |
| **30–45%** | Working, tail was smaller than modelled | Proceed; consider a tighter window later |
| **~21%, unchanged** | Segment is not filtering — you are still mailing everyone | **Stop.** Re-do Stage 3. |
| **Under 15%** | Something is wrong with delivery, not with the segment | **Stop.** Do not send again until diagnosed. |

**Abort conditions — stop immediately at any of these:**
- Open rate at or below your ~21% baseline
- A spike in unsubscribes versus a normal send
- Any bounce or complaint notice from AWeber

**Rollback:** none needed — you have sent one campaign to a subset of people who already wanted your
mail. The worst case is that it did not help.

**Record:** `Sent ______ · recipients ______ · opens ______ (___%) · unsubs ______`

---

## Stage 5 — the tail  *(optional, and only after 2–3 good sends)*

Do not touch the non-engaged until Stage 4 has succeeded at least twice. There is no hurry: as long
as you are only mailing the segment, the tail is already doing no harm.

When you do decide, in order of increasing finality:

1. **Leave them.** Tagged, untouched, not mailed. Costs nothing. Recommended indefinitely.
2. **One re-engagement send**, to the tail only, from AWeber — never from JD Mail. One email:
   *"Still want these? Click here."* Anyone who clicks joins `engaged_2026`. Expect 1–3%.
3. **Unsubscribe the non-responders.** **One-way door.** Once unsubscribed you cannot re-add them
   without fresh consent. Only after step 2, and only if you have decided the list will never be
   worth reactivating.

**Never delete.** Deletion destroys the record of who they were and when they joined — which is the
evidence you would need if a complaint ever landed.

---

## Stage 6 — separate, and more urgent than Stage 5

**Fix click tracking.** All five recent broadcasts report `unique_clicks: 0`, while your 2020
broadcasts recorded 31, 56 and 66. Something between then and now stopped recording clicks.

271 people opened a $97 offer and the system logged no clicks. Opens are inflated by image proxies —
81.8% of that reference subscriber's opens were proxies. **Clicks are the metric that tracks money,
and you do not currently have it.**

This is independent of the segment work and can be done in parallel. It is worth more than
Stage 5.

---

## Order of operations

```
Stage 0  test one address              15 min      today
Stage 6  fix click tracking            unknown     today, in parallel
Stage 1  collect openers               1-2 hrs     this week
Stage 2  tag them                      automatable
Stage 3  verify  ← the gate
Stage 4  one clean send                            next campaign
         ... 2-3 more sends ...
Stage 5  decide about the tail                     next month, or never
```

**Do not run Stages 1–4 in one sitting.** Stage 3 is a gate, not a formality. If it fails, the
whole thing stops there, and that is the runbook working.

---

## What to hand back

After Stage 4, the record should read:

```
Stage 0  confirmation email: YES/NO      path chosen: A/B
Stage 1  broadcasts used: ___            unique openers: ___
Stage 2  applied: ___  failed: ___
Stage 3  count OK ___  spot-checks ___   dlucco absent ___
Stage 4  recipients ___  opens ___ (__%)  vs baseline 21%
```

That is five lines and it is the whole result. If Stage 4's percentage is not roughly double the
baseline, the operation did not do what it was supposed to — and the record is how you will know
that rather than assume it.
