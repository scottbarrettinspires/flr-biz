# flr.biz agent mobile pages
Small PHP app (no dependencies) that replaces agents' Linktree cards.

- Agents live in `_app/agents.json` (name, Lofty base URL, button lists, `setup_hash`). Each agent has a permanent
  code = initials + number (`ab1`); buyer = `abb1`, seller = `abs1`.
- Each agent edits their own YouTube link, photo, phone, email and socials at
  `/setup/?a=<code>&k=<private key>`. Only the SHA-256 of the key is stored here.
- Saved edits live in `_data/` and `uploads/` on the server. They are git-ignored, so deploys never overwrite them.
- Add an agent: append to `_app/agents.json` with the next free code and a new `setup_hash`, push.
