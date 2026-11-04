"""Shared plumbing for the Trail AI scripts: API client, cache, Firecrawl, opencode, CI logging."""

import json
import os
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

DEFAULT_API_URL = "https://trail.services.kibotu.net/api"
FIRECRAWL_URL = "https://api.firecrawl.dev/v2/scrape"
ANSI = re.compile(r"\x1b\[[0-9;]*[A-Za-z]")
IN_CI = os.environ.get("GITHUB_ACTIONS") == "true"


# --- logging ---------------------------------------------------------------

def log(msg: str = "") -> None:
    print(msg, flush=True)


def warn(msg: str) -> None:
    log(f"::warning::{msg}" if IN_CI else f"⚠️  {msg}")


def error(msg: str) -> None:
    log(f"::error::{msg}" if IN_CI else f"❌ {msg}")


def summary(markdown: str) -> None:
    """Append to the job summary, the one place a reader looks first."""
    path = os.environ.get("GITHUB_STEP_SUMMARY")
    if path:
        with open(path, "a", encoding="utf-8") as f:
            f.write(markdown + "\n")


def short(s: str, n: int = 60) -> str:
    s = " ".join((s or "").split())
    return s if len(s) <= n else s[: n - 1] + "…"


def tail(s: str, n: int = 400) -> str:
    s = ANSI.sub("", s or "").strip()
    return s[-n:]


# --- Trail API -------------------------------------------------------------

class Trail:
    def __init__(self, api_url: str, api_key: str, agent: str):
        self.url = api_url.rstrip("/")
        self.http = requests.Session()
        retry = Retry(total=3, backoff_factor=1, status_forcelist=[429, 500, 502, 503, 504],
                      allowed_methods=["GET", "PUT", "POST"])
        self.http.mount("https://", HTTPAdapter(max_retries=retry))
        self.http.mount("http://", HTTPAdapter(max_retries=retry))
        self.http.headers.update({"Authorization": f"Bearer {api_key}",
                                  "Content-Type": "application/json", "User-Agent": agent})

    def entries(self) -> list[dict]:
        """All entries, newest first (cursor pagination)."""
        out, cursor = [], None
        while True:
            params = {"limit": 100, **({"before": cursor} if cursor else {})}
            r = self.http.get(f"{self.url}/entries", params=params, timeout=30)
            r.raise_for_status()
            data = r.json()
            out += data.get("entries", [])
            if not data.get("has_more"):
                break
            cursor = data.get("next_cursor")
        return sorted(out, key=lambda e: e.get("created_at", ""), reverse=True)

    def entry(self, hash_id: str) -> dict:
        r = self.http.get(f"{self.url}/entries/{hash_id}", timeout=30)
        r.raise_for_status()
        return r.json()

    def put(self, path: str, body: dict) -> str | None:
        """PUT; returns None on success, else a short reason."""
        try:
            r = self.http.put(f"{self.url}{path}", json=body, timeout=15)
        except requests.RequestException as e:
            return f"network: {e}"
        if r.ok:
            return None
        return f"HTTP {r.status_code}" + (" (not your entry)" if r.status_code == 403 else f": {tail(r.text, 160)}")


# --- cache -----------------------------------------------------------------

class Cache:
    """hash_id -> result. Atomic writes; a corrupt or foreign file just means a fresh start."""

    VERSION = 1

    def __init__(self, path: str):
        self.path = Path(path)
        self.items: dict = {}
        try:
            data = json.loads(self.path.read_text("utf-8"))
            if data.get("version") == self.VERSION:
                self.items = data.get("processed", {})
        except FileNotFoundError:
            pass
        except (ValueError, AttributeError) as e:
            warn(f"Ignoring unreadable cache {self.path} ({e})")

    def save(self) -> None:
        fd, tmp = tempfile.mkstemp(dir=self.path.parent, suffix=".tmp")
        with os.fdopen(fd, "w", encoding="utf-8") as f:
            json.dump({"version": self.VERSION, "processed": self.items}, f, indent=2, ensure_ascii=False)
        os.replace(tmp, self.path)


# --- page content ----------------------------------------------------------

