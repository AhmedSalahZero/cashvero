import { test, expect } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

type CatalogEntry = {
    id: string;
    title: string;
    url: string;
    page?: string;
    optional?: boolean;
};

type PageResult = {
    id: string;
    title: string;
    url: string;
    status: 'pass' | 'fail' | 'skipped';
    issues: string[];
    warnings: string[];
    httpStatus?: number;
};

const catalogPath = path.join(__dirname, 'catalog.json');
const summaryPath = path.join(__dirname, 'summary.json');
const artifactsDir = path.join(__dirname, 'artifacts');

function loadCatalog(): CatalogEntry[] {
    if (!fs.existsSync(catalogPath)) {
        throw new Error(
            `Missing ${catalogPath}. Run: php tests/e2e/mobile/page-catalog.php`
        );
    }
    const raw = JSON.parse(fs.readFileSync(catalogPath, 'utf8'));
    return Array.isArray(raw) ? raw : raw.pages ?? [];
}

const catalog = loadCatalog();
const results: PageResult[] = [];

test.afterAll(() => {
    fs.mkdirSync(path.dirname(summaryPath), { recursive: true });
    const summary = {
        generatedAt: new Date().toISOString(),
        viewport: { width: 375, height: 812 },
        totals: {
            pass: results.filter((r) => r.status === 'pass').length,
            fail: results.filter((r) => r.status === 'fail').length,
            skipped: results.filter((r) => r.status === 'skipped').length,
        },
        byIssue: {} as Record<string, string[]>,
        pages: results,
    };

    for (const page of results) {
        for (const issue of page.issues) {
            (summary.byIssue[issue] ??= []).push(page.id);
        }
    }

    fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));

    const html = [
        '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Mobile Audit Summary</title>',
        '<style>body{font-family:system-ui;padding:1.5rem;background:#0f172a;color:#e2e8f0}',
        'h1{font-size:1.25rem}.pass{color:#34d399}.fail{color:#f87171}.skip{color:#94a3b8}',
        'table{border-collapse:collapse;width:100%}td,th{border:1px solid #334155;padding:.4rem .6rem;text-align:left;font-size:.85rem}',
        'code{font-size:.8rem}</style></head><body>',
        `<h1>Mobile Audit — ${summary.totals.pass} pass / ${summary.totals.fail} fail / ${summary.totals.skipped} skipped</h1>`,
        '<table><thead><tr><th>Status</th><th>Page</th><th>Issues</th><th>URL</th></tr></thead><tbody>',
        ...results.map((r) => {
            const cls = r.status === 'pass' ? 'pass' : r.status === 'fail' ? 'fail' : 'skip';
            return `<tr class="${cls}"><td>${r.status}</td><td>${r.title}</td><td>${[...r.issues, ...r.warnings].join('; ') || '—'}</td><td><code>${r.url}</code></td></tr>`;
        }),
        '</tbody></table></body></html>',
    ].join('\n');
    fs.writeFileSync(path.join(__dirname, 'summary.html'), html);
});

for (const entry of catalog) {
    test(`mobile: ${entry.id}`, async ({ page }, testInfo) => {
        const result: PageResult = {
            id: entry.id,
            title: entry.title,
            url: entry.url,
            status: 'pass',
            issues: [],
            warnings: [],
        };

        const response = await page.goto(entry.url, { waitUntil: 'domcontentloaded', timeout: 45_000 });
        const status = response?.status() ?? 0;
        result.httpStatus = status;

        if (status === 404 || status === 403 || status >= 500) {
            if (entry.optional || status === 404) {
                result.status = 'skipped';
                result.warnings.push(`HTTP ${status}`);
                results.push(result);
                testInfo.annotations.push({ type: 'skip', description: `HTTP ${status}` });
                return;
            }
            result.status = 'fail';
            result.issues.push(`http_${status}`);
            results.push(result);
            expect(status, `${entry.id} returned HTTP ${status}`).toBe(200);
            return;
        }

        // Give Vue/Inertia a beat to hydrate.
        await page.waitForTimeout(400);

        const metrics = await page.evaluate(() => {
            const doc = document.documentElement;
            const body = document.body;
            const main = document.querySelector('main');
            const mainWidth = main ? main.getBoundingClientRect().width : body.getBoundingClientRect().width;
            const scrollWidth = Math.max(doc.scrollWidth, body.scrollWidth);
            const clientWidth = window.innerWidth;

            const smallButtons: string[] = [];
            document.querySelectorAll('button, a.cvr-btn-primary, a.cvr-btn-copper').forEach((el) => {
                const r = (el as HTMLElement).getBoundingClientRect();
                if (r.width > 0 && r.height > 0 && (r.width < 40 || r.height < 32)) {
                    const label = ((el as HTMLElement).innerText || (el as HTMLElement).getAttribute('title') || el.tagName).trim().slice(0, 40);
                    if (label) smallButtons.push(label);
                }
            });

            return {
                scrollWidth,
                clientWidth,
                mainWidth,
                overflow: scrollWidth > clientWidth + 1,
                smallButtons: [...new Set(smallButtons)].slice(0, 8),
            };
        });

        if (metrics.overflow) {
            result.issues.push('horizontal_overflow');
        }
        if (metrics.mainWidth < 280) {
            result.issues.push('main_content_too_narrow');
        }
        if (metrics.smallButtons.length) {
            result.warnings.push(`small_touch_targets: ${metrics.smallButtons.join(', ')}`);
        }

        if (result.issues.length) {
            result.status = 'fail';
            fs.mkdirSync(artifactsDir, { recursive: true });
            const shot = path.join(artifactsDir, `${entry.id.replace(/[^a-z0-9_-]+/gi, '_')}.png`);
            await page.screenshot({ path: shot, fullPage: true });
        }

        results.push(result);

        expect(
            result.issues,
            `${entry.id} mobile issues: ${result.issues.join(', ')} (scroll=${metrics.scrollWidth}, vw=${metrics.clientWidth}, main=${Math.round(metrics.mainWidth)})`
        ).toEqual([]);
    });
}
