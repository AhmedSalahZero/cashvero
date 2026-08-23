#!/usr/bin/env python3
"""
Headless Chrome mobile audit — zero third-party deps.

Talks Chrome DevTools Protocol over a tiny built-in WebSocket client
so the suite works when npm/pip registries are unreachable.

Usage:
  php tests/e2e/mobile/page-catalog.php
  export E2E_EMAIL=... E2E_PASSWORD=... APP_URL=http://127.0.0.1:8000
  python3 tests/e2e/mobile/chrome_audit.py
"""

from __future__ import annotations

import base64
import hashlib
import json
import os
import random
import shutil
import socket
import ssl
import struct
import subprocess
import sys
import tempfile
import time
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent
CATALOG = ROOT / "catalog.json"
ARTIFACTS = ROOT / "artifacts"
SUMMARY_JSON = ROOT / "summary.json"
SUMMARY_HTML = ROOT / "summary.html"
VIEWPORT = {"width": 375, "height": 812}
BASE_URL = os.environ.get("APP_URL", "http://127.0.0.1:8000").rstrip("/")
EMAIL = os.environ.get("E2E_EMAIL", "")
PASSWORD = os.environ.get("E2E_PASSWORD", "")
CHROME = shutil.which("google-chrome") or shutil.which("google-chrome-stable") or shutil.which("chromium")


class MiniWebSocket:
    """Minimal RFC6455 client (text frames only) for Chrome CDP."""

    def __init__(self, url: str, timeout: float = 30.0):
        parsed = urllib.parse.urlparse(url)
        host = parsed.hostname or "127.0.0.1"
        port = parsed.port or (443 if parsed.scheme == "wss" else 80)
        path = parsed.path or "/"
        if parsed.query:
            path += "?" + parsed.query

        raw = socket.create_connection((host, port), timeout=timeout)
        if parsed.scheme == "wss":
            ctx = ssl.create_default_context()
            self.sock = ctx.wrap_socket(raw, server_hostname=host)
        else:
            self.sock = raw
        self.sock.settimeout(timeout)

        key = base64.b64encode(os.urandom(16)).decode()
        headers = (
            f"GET {path} HTTP/1.1\r\n"
            f"Host: {host}:{port}\r\n"
            "Upgrade: websocket\r\n"
            "Connection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {key}\r\n"
            "Sec-WebSocket-Version: 13\r\n"
            "\r\n"
        )
        self.sock.sendall(headers.encode())
        resp = b""
        while b"\r\n\r\n" not in resp:
            chunk = self.sock.recv(4096)
            if not chunk:
                raise RuntimeError("WebSocket handshake closed early")
            resp += chunk
        if b"101" not in resp.split(b"\r\n", 1)[0]:
            raise RuntimeError(f"WebSocket handshake failed: {resp[:200]!r}")

    def send_text(self, text: str) -> None:
        payload = text.encode()
        mask_key = os.urandom(4)
        header = bytearray([0x81])  # FIN + text
        n = len(payload)
        if n < 126:
            header.append(0x80 | n)
        elif n < 65536:
            header.append(0x80 | 126)
            header.extend(struct.pack("!H", n))
        else:
            header.append(0x80 | 127)
            header.extend(struct.pack("!Q", n))
        header.extend(mask_key)
        masked = bytes(b ^ mask_key[i % 4] for i, b in enumerate(payload))
        self.sock.sendall(header + masked)

    def recv_text(self) -> str:
        while True:
            hdr = self._recv_exact(2)
            opcode = hdr[0] & 0x0F
            masked = (hdr[1] & 0x80) != 0
            length = hdr[1] & 0x7F
            if length == 126:
                length = struct.unpack("!H", self._recv_exact(2))[0]
            elif length == 127:
                length = struct.unpack("!Q", self._recv_exact(8))[0]
            mask_key = self._recv_exact(4) if masked else b""
            payload = self._recv_exact(length)
            if masked:
                payload = bytes(b ^ mask_key[i % 4] for i, b in enumerate(payload))
            if opcode == 0x8:  # close
                raise RuntimeError("WebSocket closed by peer")
            if opcode == 0x9:  # ping → pong
                self._send_pong(payload)
                continue
            if opcode == 0x1:
                return payload.decode()
            # ignore binary / continuation for CDP (messages are small text JSON)

    def _send_pong(self, payload: bytes) -> None:
        mask_key = os.urandom(4)
        header = bytearray([0x8A, 0x80 | len(payload)])
        header.extend(mask_key)
        masked = bytes(b ^ mask_key[i % 4] for i, b in enumerate(payload))
        self.sock.sendall(header + masked)

    def _recv_exact(self, n: int) -> bytes:
        buf = b""
        while len(buf) < n:
            chunk = self.sock.recv(n - len(buf))
            if not chunk:
                raise RuntimeError("Socket closed")
            buf += chunk
        return buf

    def close(self) -> None:
        try:
            self.sock.close()
        except Exception:
            pass


