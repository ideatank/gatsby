# Judgement — the last 23 broadcasts, and whether the plan works

**Date:** 2026-09-09 · AWeber API, read-only.

You asked me to do the same across the last batch of broadcasts and judge. Here it is.

---

## Verdict in one line

**The list is healthier than I said, your plan is sound, but you cannot make the cut on opens —
and the metric you need for 2026 is the one click tracking destroyed.**

---

## 1. The list is stable, not dying

Twenty-three big sends, February to July 2026:

| Metric | Value |
|---|---|
| Open rate range | **19.6% – 24.4%** |
| Mean | **~21.9%** |
| Standard deviation | ~1.4 points |
| Recipients | 1,384 → 1,322 |
| Attrition | ~2.7 per send over 27 sends |

Twenty-seven campaigns in six months — an aggressive cadence — and the open rate did not decay by a
single point. Attrition is ~62 people across the whole run.

**Correction to my earlier framing.** I described a big dead tail dragging the average down. The
stability says otherwise: this audience absorbs frequent commercial mail without degrading. That is
a functioning asset, not a corpse.

## 2. You are already running two segments

| Send type | Recipients | Open rate |
|---|---:|---:|
| Big | ~1,340 | 20–24% |
| Small | ~280 | 27–30% |
| Small (2026-04-19) | 203 | **75.4%** |
| Small (2026-03-12) | 409 | **57.5%** |

You already mail a ~280-person core separately, and it performs 1.5× better. Whatever selects that
group is doing real work. **Find out what defines it before building anything new — it may already
be the segment you are trying to construct.**

Note the two outliers at 57% and 75%. Something about those two sends worked far better than
anything else this year. Worth knowing what.

---

## 3. The finding that changes the plan: opens are not trustworthy here

Three subscribers, pulled in full:

| | dlucco@gmail.com | ghaleib@gmail.com | mark@lyfordoffice.com |
|---|---|---|---|
| Joined | 2020, **import** | 2018, **import** | 2023, **webform** |
| Messages sent | 330 | 712 | 247 |
| Unique opens | 10 (**3%**) | 313 (**44%**) | 247 (**100%**) |
| Unique clicks | **0** | **4** (0.6%) | **18** (7.3%) |
| Opened in 2026 | 0 of 27 | ~14 of 27 | 36 of 36 |
| Mail client | Gmail 82% | Gmail 59% | **Apple Mail 60%** |
| Proxy share of opens | 81.8% | 70.6% | 65.6% |

Three things fall out of this:

**A 100% open rate is not a superfan — it is Apple Mail Privacy Protection.** `mark@` opened every
one of 36 broadcasts. Apple MPP pre-fetches images on delivery whether or not a human looks. His
opens are largely machine. What proves he is real is his **18 clicks** — MPP does not click.

**Your 2020 tags are now wrong.** `ghaleib@` carries the tag `ontra_delavo_no_opens_since_january_2020`
and today shows a **44% lifetime open rate**, having opened ~14 of your 27 2026 sends. Either the
tag was wrong, or he came back, or proxy fetching started registering opens that were never really
happening. Whichever it is: **do not build a 2026 segment from 2020-era tags.**

**Clicks separate cleanly where opens do not.** Ranked by opens: mark 100%, ghaleib 44%,
dlucco 3%. Ranked by clicks: mark 18, ghaleib 4, dlucco 0. The click ordering matches intuition
about who is actually a customer. The open ordering puts a machine at the top.

**And the imports are the dead ones.** Both zero/near-zero click subscribers arrived by import.
The one with real engagement signed up through a webform. `subscription_method` is a free
discriminator sitting in the data already.

---

## 4. The trap in the plan

Your logic was: take the ~1,325 from the last send, retrieve their opens, keep the openers, leave.

The problem is step three. **Opens will hand you a segment full of Apple Mail proxies and miss
people who read without loading images.** On this sample, proxy traffic is 65–82% of all opens. You
would be building your entire migration on a metric that is majority machine.

**And the metric that works — clicks — is blank for 2026, because click tracking was off.**

That is the real cost of the checkbox, and it is bigger than the number of lost clicks. It means the
one clean signal for grading your audience does not exist for the most recent six months.

---

## 5. What to do instead

**Use 2024–2025 clicks.** They exist — tracking was on. Anyone who clicked anything in that window
is genuinely engaged, proven by an action a proxy cannot fake. That is your seed segment, and it is
better evidence than any amount of 2026 open data.

Build the segment in tiers, best evidence first:

| Tier | Definition | Evidence quality |
|---|---|---|
| **1** | Clicked anything, 2024–2025 | **Strongest.** A human acted. |
| **2** | Signed up via webform/form, any date | Strong. Chose to be there. |
| **3** | Bought something (product tags) | Strong. Paid. |
| **4** | Opened in 2026 **and** not Apple Mail | Weak but usable |
| **—** | Opened in 2026, Apple Mail only | **Discard as evidence** — likely MPP |
| **—** | Import, zero clicks ever | Do not migrate |

Tiers 1–3 are your migration list. Tier 4 is a maybe. The last two rows are why the naive
open-based cut would have failed.

**Fix click tracking before the next send** — then every future campaign adds to Tier 1 instead of
producing nothing.

---

## 6. So: yes, with three changes

Your instinct was right on all the parts that matter. The ~1,325 are the asset. Extracting them
before you leave is correct, because the engagement history does not travel. Focusing there rather
than on 249 lists is correct.

Three changes:

1. **Cut on clicks and signup method, not opens.** Opens here are majority proxy.
2. **Pull the click data from 2024–2025**, since 2026 has none.
3. **Take the unsubscribes and the consent dates too** — not just the keepers. Those are legal
   obligations and they follow the person, not the list.

---

## Confidence and limits

- The broadcast aggregates (§1, §2) are **complete** for the 23 sends listed — measured, not sampled.
- The subscriber profiles (§3) are **n = 3**. Three people cannot size your engaged segment, and I
  am not going to extrapolate a percentage from them. What they *do* establish, because each is an
  existence proof, is that a 100%-open subscriber can be a proxy artifact, that a 2020 non-opener
  tag can be wrong today, and that clicks and opens rank the same people differently. Those
  conclusions do not need a large sample; one counter-example each is enough.
- I cannot size the opener union from this API. `opens_by_subscriber` returns the top 10 per
  broadcast, mostly with `email: null`. Per-subscriber pulls are complete but ~10KB each — 1,325 of
  them is not feasible here. **That extraction belongs in AWeber's UI export.**
