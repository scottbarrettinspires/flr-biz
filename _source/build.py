#!/usr/bin/env python3
"""Generate flr.biz agent mobile pages from agents.json.
Usage: python3 build.py   (writes the site to ./dist)
Add an agent = add one entry to agents.json, drop their photo in
assets/agents/<slug>/photo.jpg, run build.py.
"""
import json, shutil, html
from pathlib import Path

ROOT = Path(__file__).parent
DIST = ROOT / "dist"
data = json.loads((ROOT / "agents.json").read_text(encoding="utf-8"))
esc = html.escape

ICONS = {
    "phone": '<path d="M6.6 10.8a15 15 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11 11 0 0 0 3.6.6 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.6 3.6a1 1 0 0 1-.25 1z"/>',
    "email": '<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.2L4.6 7H19.4zM4 9.3V17h16V9.3l-8 5.6z"/>',
    "instagram": '<path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V7a3 3 0 0 0-3-3zm5 3.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 0 1 12 7.5zm0 2A2.5 2.5 0 1 0 14.5 12 2.5 2.5 0 0 0 12 9.5zM17.3 6a1.1 1.1 0 1 1-1.1 1.1A1.1 1.1 0 0 1 17.3 6z"/>',
    "facebook": '<path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.8c0-.9.3-1.5 1.6-1.5h1.7V4.4A22 22 0 0 0 14.4 4C12 4 10.3 5.5 10.3 8.2v2.6H7.5V14h2.8v8z"/>',
    "tiktok": '<path d="M16.6 2h-3.2v13.4a2.9 2.9 0 1 1-2.9-2.9c.3 0 .6 0 .9.1V9.3a6.1 6.1 0 1 0 5.2 6V8.7a7.3 7.3 0 0 0 4.3 1.4V6.9a4.3 4.3 0 0 1-4.3-4.9z"/>',
    "website": '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 9h-3a15.7 15.7 0 0 0-1.2-5.2A8 8 0 0 1 18.9 11zM12 4c.8 1 1.7 3.2 2 7H10c.3-3.8 1.2-6 2-7zM9.3 5.8A15.7 15.7 0 0 0 8.1 11h-3a8 8 0 0 1 4.2-5.2zM5.1 13h3a15.7 15.7 0 0 0 1.2 5.2A8 8 0 0 1 5.1 13zM12 20c-.8-1-1.7-3.2-2-7h4c-.3 3.8-1.2 6-2 7zm2.7-1.8A15.7 15.7 0 0 0 15.9 13h3a8 8 0 0 1-4.2 5.2z"/>',
    "contact": '<path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-4 0-8 2-8 5v2h16v-2c0-3-4-5-8-5z"/>',
}

def icon(name):
    return f'<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor">{ICONS[name]}</svg>'

def initials(name):
    return "".join(w[0] for w in name.split()[:2]).upper()

def href(agent, link, slug):
    if "view" in link:
        return f"/{slug}/{link['view']}/"
    if "lofty" in link:
        return agent["lofty_base"].rstrip("/") + link["lofty"]
    return link["url"]

