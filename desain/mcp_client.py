#!/usr/bin/env python3
"""Minimal MCP stdio client for FigCraft. Talks JSON-RPC to `figcraft-cli mcp --attach PORT`."""
import json, subprocess, sys, os

CLI = os.path.expanduser("~/.local/bin/figcraft-cli")

class FigMCP:
    def __init__(self, attach="7979"):
        args = [CLI, "mcp"] + (["--attach", attach] if attach else [])
        self.p = subprocess.Popen(args, stdin=subprocess.PIPE, stdout=subprocess.PIPE,
                                  stderr=subprocess.DEVNULL, text=True, bufsize=1)
        self._id = 0
        self._handshake()

    def _send(self, obj):
        self.p.stdin.write(json.dumps(obj) + "\n")
        self.p.stdin.flush()

    def _recv(self):
        while True:
            line = self.p.stdout.readline()
            if not line:
                raise RuntimeError("MCP server closed the stream")
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
        self._send({"jsonrpc": "2.0", "id": mid, "method": method, "params": params or {}})
        while True:
            m = self._recv()
            if m.get("id") == mid:
                if "error" in m:
                    raise RuntimeError(json.dumps(m["error"])[:600])
                return m.get("result", {})

    def _handshake(self):
        self._call("initialize", {
            "protocolVersion": "2024-11-05",
            "capabilities": {},
            "clientInfo": {"name": "hermes-figcraft", "version": "1.0"},
        })
        self._send({"jsonrpc": "2.0", "method": "notifications/initialized"})

    def tools(self):
        return self._call("tools/list")["tools"]

    def call(self, tool, **arguments):
        r = self._call("tools/call", {"name": tool, "arguments": arguments})
        out = []
        for c in r.get("content", []):
            if c.get("type") == "text":
                out.append(c["text"])
        txt = "\n".join(out)
        try:
            return json.loads(txt)
        except Exception:
            return txt

    def close(self):
        try:
            self.p.stdin.close()
            self.p.terminate()
        except Exception:
            pass


if __name__ == "__main__":
    m = FigMCP()
    for t in m.tools():
        print(t["name"], "|", (t.get("description") or "")[:70].replace("\n", " "))
    m.close()