class Cdp:
    def __init__(self, ws_url: str):
        self.ws = MiniWebSocket(ws_url)
        self._id = 0

    def call(self, method: str, params: dict | None = None, session_id: str | None = None):
        self._id += 1
        msg = {"id": self._id, "method": method, "params": params or {}}
        if session_id:
            msg["sessionId"] = session_id
        self.ws.send_text(json.dumps(msg))
        while True:
            data = json.loads(self.ws.recv_text())
            if data.get("id") == self._id:
                if "error" in data:
                    raise RuntimeError(f"{method}: {data['error']}")
                return data.get("result", {})

    def close(self) -> None:
        self.ws.close()


def wait_port(port: int, timeout: float = 25.0) -> dict:
    deadline = time.time() + timeout
    url = f"http://127.0.0.1:{port}/json/version"
    last = None
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(url, timeout=1) as resp:
                return json.loads(resp.read().decode())
        except Exception as e:
            last = e
            time.sleep(0.25)
    raise RuntimeError(f"Chrome DevTools not ready on :{port}: {last}")


def evaluate(cdp_call, expression: str):
    result = cdp_call("Runtime.evaluate", {
        "expression": expression,
        "returnByValue": True,
        "awaitPromise": True,
    })
    if result.get("exceptionDetails"):
        raise RuntimeError(result["exceptionDetails"])
    return result.get("result", {}).get("value")


