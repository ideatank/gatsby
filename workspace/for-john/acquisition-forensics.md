# Where the signups came from — and why they stopped

**Date:** 2026-09-09 · Full account export, 1,786 distinct people ever, active + unsubscribed.

---

## The 2020 "peak" was not acquisition. It was an import.

I showed you a chart with 442 new subscribers in 2020 and called it the year your list grew. That
reading was wrong, and the date stamps prove it:

| List | People added | On these days |
|---|---:|---|
| **John Delavera's List** | 236 + 223 = **459** | **2020-10-20 and 2020-10-21** |
| **Feedback - Courses platform** | **279** | **2020-09-06** — a single day |
| ontra_* (7 lists) | 168 / 113 / 72 / 57 / 51 | 2018-05-29, 2017-11-10, 2017-11-01… |

459 people in two days. 279 in one. That is a bulk load, not a signup curve.

Corroborating: of the 581 people dated 2020, **577 have no source URL and no add method**, and they
carry tags like `use-sell-member`, `delavo_lifetime_use-sell`, `delavo_lifetime_moosend`. The
`ontra_*` list names are Ontraport. The `moosend` tag is Moosend.

**You did not build this list in 2020. You moved it into AWeber in 2020** — from Ontraport (2017–18)
and from whatever held the use-sell/DELAVO buyers (2020).

That reframes everything. The list was never growing and then stopped. It was assembled from
elsewhere, and organic acquisition has always been small.

---

## What genuine acquisition actually looks like here

Signups with a real recorded source, all time:

**2019 — affiliate traffic, the only channel that ever produced a run:**
```
29 signups from internetmarketingchronicles.com, each carrying ?bb_aff=<code>
    IluVyJkAj0cy · kRClEL2NPwpn · ksKSy1PuS9Fp · RdHCOy6H2Aiz · 0xnh2Qt5M8 · EEQ0zmANFC …
landing on: Internet Marketing Chronicles (16), Chronicles 2 (13)
```
Multiple distinct affiliate codes, over months. **Someone else's audience was sending you people.**
It is the only channel in eleven years that shows a repeatable pattern.

**2021 onward — your own site, in a trickle:**
```
28  johndelavera.com + www.johndelavera.com
 5  turboplr.com
 4  turbo-gpt.com
 2  turbo.aweb.page  (AWeber-hosted landing page)
 1  turboebooks.com
```
**Forty signups in five and a half years** from every property you own, combined.

**Everything else — roughly 1,550 people — has no recorded source and arrived in dated batches.**

---

## THE LIVE BUG — your signup form loses people the same day

This is the finding worth acting on. The 2024 cohort: 28 signups, **22 unsubscribed (78.6%)**.

Look at when they left:

```
added 2024-02-17   unsub 2024-02-17    same day
added 2024-03-02   unsub 2024-03-02    same day
added 2024-04-29   unsub 2024-04-29    same day
added 2024-05-08   unsub 2024-05-08    same day
added 2024-05-10   unsub 2024-05-10    same day
added 2024-05-12   unsub 2024-05-12    same day
added 2024-06-06   unsub 2024-06-06    same day
added 2024-07-31   unsub 2024-07-31    same day
added 2024-08-01   unsub 2024-08-01    same day
added 2024-08-02   unsub 2024-08-02    same day
added 2024-08-05   unsub 2024-08-05    same day  ×4
added 2024-08-27   unsub 2024-08-27    same day
```

**Fourteen of the twenty-two unsubscribed on the day they signed up.** Every one via `my_web_form`,
all onto *John Delavera's Subscribers*.

Normal churn does not look like this. Same-day opt-out at 50% of a cohort means one of three things:

1. **Whatever they receive on signup drives them straight off** — wrong content, wrong expectation,
   or a welcome sequence that misfires.
2. **The form is collecting people who did not mean to subscribe** — a checkout or download flow
   that opts them in as a side effect, so their first act is to leave.
3. **Automated submissions.** Four on 2024-08-05 alone is a plausible bot signature.

I cannot tell which from the export — the distinguishing evidence is in the form itself and in what
gets sent on signup. But whichever it is, **the one acquisition path you still have is leaking at
the moment of conversion**, and it has been for at least two years.

Fixing that is worth more than everything else in this folder. You do not have a traffic problem
first — you have a problem converting the trickle you already get.

---

## Churn by cohort

| Year | Signups | Still active | Unsubscribed | Churn |
|---|---:|---:|---:|---:|
| 2016 | 176 | 131 | 45 | 25.6% |
| 2017 | 367 | 240 | 127 | 34.6% |
| 2018 | 186 | 149 | 37 | 19.9% |
| 2019 | 241 | 199 | 42 | 17.4% |
| 2020 | 581 | 437 | 144 | 24.8% |
| 2021 | 19 | 9 | 10 | **52.6%** |
| 2022 | 144 | 119 | 25 | 17.4% |
| 2023 | 40 | 33 | 7 | 17.5% |
| **2024** | **28** | **6** | **22** | **78.6%** |
| 2025 | 4 | 3 | 1 | 25.0% |

The imported cohorts (2016–2020) churn at 17–35% over five-plus years, which is unremarkable. The
recent form-driven cohorts churn at 52% and 78% within months. **The people you import stay. The
people who find you leave.**

That is exactly backwards from how it should work, and it points at the same place: what happens
immediately after someone signs up.

---

## What I would do

1. **Sign up to your own form.** Use an address you control, and watch what arrives, in what order,
   with what subject line, over the first 24 hours. Fourteen same-day unsubscribes say something in
   that sequence is wrong, and this is a ten-minute test.
2. **Check whether anything opts people in as a side effect** — a purchase flow, a download gate, a
   coupon page. Involuntary subscribers unsubscribe immediately and complain eventually.
3. **Add a honeypot or captcha** if the August 2024 clustering turns out to be automated.
4. **Then look at the affiliate channel.** `bb_aff` codes on internetmarketingchronicles.com are the
   only thing in eleven years that produced repeatable inbound signups. It ran in 2019 and stopped.
   Finding out why is a better use of a week than any migration task.

---

## What this means for the JD Mail project

Nothing in this changes the migration plan — but it changes what the plan is *for*.

You are moving a 1,339-person list, assembled by import between 2017 and 2020, that has gained
roughly 40 people through your own properties in five years and currently loses half of new signups
within a day. Moving it to JD Mail saves a subscription fee and gives you control.

**It does not make the list grow.** Only fixing the signup path and reviving a traffic source does
that, and neither is an email-infrastructure problem.