def page(agent, view_key):
    slug = agent["slug"]
    view = agent[view_key]
    is_main = view_key == "main"
    name = agent["name"] + " - " + agent["title"] if is_main else view["name"]
    headline = agent["headline"] if is_main else view["headline"]
    tagline = agent["tagline"] if is_main else view["sub"]
    base = f"/{slug}/" if is_main else f"/{slug}/{view_key}/"
    photo = f"/assets/agents/{slug}/{agent['photo']}"

    socials = []
    if agent.get("phone"):
        tel = "".join(c for c in agent["phone"] if c.isdigit() or c == "+")
        socials.append(("phone", f"tel:{tel}", "Call"))
    if agent.get("email"):
        socials.append(("email", f"mailto:{agent['email']}", "Email"))
    for key, label in (("instagram", "Instagram"), ("facebook", "Facebook"), ("tiktok", "TikTok"), ("website", "Website")):
        if agent.get(key):
            socials.append((key, agent[key], label))
    social_html = "".join(
        f'<a class="soc" href="{esc(u)}" aria-label="{l}" {"" if k in ("phone","email") else "target=\"_blank\" rel=\"noopener\""}>{icon(k)}</a>'
        for k, u, l in socials
    )

    links_html = ""
    for l in view["links"]:
        external = "view" not in l
        sub = f'<span class="sub">{esc(l["sub"])}</span>' if l.get("sub") else ""
        links_html += (
            f'<a class="btn" href="{esc(href(agent, l, slug))}"'
            f'{" target=\"_blank\" rel=\"noopener\"" if external else ""}>'
            f'<span class="t">{esc(l["title"])}</span>{sub}</a>\n'
        )

    video_html = ""
    if agent.get("youtube_id") and is_main:
        video_html = (
            '<div class="video"><iframe src="https://www.youtube-nocookie.com/embed/'
            f'{esc(agent["youtube_id"])}" title="Video from {esc(agent["name"].title())}" '
            'loading="lazy" allowfullscreen></iframe></div>'
        )

    back = "" if is_main else f'<a class="back" href="/{slug}/">&larr; {esc(agent["name"].title())}</a>'
    contact = (
        f'<a class="btn contact" href="/{slug}/contact.vcf" download>'
        f'<span class="t">{icon("contact")} Add me to your contacts</span></a>'
    )

    return f"""<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(name)} | {esc(data['site']['brokerage'])}</title>
<meta name="description" content="{esc(tagline)}">
<meta property="og:title" content="{esc(name)}">
<meta property="og:description" content="{esc(tagline)}">
<meta property="og:image" content="https://{data['site']['domain']}{photo}">
<link rel="canonical" href="https://{data['site']['domain']}{base}">
<link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="card">
{back}
<div class="avatar" data-initials="{esc(initials(agent['name']))}"><img src="{photo}" alt="{esc(agent['name'].title())}" onerror="this.remove()"></div>
<h1>{esc(name)}</h1>
<p class="tag">{esc(tagline)}</p>
{f'<p class="headline">{esc(headline)}</p>' if is_main else ''}
<div class="socials">{social_html}</div>
{video_html}
<nav class="links">
{links_html}{contact if is_main else ''}
</nav>
<footer>{esc(data['site']['brokerage'])}<br><span>Equal Housing Opportunity</span></footer>
</main>
</body>
</html>
"""

def vcard(agent):
    first, *rest = agent["name"].title().split()
    lines = ["BEGIN:VCARD", "VERSION:3.0",
             f"N:{' '.join(rest)};{first};;;", f"FN:{agent['name'].title()}",
             f"ORG:{data['site']['brokerage']}", f"TITLE:{agent['title']}"]
    if agent.get("phone"): lines.append(f"TEL;TYPE=CELL:{agent['phone']}")
    if agent.get("email"): lines.append(f"EMAIL:{agent['email']}")
    if agent.get("website"): lines.append(f"URL:{agent['website']}")
    lines += [f"URL:https://{data['site']['domain']}/{agent['slug']}/", "END:VCARD"]
    return "\r\n".join(lines) + "\r\n"

if DIST.exists():
    shutil.rmtree(DIST)
shutil.copytree(ROOT / "assets", DIST / "assets", dirs_exist_ok=True)

for a in data["agents"]:
    for key in ("main", "buyer", "seller"):
        out = DIST / a["slug"] if key == "main" else DIST / a["slug"] / key
        out.mkdir(parents=True, exist_ok=True)
        (out / "index.html").write_text(page(a, key), encoding="utf-8")
    (DIST / a["slug"] / "contact.vcf").write_text(vcard(a), encoding="utf-8")

(DIST / "index.html").write_text(
    '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex">'
    f'<title>{data["site"]["brokerage"]}</title>'
    f'<meta http-equiv="refresh" content="0;url=https://floridaluxuryrealty.com">', encoding="utf-8")
(DIST / "robots.txt").write_text("User-agent: *\nAllow: /\n", encoding="utf-8")
print("Built:", ", ".join(a["slug"] for a in data["agents"]))
