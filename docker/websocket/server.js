const WebSocket = require('ws');
const http = require('http');

const wss = new WebSocket.Server({ port: 8081, host: '0.0.0.0' });

function heartbeat() {
  this.isAlive = true;
}

wss.on('connection', function connection(ws) {
  ws.isAlive = true;
  ws.on('pong', heartbeat);
  console.log('Client connected');
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