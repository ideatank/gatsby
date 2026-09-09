#!/usr/bin/env python3
"""
analyse-export.py - aggregate an AWeber full-account export.

READ ONLY. Reads the unzipped export tree, writes nothing back into it.
Produces aggregate counts plus two derived lists (suppression, tiers) into an
output directory you choose - NEVER into a git repository, since the rows are
personal data.

Usage:
    python3 analyse-export.py <export_dir> [--out <dir>]
"""
import csv, os, sys, glob, re
from collections import Counter, defaultdict

if len(sys.argv) < 2:
    sys.exit("Usage: analyse-export.py <export_dir> [--out <dir>]")

root = sys.argv[1]
out = None
if "--out" in sys.argv:
    out = sys.argv[sys.argv.index("--out") + 1]

def read_csv(path):
    """AWeber exports are not reliably UTF-8; fall back rather than crash."""
    for enc in ("utf-8-sig", "utf-8", "latin-1"):
        try:
            with open(path, newline="", encoding=enc) as fh:
                return list(csv.DictReader(fh))
        except UnicodeDecodeError:
            continue
    return []

lists = sorted(d for d in glob.glob(os.path.join(root, "awlist*")) if os.path.isdir(d))

active   = {}                      # email -> record (first seen wins)
inactive = {}                      # email -> record
memberships = Counter()            # email -> number of lists
per_list = []                      # (id, name, active, inactive)
add_method = Counter()
verified   = Counter()
tags       = Counter()
years      = Counter()

bc_rows = 0
bc = Counter()                     # emailed / undeliverable / complaints / suppressed / opens / clicks
bc_by_year = defaultdict(Counter)
seen_bc = set()                    # dedupe: same broadcast is filed under several lists

for d in lists:
    base = os.path.basename(d)
    m = re.match(r"awlist(\d+)_(.*)", base)
    lid, lname = (m.group(1), m.group(2)) if m else (base, base)

    a = read_csv(os.path.join(d, "leads", "active_leads.csv"))
    i = read_csv(os.path.join(d, "leads", "inactive_leads.csv"))
    per_list.append((lid, lname, len(a), len(i)))

    for r in a:
        e = (r.get("Email") or "").strip().lower()
        if not e:
            continue
        memberships[e] += 1
        if e not in active:
            active[e] = r
            add_method[(r.get("Add Method") or "(blank)").strip()] += 1
            verified[(r.get("Verified") or "(blank)").strip()] += 1
            da = (r.get("Date Added") or "")[:4]
            if da.isdigit():
                years[da] += 1
            for t in (r.get("tags") or "").split(","):
                t = t.strip()
                if t:
                    tags[t] += 1

    for r in i:
        e = (r.get("Email") or "").strip().lower()
        if e and e not in inactive:
            inactive[e] = r

    # broadcast summaries
    p = os.path.join(d, "broadcasts", "broadcast_summary.csv")
    if os.path.exists(p):
        for r in read_csv(p):
            key = (r.get("Date Sent"), r.get("Subject"), r.get("Number Emailed"))
            if key in seen_bc or not r.get("Date Sent"):
                continue
            seen_bc.add(key)
            def n(f):
                v = (r.get(f) or "0").strip()
                return int(v) if v.lstrip("-").isdigit() else 0
            bc_rows += 1
            yr = (r.get("Date Sent") or "")[:4]
            for f, k in (("Number Emailed", "emailed"),
                         ("Number Undeliverable", "undeliverable"),
                         ("Number of Complaints", "complaints"),
                         ("Number of Suppressed", "suppressed"),
                         ("Unique Opens", "opens"),
                         ("Unique Clicks", "clicks")):
                bc[k] += n(f)
                bc_by_year[yr][k] += n(f)

rule = "=" * 72
print(rule)
print("AWEBER EXPORT - AGGREGATE ANALYSIS")
print(f"source : {root}")
print(f"lists  : {len(lists)}")
print(rule)

print(f"\nUNIQUE PEOPLE")
print(f"  active (subscribed)   : {len(active):,}")
print(f"  inactive (unsub etc.) : {len(inactive):,}")
overlap = set(active) & set(inactive)
print(f"  in both               : {len(overlap):,}   <- subscribed on one list, unsubscribed on another")
print(f"  total distinct people : {len(set(active) | set(inactive)):,}")

multi = sum(1 for v in memberships.values() if v > 1)
print(f"\n  on more than one list : {multi:,} of {len(active):,} "
      f"({100*multi/max(len(active),1):.1f}%)")
print(f"  total memberships     : {sum(memberships.values()):,}")
if memberships:
    print("  most-listed people    : " +
          ", ".join(f"{n} lists x{c}" for n, c in
                    Counter(memberships.values()).most_common(6)))

