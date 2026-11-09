#!/usr/bin/env python3
"""Persistent MCP client for FigCraft.

Keeps ONE connection to the running app (`figcraft --control PORT`) so the
agent avatar/outline stays visible in the UI, and heartbeats every few seconds
so it never fades the 8 s timeout.

Usage:
    from live import Live
    with Live() as L:
        L.batch([...])          # runs live, user sees it
        L.call("run_command", command="view.zoomFit")
        L.screenshot("/tmp/x.png")
"""
import json
import os
import subprocess
import threading
import time

CLI = os.path.expanduser("~/.local/bin/figcraft-cli")
AGENT_NAME = "Orin"
HEARTBEAT_S = 4.0


class Live:
    def __init__(self, attach="7979", name=AGENT_NAME, heartbeat=True):
        self.p = subprocess.Popen(
            [CLI, "mcp", "--attach", attach, "--timeout", "300"],
            stdin=subprocess.PIPE, stdout=subprocess.PIPE,
            stderr=subprocess.DEVNULL, text=True, bufsize=1)
        self._id = 0
        self._lock = threading.Lock()
        self._stop = threading.Event()
        self._hb = None
        self._handshake(name)
        if heartbeat:
            self._hb = threading.Thread(target=self._heartbeat, daemon=True)
            self._hb.start()

    # ---------- json-rpc ----------
    def _send(self, obj):
        with self._lock:
            self.p.stdin.write(json.dumps(obj) + "\n")
            self.p.stdin.flush()

    def _recv(self):
        while True:
            line = self.p.stdout.readline()
            if not line:
                raise RuntimeError("FigCraft closed the MCP stream")
            line = line.strip()
            if not line:
                continue
            try:
                return json.loads(line)
            except json.JSONDecodeError:
                continue

    def _call(self, method, params=None):
        self._id += 1
        mid = self._id
        self._send({"jsonrpc": "2.0", "id": mid, "method": method,
                    "params": params or {}})
        while True:
            m = self._recv()
            if m.get("id") == mid:
                if "error" in m:
                    raise RuntimeError(json.dumps(m["error"])[:800])
                return m.get("result", {})

    def _handshake(self, name):
        self._call("initialize", {
            "protocolVersion": "2025-06-18",
            "capabilities": {},
            "clientInfo": {"name": name, "title": name, "version": "1.0"},
        })
        self._send({"jsonrpc": "2.0", "method": "notifications/initialized"})

    # ---------- heartbeat: keeps the agent badge on screen ----------
    def _heartbeat(self):
        # a read-only call; failing silently is fine (reconnect happens on next real call)
        while not self._stop.wait(HEARTBEAT_S):
            try:
                self.call("inspect_ui", timeout=10)
            except Exception:
                return

    # ---------- tools ----------
    def call(self, tool, **args):
        r = self._call("tools/call", {"name": tool, "arguments": args})
        out = [c["text"] for c in r.get("content", []) if c.get("type") == "text"]
        txt = "\n".join(out)
        try:
            return json.loads(txt)
        except Exception:
            return txt

    def batch(self, ops, label="Orin", on_error="continue"):
        return self.call("batch", commands=ops, label=label, onError=on_error)

    def cmd(self, command, **params):
        return self.call("run_command", command=command, params=params)

    def screenshot(self, path):
        self.call("screenshot", **{"window": True, "maxSize": 1500})
        os.system(f"screencapture -x {path}")
        return path

    def open(self, path):
        return self.call("open_file", path=path)

    def save(self, path):
        return self.call("save_file", path=path)

    def close(self):
        self._stop.set()
        try:
            self.p.stdin.close()
            self.p.terminate()
        except Exception:
            pass

    def __enter__(self):
        return self

    def __exit__(self, *a):
        self.close()


if __name__ == "__main__":
    with Live() as L:
        print("connected:", str(L.call("inspect_ui"))[:80])
        for i in range(3):
            time.sleep(3)
            L.cmd("view.zoomFit")
            print("tick", i, "alive")