def _firecrawl(url: str) -> str:
    base = os.environ.get("FIRECRAWL_API_URL", "https://api.firecrawl.dev").rstrip("/")
    r = requests.post(f"{base}/v2/scrape", timeout=60,
                      headers={"Authorization": f"Bearer {os.environ['FIRECRAWL_API_KEY']}"},
                      json={"url": url, "formats": ["markdown"], "onlyMainContent": True, "timeout": 45000})
    r.raise_for_status()
    return (r.json().get("data") or {}).get("markdown") or ""


def _local(url: str) -> str:
    import trafilatura
    r = requests.get(url, timeout=20, headers={"User-Agent": "Mozilla/5.0 (compatible; TrailBot/2.0)"})
    r.raise_for_status()
    return trafilatura.extract(r.text, url=url, include_comments=False) or ""


def scrape(url: str, max_chars: int = 6000) -> tuple[str | None, str]:
    """Main text of a page, best source first: Firecrawl, then local trafilatura.

    Returns (content, note). Content is None when both fail; the caller then lets the opencode
    agent fetch the URL itself. The note records which source won or why each one lost.
    """
    sources = ([("firecrawl", _firecrawl)] if os.environ.get("FIRECRAWL_API_KEY") else []) + [("local", _local)]
    misses = []
    for name, fn in sources:
        try:
            text = fn(url)
        except (requests.RequestException, ValueError) as e:
            misses.append(f"{name}: {tail(str(e), 80)}")
            continue
        if text.strip():
            return text[:max_chars], name + (f" (after {'; '.join(misses)})" if misses else "")
        misses.append(f"{name}: no readable text")
    return None, "agent fetch (" + "; ".join(misses) + ")"


# --- opencode --------------------------------------------------------------

class OpencodeError(RuntimeError):
    def __init__(self, msg: str, fatal: bool = False):
        super().__init__(msg)
        self.fatal = fatal  # fatal = retrying other entries cannot help (auth, model, agent)


FATAL_HINTS = ("api key", "apikey", "unauthorized", "authentication", "model unavailable",
               "agent not found", "not logged in", "no provider", "quota", "credit")


def opencode(prompt: str, agent: str, model: str | None, cwd: Path, timeout: int = 120) -> str:
    cmd = ["opencode", "run", "--standalone", "--agent", agent] + (["--model", model] if model else []) + [prompt]
    try:
        p = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout, cwd=cwd)
    except subprocess.TimeoutExpired:
        raise OpencodeError(f"timed out after {timeout}s")
    except FileNotFoundError:
        raise OpencodeError("opencode not on PATH", fatal=True)
    if p.returncode != 0:
        detail = tail(p.stderr) or tail(p.stdout) or "no output"
        raise OpencodeError(f"opencode exit {p.returncode}: {detail}",
                            fatal=any(h in detail.lower() for h in FATAL_HINTS))
    return ANSI.sub("", p.stdout).strip()


def preflight(agent: str, model: str | None, cwd: Path) -> None:
    """One cheap call up front, so a bad key fails once with a reason instead of N times without one."""
    try:
        opencode("Reply with exactly: OK", agent, model, cwd, timeout=180)
    except OpencodeError as e:
        error(f"opencode preflight failed (agent={agent}, model={model or 'default'}): {e}")
        log("   Unset OPENCODE_MODEL/--model to use opencode's default free model, or set the "
            "provider key (e.g. ANTHROPIC_API_KEY) that the named model needs.")
        sys.exit(2)


class Breaker:
    """Stop after N consecutive failures sharing a cause; the 16th identical error teaches nothing."""

    def __init__(self, limit: int = 3):
        self.limit, self.last, self.count = limit, None, 0

    def ok(self) -> None:
        self.last, self.count = None, 0

    def fail(self, msg: str, fatal: bool = False) -> bool:
        """Record a failure; True means stop the run."""
        key = msg[:80]
        self.count = self.count + 1 if key == self.last else 1
        self.last = key
        return fatal or self.count >= self.limit


def pause(ms: int) -> None:
    time.sleep(ms / 1000)