def main() -> int:
    if not CHROME:
        print("google-chrome not found on PATH", file=sys.stderr)
        return 1
    if not CATALOG.exists():
        print(f"Missing {CATALOG} — run: php tests/e2e/mobile/page-catalog.php", file=sys.stderr)
        return 1
    if not EMAIL or not PASSWORD:
        print("Set E2E_EMAIL and E2E_PASSWORD", file=sys.stderr)
        return 1

    catalog = json.loads(CATALOG.read_text())
    pages = catalog["pages"] if isinstance(catalog, dict) else catalog
    ARTIFACTS.mkdir(parents=True, exist_ok=True)

    user_data = tempfile.mkdtemp(prefix="cvr-mobile-chrome-")
    port = 9222 + (os.getpid() % 200)

    chrome = subprocess.Popen(
        [
            CHROME,
            f"--remote-debugging-port={port}",
            f"--user-data-dir={user_data}",
            "--headless=new",
            "--disable-gpu",
            "--no-first-run",
            "--no-default-browser-check",
            f"--window-size={VIEWPORT['width']},{VIEWPORT['height']}",
            "about:blank",
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )

    results = []
    try:
        version = wait_port(port)
        browser = Cdp(version["webSocketDebuggerUrl"])
        target = browser.call("Target.createTarget", {"url": "about:blank"})
        attached = browser.call(
            "Target.attachToTarget",
            {"targetId": target["targetId"], "flatten": True},
        )
        sid = attached["sessionId"]

        def cdp(method, params=None):
            return browser.call(method, params, session_id=sid)

        cdp("Page.enable")
        cdp("Runtime.enable")
        cdp("Emulation.setDeviceMetricsOverride", {
            "width": VIEWPORT["width"],
            "height": VIEWPORT["height"],
            "deviceScaleFactor": 2,
            "mobile": True,
        })

        # Login via the Vue form fields.
        cdp("Page.navigate", {"url": f"{BASE_URL}/en/login"})
        time.sleep(2.0)
        evaluate(cdp, f"""
          (() => {{
            const email = document.querySelector('#email');
            const password = document.querySelector('#password');
            if (!email || !password) return 'missing-fields';
            const set = (el, v) => {{
              const proto = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
              proto.set.call(el, v);
              el.dispatchEvent(new Event('input', {{ bubbles: true }}));
              el.dispatchEvent(new Event('change', {{ bubbles: true }}));
            }};
            set(email, {json.dumps(EMAIL)});
            set(password, {json.dumps(PASSWORD)});
            const btn = document.querySelector('form.zav-form button[type="submit"]')
              || document.querySelector('form button[type="submit"]')
              || document.querySelector('form.zav-form button');
            if (btn) btn.click();
            else {{
              const form = document.querySelector('form.zav-form') || document.querySelector('form');
              if (form) form.submit();
            }}
            return 'ok';
          }})()
        """)
        time.sleep(3.5)

        for entry in pages:
            url = entry["url"]
            full = url if url.startswith("http") else BASE_URL + (url if url.startswith("/") else "/" + url)
            status = "pass"
            issues: list[str] = []
            warnings: list[str] = []

            try:
                cdp("Page.navigate", {"url": full})
                time.sleep(1.4)
                metrics = evaluate(cdp, """
                  (() => {
                    const doc = document.documentElement;
                    const body = document.body;
                    const main = document.querySelector('main');
                    const mainWidth = main ? main.getBoundingClientRect().width
                      : body.getBoundingClientRect().width;
                    const scrollWidth = Math.max(doc.scrollWidth, body.scrollWidth);
                    const clientWidth = window.innerWidth;
                    return {
                      scrollWidth, clientWidth, mainWidth,
                      overflow: scrollWidth > clientWidth + 1,
                      path: location.pathname + location.search
                    };
                  })()
                """)

                if metrics.get("overflow"):
                    issues.append("horizontal_overflow")
                if float(metrics.get("mainWidth") or 0) < 280:
                    issues.append("main_content_too_narrow")
                path = metrics.get("path") or ""
                if "/login" in path and "/login" not in entry["url"]:
                    if entry.get("optional"):
                        status = "skipped"
                        warnings.append("redirected_to_login")
                    else:
                        issues.append("redirected_to_login")

                if issues:
                    status = "fail"
                    shot = ARTIFACTS / ("".join(c if c.isalnum() or c in "-_" else "_" for c in entry["id"]) + ".png")
                    data = cdp("Page.captureScreenshot", {"format": "png", "fromSurface": True})
                    shot.write_bytes(base64.b64decode(data["data"]))

            except Exception as e:
                if entry.get("optional"):
                    status = "skipped"
                    warnings.append(str(e)[:160])
                else:
                    status = "fail"
                    issues.append(f"error:{e}")

            results.append({
                "id": entry["id"],
                "title": entry.get("title", entry["id"]),
                "url": entry["url"],
                "status": status,
                "issues": issues,
                "warnings": warnings,
            })
            mark = {"pass": "PASS", "fail": "FAIL", "skipped": "SKIP"}[status]
            extra = (", ".join(issues + warnings)) or "—"
            print(f"  {mark}  {entry['id']}  {extra}")

        browser.close()
    finally:
        chrome.terminate()
        try:
            chrome.wait(timeout=5)
        except Exception:
            chrome.kill()
        shutil.rmtree(user_data, ignore_errors=True)

    summary = {
        "generatedAt": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
        "viewport": VIEWPORT,
        "engine": "chrome-cdp",
        "totals": {
            "pass": sum(1 for r in results if r["status"] == "pass"),
            "fail": sum(1 for r in results if r["status"] == "fail"),
            "skipped": sum(1 for r in results if r["status"] == "skipped"),
        },
        "byIssue": {},
        "pages": results,
    }
    for r in results:
        for issue in r["issues"]:
            summary["byIssue"].setdefault(issue, []).append(r["id"])

    SUMMARY_JSON.write_text(json.dumps(summary, indent=2) + "\n")
    rows = []
    for r in results:
        issues = "; ".join(r["issues"] + r["warnings"]) or "—"
        rows.append(
            f'<tr class="{r["status"]}"><td>{r["status"]}</td><td>{r["title"]}</td>'
            f"<td>{issues}</td><td><code>{r['url']}</code></td></tr>"
        )
    SUMMARY_HTML.write_text(
        "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Mobile Audit</title>"
        "<style>body{font-family:system-ui;padding:1.5rem;background:#0f172a;color:#e2e8f0}"
        "h1{font-size:1.25rem}.pass{color:#34d399}.fail{color:#f87171}.skipped{color:#94a3b8}"
        "table{border-collapse:collapse;width:100%}td,th{border:1px solid #334155;padding:.4rem .6rem;font-size:.85rem;text-align:left}"
        "code{font-size:.75rem}</style></head><body>"
        f"<h1>Mobile Audit — {summary['totals']['pass']} pass / "
        f"{summary['totals']['fail']} fail / {summary['totals']['skipped']} skipped</h1>"
        "<table><thead><tr><th>Status</th><th>Page</th><th>Issues</th><th>URL</th></tr></thead><tbody>"
        + "\n".join(rows)
        + "</tbody></table></body></html>\n"
    )
    print(
        f"\nDone — {summary['totals']['pass']} pass / "
        f"{summary['totals']['fail']} fail / {summary['totals']['skipped']} skipped"
    )
    print(f"Summary: {SUMMARY_HTML}")
    return 1 if summary["totals"]["fail"] else 0


if __name__ == "__main__":
    sys.exit(main())
