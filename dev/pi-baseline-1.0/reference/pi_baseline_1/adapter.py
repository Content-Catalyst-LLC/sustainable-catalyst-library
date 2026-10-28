"""Pi dev-only Library read adapter. Fixed allowlist, no write methods, no secrets."""
import json
import os
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlencode, urlparse, parse_qs
from urllib.request import urlopen, Request

BACKEND = os.environ.get("LIBRARY_INTERNAL_URL", "http://library-backend:8080").rstrip("/")
ROUTES = {
    "/health": "/health",
    "/readiness": "/api/library/v1/research-interface/readiness",
    "/bootstrap": "/api/library/v1/research-interface/bootstrap",
    "/search": "/api/library/v1/research-interface/search",
}

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        parsed = urlparse(self.path)
        target = ROUTES.get(parsed.path)
        if target is None:
            self.send_error(404, "Not found")
            return
        params = {}
        if parsed.path == "/search":
            query = parse_qs(parsed.query, keep_blank_values=True)
            if set(query) - {"q"} or len(query.get("q", [])) != 1 or len(query["q"][0]) > 160:
                self.send_error(400, "Only one q parameter is supported")
                return
            params = {"q": query["q"][0], "mode": "lexical", "limit": "10", "offset": "0"}
        elif parsed.query:
            self.send_error(400, "Unexpected query")
            return
        dest = BACKEND + target + ("?" + urlencode(params) if params else "")
        try:
            with urlopen(Request(dest, headers={"Accept": "application/json"}), timeout=10) as response:
                data = response.read(1024 * 1024 + 1)
                if len(data) > 1024 * 1024:
                    self.send_error(502, "Response too large")
                    return
                obj = json.loads(data)
                if not isinstance(obj, dict):
                    self.send_error(502, "Unexpected result")
                    return
                if parsed.path == "/health" and not obj.get("ok"):
                    self.send_error(503, "Backend unhealthy")
                    return
            wire = json.dumps(obj, separators=(",", ":")).encode()
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Cache-Control", "no-store")
            self.send_header("X-Content-Type-Options", "nosniff")
            self.send_header("Content-Length", str(len(wire)))
            self.end_headers()
            self.wfile.write(wire)
        except Exception as exc:
            self.send_error(502, "Library backend unavailable: " + exc.__class__.__name__)

    def do_POST(self):
        self.send_error(405)
    def do_PUT(self):
        self.send_error(405)
    def do_DELETE(self):
        self.send_error(405)
    def log_message(self, fmt, *args):
        pass

if __name__ == "__main__":
    ThreadingHTTPServer(("0.0.0.0", 8097), Handler).serve_forever()
