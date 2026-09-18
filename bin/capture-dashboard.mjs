import { spawn } from 'child_process';
import fs from 'fs';

async function main() {
    const outputPath = process.argv[2] || '/tmp/dashboard_current.png';
    const tabToClick = process.argv[3] || null;
    const targetPage = process.argv[4] || 'site-checkup-pro';

    // 1. Launch headless chrome
    const chrome = spawn('/usr/bin/google-chrome', [
        '--headless=new',
        '--remote-debugging-port=9225',
        '--no-sandbox',
        '--disable-gpu',
        '--disable-dev-shm-usage',
        '--disable-extensions',
        '--user-data-dir=/tmp/chrome-eval-profile',
        '--window-size=1280,900',
        '--host-resolver-rules=MAP test111.local 127.0.0.1:10003'
    ]);

    await new Promise(r => setTimeout(r, 1500));

    try {
        const versionRes = await fetch('http://127.0.0.1:9225/json/list');
        const targets = await versionRes.json();
        const pageTarget = targets.find(t => t.type === 'page') || targets[0];
        const ws = new WebSocket(pageTarget.webSocketDebuggerUrl);

        await new Promise((resolve, reject) => {
            ws.onopen = resolve;
            ws.onerror = reject;
        });

        let id = 1;
        const callbacks = new Map();
        function send(method, params = {}) {
            return new Promise((resolve, reject) => {
                const msgId = id++;
                callbacks.set(msgId, { resolve, reject });
                ws.send(JSON.stringify({ id: msgId, method, params }));
            });
        }

        ws.onmessage = (event) => {
            const msg = JSON.parse(event.data);
            if (msg.id && callbacks.has(msg.id)) {
                const { resolve, reject } = callbacks.get(msg.id);
                callbacks.delete(msg.id);
                if (msg.error) {
                    reject(new Error(msg.error.message));
                } else {
                    resolve(msg.result);
                }
            }
        };

        await send('Page.enable');
        await send('Runtime.enable');
        await send('Network.enable');
        await send('Network.setCacheDisabled', { cacheDisabled: true });

        // Login first if needed
        await send('Page.navigate', { url: 'http://test111.local/wp-login.php' });
        await new Promise(r => setTimeout(r, 1200));

        // Fill credentials & submit
        await send('Runtime.evaluate', {
            expression: `
                const userField = document.querySelector('#user_login');
                const passField = document.querySelector('#user_pass');
                const submitBtn = document.querySelector('#wp-submit');
                if (userField && passField && submitBtn) {
                    userField.value = 'test';
                    passField.value = 'admin123';
                    submitBtn.click();
                }
            `
        });
        await new Promise(r => setTimeout(r, 1500));

        // Navigate to Site Checkup Pro page
        await send('Page.navigate', { url: `http://test111.local/wp-admin/admin.php?page=${targetPage}` });
        await new Promise(r => setTimeout(r, 2000));

        // If tabToClick specified, click it
        if (tabToClick) {
            await send('Runtime.evaluate', {
                expression: `
                    const tab = document.querySelector('[data-tab="${tabToClick}"]');
                    if (tab) tab.click();
                `
            });
            await new Promise(r => setTimeout(r, 1000));
        }

        // Take screenshot
        const screenshotResult = await send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: false
        });

        fs.writeFileSync(outputPath, Buffer.from(screenshotResult.data, 'base64'));
        console.log(`Screenshot saved to ${outputPath}`);

        ws.close();
    } finally {
        chrome.kill();
    }
}

main().catch(err => {
    console.error('Error:', err);
    process.exit(1);
});
