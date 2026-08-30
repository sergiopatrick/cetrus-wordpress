#!/usr/bin/env python3
"""Cliente CDP minimo: sobe Chrome headless, navega, roda JS e devolve o resultado."""
import json, socket, base64, os, struct, subprocess, time, urllib.request, secrets, hashlib, re, signal

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

class WS:
    def __init__(self, url):
        m = re.match(r"ws://([^:/]+):(\d+)(/.*)", url)
        host, porta, caminho = m.group(1), int(m.group(2)), m.group(3)
        self.s = socket.create_connection((host, porta), timeout=45)
        chave = base64.b64encode(secrets.token_bytes(16)).decode()
        req = (f"GET {caminho} HTTP/1.1\r\nHost: {host}:{porta}\r\nUpgrade: websocket\r\n"
               f"Connection: Upgrade\r\nSec-WebSocket-Key: {chave}\r\nSec-WebSocket-Version: 13\r\n\r\n")
        self.s.sendall(req.encode())
        buf = b""
        while b"\r\n\r\n" not in buf:
            buf += self.s.recv(4096)
        self.resto = buf.split(b"\r\n\r\n",1)[1]

    def _ler(self, n):
        while len(self.resto) < n:
            d = self.s.recv(65536)
            if not d: raise IOError("conexao fechada")
            self.resto += d
        out, self.resto = self.resto[:n], self.resto[n:]
        return out

    def enviar(self, texto):
        dados = texto.encode()
        cab = bytearray([0x81])
        n = len(dados)
        mask = secrets.token_bytes(4)
        if n < 126: cab.append(0x80 | n)
        elif n < 65536: cab.append(0x80 | 126); cab += struct.pack(">H", n)
        else: cab.append(0x80 | 127); cab += struct.pack(">Q", n)
        cab += mask
        cab += bytes(b ^ mask[i % 4] for i, b in enumerate(dados))
        self.s.sendall(bytes(cab))

    def receber(self):
        b0, b1 = self._ler(2)
        n = b1 & 0x7F
        if n == 126: n = struct.unpack(">H", self._ler(2))[0]
        elif n == 127: n = struct.unpack(">Q", self._ler(8))[0]
        return self._ler(n).decode("utf-8", "replace")

    def fechar(self):
        try: self.s.close()
        except Exception: pass


class Chrome:
    def __init__(self, largura=1440, altura=900, mobile=False, porta=9333):
        self.porta = porta
        perfil = f"/tmp/qa-chrome-{porta}"
        subprocess.run(["rm","-rf",perfil], capture_output=True)
        args = [CHROME, "--headless=new", "--disable-gpu", "--no-first-run", "--no-default-browser-check",
                f"--remote-debugging-port={porta}", f"--user-data-dir={perfil}",
                f"--window-size={largura},{altura}", "--hide-scrollbars",
                "--force-device-scale-factor=1", "about:blank"]
        self.p = subprocess.Popen(args, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        alvo = None
        for _ in range(80):
            time.sleep(0.25)
            try:
                lista = json.load(urllib.request.urlopen(f"http://127.0.0.1:{porta}/json", timeout=3))
                pgs = [t for t in lista if t.get("type") == "page"]
                if pgs: alvo = pgs[0]; break
            except Exception: pass
        if not alvo: raise RuntimeError("Chrome nao subiu")
        self.ws = WS(alvo["webSocketDebuggerUrl"])
        self.id = 0
        self.cmd("Page.enable"); self.cmd("Runtime.enable")
        if mobile:
            self.cmd("Emulation.setDeviceMetricsOverride", {
                "width": largura, "height": altura, "deviceScaleFactor": 2, "mobile": True})
            self.cmd("Emulation.setUserAgentOverride", {"userAgent":
                "Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36"})

    def cmd(self, metodo, params=None, espera=45):
        self.id += 1
        meu = self.id
        self.ws.enviar(json.dumps({"id": meu, "method": metodo, "params": params or {}}))
        fim = time.time() + espera
        while time.time() < fim:
            msg = json.loads(self.ws.receber())
            if msg.get("id") == meu:
                if "error" in msg: raise RuntimeError(f"{metodo}: {msg['error']}")
                return msg.get("result", {})
        raise TimeoutError(metodo)

    def ir(self, url, espera=14):
        self.cmd("Page.navigate", {"url": url})
        time.sleep(espera)

    def js(self, expr, espera=45):
        r = self.cmd("Runtime.evaluate",
                     {"expression": expr, "returnByValue": True, "awaitPromise": True}, espera)
        if r.get("exceptionDetails"):
            return {"erro": str(r["exceptionDetails"])[:300]}
        return r.get("result", {}).get("value")

    def fechar(self):
        try: self.ws.fechar()
        except Exception: pass
        try:
            self.p.send_signal(signal.SIGTERM); self.p.wait(timeout=10)
        except Exception:
            try: self.p.kill()
            except Exception: pass
