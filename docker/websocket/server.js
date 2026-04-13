const WebSocket = require('ws');
const http = require('http');
const fetch = (...args) => import('node-fetch').then(mod => mod.default(...args));
const cookie = require('cookie');

const BROADCAST_SECRET = process.env.BROADCAST_SECRET || '';

const wss = new WebSocket.Server({ port: 8081, host: '0.0.0.0' });

async function isSessionValid(sessionId) {
  if (!sessionId) return false;
  try {
    const res = await fetch('http://nginx/api/validate_session.php', {
      headers: { 'Cookie': `PHPSESSID=${sessionId}` }
    });
    const text = await res.text();
    const trimmed = text.trim();
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
  const cookies = cookie.parse(req.headers.cookie || '');
  const sessionId = cookies.PHPSESSID;
  const url = new URL(req.url, `http://${req.headers.host}`);
  const isPublic = url.searchParams.get('public') === '1';
  
  // Allow public connections OR valid sessions
  if (!isPublic) {
    if (!sessionId) {
      console.warn('Rejected WebSocket connection: missing session cookie');
      ws.close();
      return;
    }
    const valid = await isSessionValid(sessionId);
    if (!valid) {
      console.warn('Rejected WebSocket connection: invalid session');
      ws.close();
      return;
    }
  } else {
    console.log('Public connected (read-only)');
  }
  
  ws.isAlive = true;
  ws.isPublic = isPublic;
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
    const providedSecret = req.headers['x-broadcast-secret'] || '';
    if (!BROADCAST_SECRET || providedSecret !== BROADCAST_SECRET) {
      res.writeHead(403, { 'Content-Type': 'text/plain' });
      res.end('forbidden');
      return;
    }

        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', () => {
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