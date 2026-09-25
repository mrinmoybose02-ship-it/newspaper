// server.js
const WebSocket = require('ws');
const http = require('http');

// ক্লায়েন্টদের সাথে যোগাযোগ রাখার জন্য WebSocket সার্ভার (পোর্ট 8080)
const wss = new WebSocket.Server({ port: 8080 });
const clients = new Set();

wss.on('connection', function connection(ws) {
    clients.add(ws);
    console.log('নতুন ক্লায়েন্ট সংযুক্ত হয়েছে। মোট ইউজার:', clients.size);

    // ক্লায়েন্ট থেকে মেসেজ এলে (যেমন লাস্ট নিউজ আইডি)
    ws.on('message', function incoming(message) {
        const data = JSON.parse(message);
        if (data.action === 'init') {
            ws.lastId = data.last_id; // ইউজার যে আইডি পর্যন্ত নিউজ পেয়েছে তা সেভ করা
        }
    });

    ws.on('close', function() {
        clients.delete(ws);
        console.log('ক্লায়েন্ট বিচ্ছিন্ন হয়েছে। মোট ইউজার:', clients.size);
    });
});

// পিএইচপি থেকে নোটিফিকেশন পাঠানোর জন্য HTTP সার্ভার (পোর্ট 8081)
const server = http.createServer((req, res) => {
    if (req.method === 'POST') {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', () => {
            try {
                const newsData = JSON.parse(body);
                console.log('পিএইচপি থেকে নতুন নিউজ এসেছে:', newsData.title);
                
                // সকল সংযুক্ত ক্লায়েন্টদের কাছে নোটিফিকেশন পাঠানো হচ্ছে
                clients.forEach(client => {
                    if (client.readyState === WebSocket.OPEN) {
                        // শুধুমাত্র সেই ইউজারদের দেখাবে যাদের কাছে এই নিউজটি নতুন
                        if (!client.lastId || newsData.id > client.lastId) {
                            client.send(JSON.stringify({ status: 'new', id: newsData.id, title: newsData.title }));
                            client.lastId = newsData.id; // ইউজারের লাস্ট আইডি আপডেট করা
                        }
                    }
                });
                
                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ success: true }));
            } catch (e) {
                res.writeHead(400, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({ error: e.message }));
            }
        });
    } else {
        res.writeHead(404);
        res.end('Not Found');
    }
});

server.listen(8081, () => {
    console.log('HTTP Broadcast Server পোর্ট 8081 এ চলছে');
    console.log('WebSocket Server পোর্ট 8080 এ চলছে');
});