print(f"\nADD METHOD (unique active people)")
for k, v in add_method.most_common(15):
    print(f"  {k[:44]:<46} {v:>6,}  {100*v/max(len(active),1):>5.1f}%")

print(f"\nVERIFIED")
for k, v in verified.most_common(6):
    print(f"  {k[:44]:<46} {v:>6,}")

print(f"\nJOINED BY YEAR (unique active)")
for y in sorted(years):
    print(f"  {y}  {years[y]:>6,}  {'#' * min(60, years[y] // 10)}")

print(f"\nTOP TAGS")
for k, v in tags.most_common(20):
    print(f"  {k[:44]:<46} {v:>6,}")

print(f"\n{rule}\nBROADCAST TOTALS ({bc_rows:,} distinct sends)")
em = bc["emailed"]
print(f"  emailed        : {em:,}")
print(f"  undeliverable  : {bc['undeliverable']:,}"
      + (f"   ({100*bc['undeliverable']/em:.2f}% BOUNCE RATE)" if em else ""))
print(f"  complaints     : {bc['complaints']:,}"
      + (f"   ({100*bc['complaints']/em:.4f}%)" if em else ""))
print(f"  suppressed     : {bc['suppressed']:,}")
print(f"  unique opens   : {bc['opens']:,}" + (f"   ({100*bc['opens']/em:.1f}%)" if em else ""))
print(f"  unique clicks  : {bc['clicks']:,}" + (f"   ({100*bc['clicks']/em:.2f}%)" if em else ""))

print(f"\nBY YEAR")
print(f"  {'year':<6}{'emailed':>10}{'undeliv':>9}{'bounce%':>9}{'compl':>7}"
      f"{'opens':>9}{'open%':>8}{'clicks':>8}")
for y in sorted(bc_by_year):
    r = bc_by_year[y]
    e = r["emailed"]
    print(f"  {y:<6}{e:>10,}{r['undeliverable']:>9,}"
          f"{(100*r['undeliverable']/e if e else 0):>8.2f}%{r['complaints']:>7,}"
          f"{r['opens']:>9,}{(100*r['opens']/e if e else 0):>7.1f}%{r['clicks']:>8,}")

print(f"\n{rule}\nPER-LIST (non-empty, by active desc)")
for lid, lname, a, i in sorted(per_list, key=lambda x: -x[2])[:20]:
    print(f"  {lid:<9} {lname[:42]:<44} active={a:>5}  inactive={i:>5}")

# ---- verification probes -------------------------------------------------
print(f"\n{rule}\nVERIFICATION PROBES")
for probe, expect in (("mark@lyfordoffice.com", "present (18 clicks, engaged)"),
                      ("dlucco@gmail.com",      "present (330 sends, 0 clicks, dead)"),
                      ("ghaleib@gmail.com",     "present (4 clicks)"),
                      ("brm444@msn.com",        "present (3 clicks)")):
    where = "ACTIVE" if probe in active else ("INACTIVE" if probe in inactive else "*** MISSING ***")
    print(f"  {probe:<28} {where:<18} expected {expect}")

# ---- outputs -------------------------------------------------------------
if out:
    os.makedirs(out, exist_ok=True)

    sup = os.path.join(out, "suppression-list.csv")
    with open(sup, "w", newline="", encoding="utf-8") as fh:
        w = csv.writer(fh)
        w.writerow(["email", "status", "inactive_date", "date_added"])
        for e, r in sorted(inactive.items()):
            w.writerow([e, r.get("Status", ""), r.get("Inactive Date", ""),
                        r.get("Date Added", "")])
    print(f"\n  wrote {len(inactive):,} rows -> {sup}")

    mig = os.path.join(out, "migration-list.csv")
    with open(mig, "w", newline="", encoding="utf-8") as fh:
        w = csv.writer(fh)
        w.writerow(["email", "name", "add_method", "verified", "date_added",
                    "lists", "tags", "tier"])
        for e, r in sorted(active.items()):
            if e in inactive:
                continue                       # unsubscribed elsewhere: never migrate
            am = (r.get("Add Method") or "").strip().lower()
            tg = (r.get("tags") or "").strip()
            if "import" in am:
                tier = "3_import"
            elif am:
                tier = "1_optin"
            else:
                tier = "2_unknown"
            w.writerow([e, r.get("Name 1", ""), r.get("Add Method", ""),
                        r.get("Verified", ""), r.get("Date Added", ""),
                        memberships[e], tg, tier])
    kept = len([e for e in active if e not in inactive])
    print(f"  wrote {kept:,} rows -> {mig}   ({len(overlap):,} excluded: unsubscribed elsewhere)")
