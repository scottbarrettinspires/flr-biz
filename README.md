# flr.biz agent mobile pages
Digital business cards for FLR agents (replaces Linktree). Site root = built output.
To add an agent: edit `_source/agents.json`, add `assets/agents/<slug>/photo.jpg`, run
`python3 _source/build.py` (writes ./dist), then copy dist/ contents to the repo root and push.
