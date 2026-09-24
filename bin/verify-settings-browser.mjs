import fs from 'fs';

async function main() {
    const port = process.env.CDP_PORT || '9222';
    const versionRes = await fetch(`http://127.0.0.1:${port}/json/list`);
    const targets = await versionRes.json();
    const pageTarget = targets.find(t => t.type === 'page') || targets[0];
    if (!pageTarget) {
        throw new Error('No Chrome target found on port ' + port);
    }
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
    await send('Emulation.setDeviceMetricsOverride', {
        width: 1280,
        height: 900,
        deviceScaleFactor: 1,
        mobile: false
    });

    console.log('Navigating to login...');
    await send('Page.navigate', { url: 'http://test111.local/wp-login.php' });
    await new Promise(r => setTimeout(r, 1500));

    // Log in if login form is present
    await send('Runtime.evaluate', {
        expression: `
            const user = document.querySelector('#user_login');
            const pass = document.querySelector('#user_pass');
            const btn = document.querySelector('#wp-submit');
            if (user && pass && btn) {
                user.value = 'test';
                pass.value = 'admin123';
                btn.click();
            }
        `
    });
    await new Promise(r => setTimeout(r, 2000));

    console.log('Navigating to Site Checkup Pro admin page...');
    await send('Page.navigate', { url: 'http://test111.local/wp-admin/admin.php?page=site-checkup-pro' });
    await new Promise(r => setTimeout(r, 2500));

    console.log('Opening Settings modal...');
    const openRes = await send('Runtime.evaluate', {
        expression: `
            const btn = document.querySelector('#wpsg-btn-open-settings') || document.querySelector('#wpsg-btn-trigger-settings');
            if (btn) {
                btn.click();
                'opened';
            } else {
                'button_not_found';
            }
        `,
        returnByValue: true
    });
    console.log('Open modal status:', openRes.result?.value);
    await new Promise(r => setTimeout(r, 1500));

    console.log('Filling settings form fields...');
    const fillRes = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const ghsaOptin = document.querySelector('#wpsg-setting-ghsa-optin');
                const ghsaKey = document.querySelector('#wpsg-setting-ghsa-key');

                const osvOptin = document.querySelector('#wpsg-setting-osv-optin');
                const osvKey = document.querySelector('#wpsg-setting-osv-key');

                const nvdOptin = document.querySelector('#wpsg-setting-nvd-optin');
                const nvdKey = document.querySelector('#wpsg-setting-nvd-key');

                const cisaOptin = document.querySelector('#wpsg-setting-cisa-kev-optin');
                const cisaKey = document.querySelector('#wpsg-setting-cisa-kev-key');

                const wpscanOptin = document.querySelector('#wpsg-setting-wpscan-optin');
                const wpscanKey = document.querySelector('#wpsg-setting-wpscan-token');

                if (!ghsaOptin || !osvOptin || !nvdOptin || !cisaOptin || !wpscanOptin) {
                    return { error: 'Some optin checkboxes were not found in DOM' };
                }

                ghsaOptin.checked = true;
                ghsaKey.value = 'ghp_liveBrowserTestToken1';

                osvOptin.checked = true;
                osvKey.value = 'osv_liveBrowserTestKey2';

                nvdOptin.checked = true;
                nvdKey.value = 'nvd_liveBrowserTestKey3';

                cisaOptin.checked = true;
                cisaKey.value = 'cisa_liveBrowserTestKey4';

                wpscanOptin.checked = true;
                wpscanKey.value = 'wpscan_liveBrowserTestKey5';

                const saveBtn = document.querySelector('#wpsg-btn-save-settings');
                if (saveBtn) {
                    saveBtn.click();
                    return { status: 'saving_clicked' };
                }
                return { error: 'Save button not found' };
            })()
        `,
        returnByValue: true
    });
    console.log('Fill & Save status:', fillRes.result?.value);

    // Wait for save confirmation text
    let saved = false;
    for (let i = 0; i < 30; i++) {
        await new Promise(r => setTimeout(r, 500));
        const statusCheck = await send('Runtime.evaluate', {
            expression: `document.querySelector('#wpsg-settings-save-status')?.textContent || ''`,
            returnByValue: true
        });
        const txt = statusCheck.result?.value || '';
        if (txt.includes('Settings saved successfully')) {
            console.log('Save confirmed by UI status text:', txt);
            saved = true;
            break;
        }
    }
    if (!saved) {
        console.warn('Timed out waiting for save status text, proceeding to reload.');
    }

    await new Promise(r => setTimeout(r, 1000));
    console.log('Reloading page to test persistence...');
    await send('Page.reload');
    await new Promise(r => setTimeout(r, 3000));

    console.log('Reopening Settings modal to check persisted values...');
    await send('Runtime.evaluate', {
        expression: `
            const btn = document.querySelector('#wpsg-btn-open-settings') || document.querySelector('#wpsg-btn-trigger-settings');
            if (btn) btn.click();
        `
    });
    await new Promise(r => setTimeout(r, 1500));

    console.log('Inspecting DOM after reload...');
    const domState = await send('Runtime.evaluate', {
        expression: `
            (() => {
                const getStatus = (id) => {
                    const el = document.querySelector(id);
                    return el ? el.textContent.trim() : null;
                };
                const getChecked = (id) => {
                    const el = document.querySelector(id);
                    return el ? el.checked : null;
                };
                return {
                    ghsa: {
                        checked: getChecked('#wpsg-setting-ghsa-optin'),
                        status: getStatus('#wpsg-ghsa-masked-status')
                    },
                    osv: {
                        checked: getChecked('#wpsg-setting-osv-optin'),
                        status: getStatus('#wpsg-osv-masked-status')
                    },
                    nvd: {
                        checked: getChecked('#wpsg-setting-nvd-optin'),
                        status: getStatus('#wpsg-nvd-masked-status')
                    },
                    cisa_kev: {
                        checked: getChecked('#wpsg-setting-cisa-kev-optin'),
                        status: getStatus('#wpsg-cisa-kev-masked-status')
                    },
                    wpscan: {
                        checked: getChecked('#wpsg-setting-wpscan-optin'),
                        status: getStatus('#wpsg-wpscan-masked-status')
                    }
                };
            })()
        `,
        returnByValue: true
    });

    console.log('PERSISTED DOM STATE AFTER BROWSER RELOAD:');
    console.log(JSON.stringify(domState.result?.value, null, 2));

    const screenshotResult = await send('Page.captureScreenshot', {
        format: 'png',
        captureBeyondViewport: false
    });
    const artifactPath = '/home/dayshift/.gemini/antigravity-ide/brain/7e8fa8b0-d1ab-4009-80f2-36d6a174766f/settings_modal_verified.png';
    fs.writeFileSync(artifactPath, Buffer.from(screenshotResult.data, 'base64'));
    console.log('Screenshot saved to:', artifactPath);

    ws.close();
}

main().catch(err => {
    console.error('Error:', err);
    process.exit(1);
});
