#!/usr/bin/env node
'use strict';

const http = require('http');
const url  = require('url');
const fs   = require('fs');
const path = require('path');

const PORT    = process.env.PORT    || 8081;
const API_KEY = process.env.API_KEY || 'test-key-a';
const REGION  = process.env.REGION  || 'region_a';
const TOTAL   = process.env.TOTAL   ? parseInt(process.env.TOTAL, 10) : null;

const dataPath = path.join(__dirname, 'request.json');
const raw      = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

const ALL_USERS = raw.data.users.map((entry) => {
    const user = { ...entry.User };
    const main = { ...entry.Main };

    if (!user.email) {
        const login = user.name || user.login || user.username || `user_${user.id}`;
        user.email = `${login}@aknet.kg`;
    }
    if (!main.email) {
        main.email = user.email;
    }

    if (!user.decoded_password) {
        user.decoded_password = `pass_${user.id}`;
    }

    return { User: user, Main: main };
});

const USERS = TOTAL !== null ? ALL_USERS.slice(0, TOTAL) : ALL_USERS;

function send(res, status, body) {
    const json = JSON.stringify(body, null, 2);
    res.writeHead(status, {
        'Content-Type':   'application/json',
        'Content-Length': Buffer.byteLength(json),
    });
    res.end(json);
}

const server = http.createServer((req, res) => {
    const parsed = url.parse(req.url, true);
    const reqPath = parsed.pathname.replace(/\/+$/, '');

    const key = req.headers['x-api-key'] || (req.headers['authorization'] || '').replace('Bearer ', '');
    if (key !== API_KEY) {
        return send(res, 401, { error: 'Unauthorized' });
    }

    if (req.method === 'GET' && reqPath === '/users') {
        const page  = Math.max(1, parseInt(parsed.query.page  || '1',    10));
        const limit = Math.max(1, parseInt(parsed.query.limit || '1000', 10));

        const offset = (page - 1) * limit;
        const slice  = USERS.slice(offset, offset + limit);
        const total  = USERS.length;

        return send(res, 200, {
            data: { users: slice },
            pagination: {
                page,
                limit,
                total,
                nextPage: offset + limit < total,
            },
        });
    }

    send(res, 404, { error: 'Not found' });
});

server.listen(PORT, () => {
    console.log(`Billing mock [${REGION}] running on :${PORT} — ${USERS.length} real users, key="${API_KEY}"`);
});
