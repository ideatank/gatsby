# Exporting from AWeber — what to pull, and which lists

**Date:** 2026-09-09

I have no access to your AWeber UI and its labels change between releases, so **this does not give
you a click path.** It gives you the three things a click path cannot: which lists to export, what
each export must contain, and how to prove it came out complete.

---

## First — the job is 8 lists, not 140

This is the useful part. Your broadcasts fan across many lists, but the recipients are concentrated.
Tracing `subscriber_link` values back from the broadcast stats, these are the lists that actually
receive your sends:

| List ID | Name | Subscribed |
|---|---|---:|
| 5821078 | John Delavera's List | 394 |
| 5395369 | VIP Active Turbo People | 265 |
| 5750707 | Feedback - Courses platform | 182 |
| 4856330 | ontra_delavo | 174 |
| 5097737 | John Delavera's Subscribers | 140 |
| 4863899 | ontra_delavo_NOT | 66 |
| 4854102 | ontra_jdvip_and_friends | 47 |
| 5206191 | Turbo PLR customers | 47 |
| 4853895 | ontra_turboplr_plrsoft | 43 |
| 4856257 | ontra_vip | 40 |
| 4309716 | members_monthly_8888 | 38 |
| 5544637 | DELAVO Bonus | 14 |

**Top five alone = 1,155 memberships.** All twelve = ~1,450 before dedupe, against ~1,325 actual
recipients — the difference is people on more than one list.

**Export these twelve. Ignore the other 237.** If AWeber's export is per-list (which it appears to
be, since segments are), that is twelve exports, not one hundred and forty.

---

## What each export must contain

Do this once, properly. Re-exporting later because a column was missing is the avoidable failure.

| Field | Why it matters |
|---|---|
| Email | — |
| Name | — |
| **Subscribe date** | Your proof of consent. Cannot be reconstructed after you leave. |
| **Subscription method** | `webform` vs `import` — the discriminator that separated your engaged subscriber from your dead ones. |
| **Tags** | The only record of what each person bought. |
| Last open date | Lets you re-segment later without redoing this. |
| **Click history / last click** | Tier 1 evidence, if the export offers it. |
| **Status** | So unsubscribes come across as unsubscribes. |

**And export the unsubscribed, not only the subscribed.** If your export tool has a status filter,
run it twice per list — once for subscribed, once for unsubscribed. The opt-outs are a legal
obligation that follows the person; leaving them behind means JD Mail will eventually mail someone
who told you to stop.

---

## The three things to look for in the UI

I can describe the capabilities; you match them to whatever the screens are called now.

**1. A subscriber export.** Somewhere in the Subscribers area. AWeber has historically delivered
exports as a file by email rather than an instant download, so expect a wait and check your inbox.
Note which columns it offers — that determines whether you get click history here or need step 2.

**2. A broadcast's "clicked" report.** Each sent broadcast's stats should let you see the
subscribers who clicked, not just the count. That is the Tier 1 list. The 2024 broadcasts are the
ones worth pulling — see the table below.

**3. A segment / saved search on click behaviour.** If the segment builder can express "clicked any
message since 2024-01-01", build it once and export the segment instead of doing 28 separate
broadcast reports. **Try this first** — it collapses the whole job.

---

## The 2024 broadcasts worth pulling, if you have to do it one at a time

Highest click counts first, so you capture most of the pool in the fewest reports:

| Broadcast | Date | Clickers |
|---|---|---:|
| 57436775 | 2024-03-13 | 46 |
| 57499804 | 2024-03-24 | 43 |
| 58998523 | 2024-12-17 | 36 |
| 57439942 | 2024-03-14 | 29 |
| 57436483 | 2024-03-13 | 27 |
| 57835129 | 2024-05-21 | 27 |
| 57396772 | 2024-03-07 | 26 |
| 57318550 | 2024-02-24 | 25 |
| 57110694 | 2024-01-18 | 23 |
| 57299506 | 2024-02-20 | 23 |
| 57177886 | 2024-01-30 | 22 |
| 57434167 | 2024-03-13 | 21 |

Twelve reports ≈ 348 click events. With overlap that is likely 150–200 unique people — most of the
pool. There are another ~16 tracked 2024 broadcasts at 10–20 clickers each if you want the tail.

---

## How to prove the export is complete

Do not trust a file because it downloaded. Send me the CSV and I will check it against the API:

- **Row counts per list** must match the subscriber counts in the table above (394, 265, 182 …).
  A short file means a filter was applied that you did not intend.
- **Unsubscribes present?** List 5821078 alone should carry ~65.
- **Known clickers present?** `mark@lyfordoffice.com` (18 clicks), `brm444@msn.com` (3),
  `alun@alunhill.com` (1) are confirmed. If your click export omits them, the filter is wrong.
- **Known dead present in the full export?** `dlucco@gmail.com` — 330 sends, 10 opens, 0 clicks.
  He should appear in the *subscriber* export and be absent from the *clicker* export. If he shows
  up as a clicker, the segment is broken.

That last pair is the real test. One known-good and one known-bad address catches almost every way
an export can silently go wrong.

---

## One alternative worth ten minutes

The AWeber API surface I have here lacks any per-subscriber click endpoint — that is measured, not
assumed. But this is an MCP wrapper, not necessarily all of AWeber's public API. Their developer
docs may expose broadcast click detail that this tool does not surface. If you already have API
credentials, it is worth a look before committing to 12–28 manual reports.

I cannot check that from here — I only see what this integration exposes.

---

## Then hand it back

Send the CSVs and I will dedupe across the twelve lists, tier by click history, subscription method
and purchase tags, produce the migration list with counts per tier, and apply the tags through
`update_subscriber`. All of that is unblocked the moment the export exists.
