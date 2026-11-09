#!/usr/bin/env python3
"""Daemon: holds ONE FigCraft MCP connection open for the whole session.

The app shows an agent's avatar + layer outline only while it is connected and
acting; both fade 8 s after its last command. Keeping one connection with a
heartbeat means the user can actually watch the agent work.

Run once:
    python3 live_daemon.py &          # or: python3 live_daemon.py --fg

Then every other script talks to it over a unix socket:
    from live_client import L
    L.batch(ops)          # user sees it happen live
"""
import json
import os
import socket
import sys
import threading
import time

from live import Live

SOCK = "/tmp/figcraft-orin.sock"


def serve(forever=True):
    if os.path.exists(SOCK):
        os.unlink(SOCK)
    srv = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    srv.bind(SOCK)
    srv.listen(8)

    L = Live()
    print("daemon ready, socket:", SOCK, flush=True)

    def handle(conn):
        with conn:
            data = b""
            while not data.endswith(b"\n"):
                chunk = conn.recv(1 << 20)
                if not chunk:
                    return
                data += chunk
            try:
                req = json.loads(data.decode())
                tool = req["tool"]
                args = req.get("args", {})
                if tool == "batch":
                    out = L.batch(args["ops"], label=args.get("label", "Orin"))
                elif tool == "cmd":
                    out = L.cmd(args["command"], **args.get("params", {}))
                elif tool == "screenshot":
                    out = L.screenshot(args["path"])
                else:
                    out = L.call(tool, **args)
                conn.sendall((json.dumps({"ok": True, "result": out}) + "\n").encode())
            except Exception as e:
                conn.sendall((json.dumps({"ok": False, "error": str(e)[:800]}) + "\n").encode())

    while True:
        conn, _ = srv.accept()
        threading.Thread(target=handle, args=(conn,), daemon=True).start()
        if not forever:
            break


if __name__ == "__main__":
    serve()
