"""Thin client for live_daemon.py — use from any script."""
import json
import socket

SOCK = "/tmp/figcraft-orin.sock"


class DaemonDown(RuntimeError):
    pass


def _rpc(payload):
    try:
        s = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
        s.settimeout(300)
        s.connect(SOCK)
        s.sendall((json.dumps(payload) + "\n").encode())
        buf = b""
        while not buf.endswith(b"\n"):
            chunk = s.recv(1 << 20)
            if not chunk:
                break
            buf += chunk
        s.close()
    except (FileNotFoundError, ConnectionRefusedError) as e:
        raise DaemonDown(f"live daemon not running ({SOCK})") from e
    out = json.loads(buf.decode())
    if not out.get("ok"):
        raise RuntimeError(out.get("error", "unknown daemon error"))
    return out["result"]


class L:
    @staticmethod
    def batch(ops, label="Orin"):
        return _rpc({"tool": "batch", "args": {"ops": ops, "label": label}})

    @staticmethod
    def cmd(command, **params):
        return _rpc({"tool": "cmd", "args": {"command": command, "params": params}})

    @staticmethod
    def call(tool, **args):
        return _rpc({"tool": tool, "args": args})

    @staticmethod
    def shot(path):
        return _rpc({"tool": "screenshot", "args": {"path": path}})
