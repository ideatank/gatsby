#!/usr/bin/env python3
"""Acquisition forensics: where did signups come from, by year, active + inactive."""
import csv, os, sys, glob, re
from collections import Counter, defaultdict

root = sys.argv[1]

def read_csv(path):
    for enc in ("utf-8-sig", "utf-8", "latin-1"):
        try:
            with open(path, newline="", encoding=enc) as fh:
                return list(csv.DictReader(fh))
        except UnicodeDecodeError:
            continue
    return []

people = {}   # email -> record (first seen), plus which list
for d in sorted(glob.glob(os.path.join(root, "awlist*"))):
    if not os.path.isdir(d): continue
    m = re.match(r"awlist(\d+)_(.*)", os.path.basename(d))
    lid, lname = (m.group(1), m.group(2)) if m else ("?", "?")
    for fn, state in (("active_leads.csv","active"), ("inactive_leads.csv","inactive")):
        for r in read_csv(os.path.join(d, "leads", fn)):
            e = (r.get("Email") or "").strip().lower()
            if not e or e in people: continue
            r["_list"], r["_lname"], r["_state"] = lid, lname, state
            people[e] = r

def yr(r):
    d = (r.get("Date Added") or "")[:4]
    return d if d.isdigit() else "?"

by_year = defaultdict(list)
for r in people.values():
    by_year[yr(r)].append(r)

print("=" * 74)
print("ACQUISITION FORENSICS — all people ever (active + unsubscribed)")
print(f"total distinct people: {len(people):,}")
print("=" * 74)

print(f"\n{'year':<6}{'signups':>9}{'still active':>14}{'unsubscribed':>14}{'churn':>8}")
for y in sorted(by_year):
    rs = by_year[y]
    a = sum(1 for r in rs if r["_state"] == "active")
    i = len(rs) - a
    print(f"{y:<6}{len(rs):>9,}{a:>14,}{i:>14,}{(100*i/len(rs)):>7.1f}%")

def profile(y):
    rs = by_year.get(y, [])
    if not rs: return
    print(f"\n{'-'*74}\n{y} — {len(rs)} signups")
    for field, label, top in (("Add URL","ADD URL (signup page)",12),
                              ("Add Method","ADD METHOD",8),
                              ("Message","MESSAGE / SOURCE NOTE",8),
                              ("Additional Notes","ADDITIONAL NOTES",6),
                              ("_lname","LANDED ON LIST",10)):
        c = Counter((r.get(field) or "(blank)").strip()[:62] for r in rs)
        shown = [(k,v) for k,v in c.most_common(top)]
        if len(shown) == 1 and shown[0][0] == "(blank)":
            print(f"  {label}: all blank")
            continue
        print(f"  {label}")
        for k, v in shown:
            print(f"    {v:>5,}  {k}")
    tg = Counter()
    for r in rs:
        for t in (r.get("tags") or "").split(","):
            t = t.strip()
            if t: tg[t] += 1
    if tg:
        print("  TAGS")
        for k, v in tg.most_common(12):
            print(f"    {v:>5,}  {k[:60]}")

for y in ("2019", "2020", "2021", "2022", "2023"):
    profile(y)

# what disappeared: URLs/methods present pre-2021 vs after
print(f"\n{'='*74}\nSIGNUP CHANNELS BY ERA")
def chan(years):
    c = Counter()
    for y in years:
        for r in by_year.get(y, []):
            u = (r.get("Add URL") or "").strip()
            m = (r.get("Add Method") or "").strip()
            key = u if u else (f"[method:{m}]" if m else "(no source recorded)")
            c[key[:62]] += 1
    return c
pre  = chan([str(y) for y in range(2014, 2021)])
post = chan([str(y) for y in range(2021, 2027)])
print(f"\n  PRE-2021 ({sum(pre.values()):,} signups)")
for k, v in pre.most_common(12): print(f"    {v:>5,}  {k}")
print(f"\n  2021+ ({sum(post.values()):,} signups)")
for k, v in post.most_common(12): print(f"    {v:>5,}  {k}")
