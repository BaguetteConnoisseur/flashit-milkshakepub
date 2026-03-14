const WebSocket = require('ws');
const http = require('http');

const wss = new WebSocket.Server({ port: 8081, host: '0.0.0.0' });

wss.on('connection', (ws) => {
    console.log('Client connected');
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