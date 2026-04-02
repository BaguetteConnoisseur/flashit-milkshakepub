const WebSocket = require('ws');
const http = require('http');
const fetch = (...args) => import('node-fetch').then(mod => mod.default(...args));
const cookie = require('cookie');


const wss = new WebSocket.Server({ port: 8081, host: '0.0.0.0' });

async function isSessionValid(sessionId) {
  if (!sessionId) return false;
  try {
    const res = await fetch('http://nginx/api/validate_session.php', {
      headers: { 'Cookie': `PHPSESSID=${sessionId}` }
    });
    const text = await res.text();
    const trimmed = text.trim();
    console.log('validate_session.php response:', {
      status: res.status,
      ok: res.ok,
      body: trimmed,
    });
    return res.ok && trimmed === 'OK';
  } catch (e) {
    console.error('Error validating session:', e);
    return false;
  }
}

function heartbeat() {
  this.isAlive = true;
}



wss.on('connection', async function connection(ws, req) {
  console.log('--- New WebSocket connection attempt ---');
  console.log('Request headers:', req.headers);
  const cookies = cookie.parse(req.headers.cookie || '');
  console.log('Parsed cookies:', cookies);
  const sessionId = cookies.PHPSESSID;
  if (!sessionId) {
    console.log('Rejected WebSocket connection: no PHPSESSID cookie found.');
    ws.close();
    return;
  }
  console.log('Validating session:', sessionId);
  const valid = await isSessionValid(sessionId);
  console.log('Session valid:', valid);
  if (!valid) {
    console.log('Rejected WebSocket connection: session validation failed for PHPSESSID', sessionId);
    ws.close();
    return;
  }
  ws.isAlive = true;
  ws.on('pong', heartbeat);
  console.log('Client connected');

  ws.on('error', (err) => {
    console.error('WebSocket error:', err);
  });

  ws.on('close', () => {
    console.log('WebSocket connection closed');
  });
});

const interval = setInterval(function ping() {
  wss.clients.forEach(function each(ws) {
    if (ws.isAlive === false) return ws.terminate();
    ws.isAlive = false;
    ws.ping();
  });
  // console.log('Heartbeat: sent ping to all clients at', new Date().toISOString());
}, 30000); // 30 seconds

wss.on('close', function close() {
  clearInterval(interval);
});

const server = http.createServer((req, res) => {
    if (req.method === 'POST' && req.url === '/broadcast') {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', () => {
            console.log('Broadcasting:', body);
            wss.clients.forEach(client => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(body);
                }
            });
            res.writeHead(200, { 'Content-Type': 'text/plain' });
            res.end('ok');
        });
    } else {
        res.writeHead(404);
        res.end();
    }
});

server.listen(8082, '0.0.0.0', () => {
    console.log('WS: 8081 | Broadcast API: 8082');
});