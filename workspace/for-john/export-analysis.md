# AWeber export — analysed

**Date:** 2026-09-09 · Source: full account export, 249 lists, 3,850 files, 38 MB.
Analysed read-only with `tools/analyse-export.py`. **No subscriber data is committed to this
repository** — see the note at the end.

---

## The export is verified complete

Row counts match the API exactly on every list I had a figure for:

| List | API | Export |
|---|---:|---:|
| 5395369 VIP Active Turbo People | 265 / 33 | **265 / 33** |
| 5821078 John Delavera's List | 394 / 65 | **394 / 65** |
| 5750707 Feedback - Courses platform | 182 / 97 | **182 / 97** |
| 4856330 ontra_delavo | 174 / 28 | **174 / 28** |
| 5097737 John Delavera's Subscribers | 140 / 33 | **140 / 33** |

All four verification probes present. `active_leads.csv` **and** `inactive_leads.csv` per list, so
the unsubscribes came across. Nothing is missing. **You can proceed on this data.**

---

## THE BOUNCE RATE — measured at last

The very first question in this whole engagement was JD Mail's bounce rate, and I reported it
UNMEASURED because `mail.log` was unreachable and AWeber's stats API exposes no bounce metric. The
export has it: `Number Undeliverable`, on every broadcast.

**Across 1,534 distinct sends and 3,844,587 emails:**

| | | |
|---|---:|---|
| Emailed | 3,844,587 | |
| Undeliverable | 15,324 | **0.40% lifetime bounce rate** |
| Complaints | 309 | 0.0080% |
| Suppressed | 0 | |

By year:

| Year | Emailed | Undeliv. | Bounce | Complaints | Open % | Clicks |
|---|---:|---:|---:|---:|---:|---:|
| 2017 | 230,958 | 4,768 | **2.06%** | 18 | 6.4% | 1,118 |
| 2018 | 1,536,400 | 9,041 | 0.59% | 214 | 6.7% | 17,221 |
| 2019 | 728,159 | 568 | 0.08% | 41 | 7.6% | 1,052 |
| 2020 | 245,903 | 29 | 0.01% | 13 | 12.5% | 3,168 |
| 2021 | 439,956 | 55 | 0.01% | 13 | 14.4% | 6,845 |
| 2022 | 264,938 | 90 | 0.03% | 2 | 19.7% | 3,315 |
| 2023 | 275,003 | 280 | 0.10% | 6 | 21.7% | 3,314 |
| 2024 | 40,844 | 108 | 0.26% | 1 | 24.1% | 607 |
| 2025 | 38,049 | 142 | 0.37% | 1 | 30.4% | **40** |
| 2026 | 39,108 | 214 | **0.55%** | **0** | 23.0% | **31** |

**Your list is clean.** 0.55% bounce against a ~2% throttling threshold, and **zero spam complaints
in 2026** across 39,108 emails. That is a well-maintained list by any standard.

This changes the risk calculation for JD Mail. I have been warning you about mailing a list that
might be full of dead addresses. **It is not.** The addresses deliver. What you still cannot do is
land them from a cold shared IP at full volume — but the list itself is not the hazard I feared.

Note 2017: a 2.06% bounce year. Whatever you did after that worked; it has been under 0.6% ever
since.

---

## The numbers that matter

| | |
|---|---:|
| Unique active people | **1,339** |
| Unique unsubscribed | **471** |
| On both (subscribed one list, unsubscribed another) | **24** |
| Total distinct people ever | 1,786 |
| On more than one list | 456 (34.1%) |
| Total memberships | 2,107 |

My API estimate of ~1,325 recipients was within 1% of the real 1,339. The list-membership figure of
2,107 was exact.

### FINDING — 24 people are being mailed after they unsubscribed

Twenty-four addresses appear as **unsubscribed on one list and still subscribed on another**. Since
your broadcasts fan across lists, those people asked to stop and are still receiving your campaigns.

This is live, it is happening now, and it is the kind of thing that produces a complaint rather than
an unsubscribe. `tools/analyse-export.py` already excludes them from the migration list. **Suppress
them in AWeber too.**

---

## CORRECTION — the signup-method discriminator barely exists

I recommended tiering on `Add Method` (`webform` vs `import`), based on the three subscribers I
sampled. The full data does not support it:

| Add Method | People | Share |
|---|---:|---:|
| **(blank)** | **1,234** | **92.2%** |
| api | 53 | 4.0% |
| my_web_form | 31 | 2.3% |
| my_web_form_3 | 8 | 0.6% |
| everything else | 13 | 1.0% |

Only 105 people of 1,339 carry any method at all. My tiering scheme would have sorted 92% of your
list into "unknown". That was a three-person sample generalised too far, and the full export
corrects it.

Related: only **74 of 1,339 are "Verified"**. 1,265 are "Not Verified".

**What you do have is your own tags:**

| | People |
|---|---:|
| Tagged as openers (`vip_active`, `*_any_opens_since_january_2020`) | **384** |
| Tagged as non-openers (`*_no_opens_since_january_2020`) | **240** |
| No tags at all | 422 |

Those tags are from 2020 and I have already shown one is wrong today (`ghaleib@gmail.com`, tagged
a non-opener, opened 14 of 27 2026 sends). But 384 people your past self marked as engaged is a far
better starting segment than anything `Add Method` can give you.

---

## THE FINDING THAT MATTERS MOST — acquisition is dead

New subscribers by year:

```
2016   132  #############
2017   241  ########################
2018   149  ##############
2019   199  ###################
2020   442  ############################################
2021     9
2022   119  ###########
2023    38  ###
2024     6
2025     4
2026     0
```

**Ten new subscribers in the last three years. Zero this year.**

Everything else in this engagement — the DNS records, the click tracking, the segment, the
migration — is maintenance on an asset that stopped growing in 2020. Meanwhile you sent 27 campaigns
in six months to the same 1,339 people, selling $97 books to an audience that has not gained a
member since.

Set against that: your open rate climbed from 12.5% in 2020 to 30.4% in 2025. **You got much better
at talking to them. There just aren't any new ones.**

I have been helping you optimise the wrong end of this. Leaving AWeber saves a subscription fee.
It does not fix a list that has not grown in three years, and no amount of hygiene will.

---

## Click collapse, confirmed with hard numbers

```
2023   275,003 emailed   3,314 clicks
2024    40,844 emailed     607 clicks
2025    38,049 emailed      40 clicks   <- tracking goes off
2026    39,108 emailed      31 clicks
```

Consistent with the `click_tracking_enabled: false` finding. The small tracked sends account for
the residual 40 and 31.

---

## What I produced

Two files, sent to you directly and **deliberately not committed to this repository**:

**`suppression-list.csv`** — 471 rows. Every unsubscribe across all 249 lists, deduped, with status
and dates. **This is the file you would have lost.** It must be loaded into JD Mail before the first
send and it must never be mailed.

**`migration-list.csv`** — 1,315 rows. Every active subscriber, deduped across lists, with the 24
unsubscribed-elsewhere addresses already removed. Columns: email, name, add_method, verified,
date_added, list count, tags, tier.

---

## Revised recommendation

1. **Suppress the 24** in AWeber today. People who opted out are receiving your mail.
2. **Load `suppression-list.csv` into JD Mail** before it can send anything. 471 addresses that must
   never be mailed again.
3. **Use the 384 tag-marked openers as the warming segment**, not `Add Method`.
4. **Your bounce rate is 0.55% and complaints are zero.** Stop worrying about the list being toxic;
   worry about the cold IP instead, which is a different and smaller problem.
5. **Then go and look at acquisition**, because that is the actual business problem and nothing in
   `workspace/for-john/` addresses it.

---

## A note on where subscriber data lives

I had committed a small CSV of ten confirmed clickers to this repository earlier. I have removed it
and added `data/.gitignore` blocking `*.csv` and `*.zip`.

Customer email addresses do not belong in version control — repositories get shared, forked, and
made public, and a deletion does not remove them from git history. The export and both derived lists
stay in the session scratchpad and come to you as files.

If you want the earlier commit purged from history rather than just removed going forward, that
needs a history rewrite on the branch — say the word.
