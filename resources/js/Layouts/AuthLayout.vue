<script setup>
/**
 * AuthLayout.vue
 * ------------------------------------------------------------------
 * Shared shell for every standalone (pre-login / no-sidebar) auth
 * page: left branding panel + right form panel frame. Extracted from
 * what was originally all inline in Auth/Login.vue, since 5 more auth
 * pages (Register, Forgot/Reset/Confirm Password) need this exact
 * same shell — duplicating ~300 lines of CSS and markup per page
 * would have meant 6 copies of the same branding panel to keep in
 * sync forever. Login.vue itself was refactored to use this too, so
 * there's exactly one copy of this shell now, not six.
 *
 * Every page using this layout supplies its own form content via the
 * default slot — alerts, heading, the actual <form>, and any footer
 * note. The branding panel, logo, and mobile logo are identical on
 * every auth page and live here.
 *
 * The <style> block below is intentionally NOT scoped: every zav-*
 * class (shell AND form-field styles like zav-input/zav-btn-submit/
 * zav-alert) lives here so every auth page's own template can use
 * them without redeclaring the CSS. Safe to be global — the `zav-`
 * prefix is unique to this auth flow and never used by the main
 * `cvr-` app styling.
 */
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const authModules = [
    { name: 'Dashboard' },
    { name: 'Overdrafts' },
    { name: 'Letter of Guarantee' },
    { name: 'Letter of Credit' },
    { name: 'Customer Section', gold: true },
    { name: 'Supplier Section', gold: true },
    { name: 'Int. Transfers' },
    { name: 'FX Trading' },
    { name: 'FX Rates' },
    { name: 'Customer Aging' },
    { name: 'Supplier Aging' },
    { name: 'Expense Payment' },
];
const authStats = [
    { value: '12', label: 'Modules' },
    { value: '5', label: 'User Roles' },
    { value: '100%', label: 'Secure & Private', gold: true },
];
const currentYear = new Date().getFullYear();
// Dynamic :src (not a static src="/...") — see Login.vue's original
// comment on this: a static src gets compiled into a JS import Vite
// then tries to resolve as a module, which fails for public/ assets.
const logoUrl = '/images/cashvero-logo.png';
</script>

<template>
    <div class="zav-root">
        <!-- Left branding panel -->
        <div class="zav-left">
            <div class="zav-grid-bg"></div>
            <div class="zav-glow-teal"></div>
            <div class="zav-glow-gold"></div>
            <div class="zav-diag"></div>

            <div class="zav-brand">
                <div class="zav-logo-mark">
                    <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="11" width="20" height="10" rx="1" />
                        <path d="M6 11V8a6 6 0 0112 0v3" />
                        <line x1="12" y1="15" x2="12" y2="17" />
                    </svg>
                </div>
                <div>
                    <div class="zav-brand-name">CashVero</div>
                    <div class="zav-brand-sub">{{ $t('Cash & Banking Facilities') }}</div>
                </div>
            </div>

            <div class="zav-mid">
                <p class="zav-eyebrow">{{ $t('Financial Intelligence & Control') }}</p>
                <h1 class="zav-headline">
                    {{ $t('Beyond Transactions') }}<br />
                    <span class="zav-headline-accent">{{ $t('Insight That Drives Decisions') }}</span>
                </h1>
                <p class="zav-tagline">
                    {{ $t('Built for Finance Teams & Treasury Managers') }}<br />
                    {{ $t('Monitor, Control & Optimise.') }}
                </p>

                <p class="zav-modules-label">{{ $t('12 Active Modules') }}</p>
                <div class="zav-modules-grid">
                    <div v-for="module in authModules" :key="module.name" class="zav-mod" :class="{ 'zav-mod-gold': module.gold }">
                        <span class="zav-mod-dot" :class="{ 'zav-mod-dot-gold': module.gold }"></span>
                        {{ $t(module.name) }}
                    </div>
                </div>
            </div>

            <div class="zav-bottom">
                <div class="zav-stats">
                    <div v-for="stat in authStats" :key="stat.label">
                        <div class="zav-stat-num" :class="{ 'zav-stat-gold': stat.gold }">{{ stat.value }}</div>
                        <div class="zav-stat-label">{{ $t(stat.label) }}</div>
                    </div>
                </div>
                <p class="zav-footer-left">
                    © {{ currentYear }} {{ $t('CashVero · Built by') }}
                    <span class="zav-footer-squad">{{ $t('SQUAD Business Consulting') }}</span>
                    {{ $t('· Cairo, Egypt') }}
                </p>
            </div>
        </div>

        <!-- Right form panel -->
        <div class="zav-right">
            <div class="zav-lang-switch">
                <LanguageSwitcher />
            </div>
            <div class="zav-logo-area">
                <img :src="logoUrl" alt="CashVero" class="zav-logo-img" />
            </div>

            <div class="zav-form-wrap">
                <div class="zav-form-inner">
                    <div class="mobile-logo">
                        <img :src="logoUrl" alt="CashVero" />
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style>
* { box-sizing: border-box; }

.zav-root {
    min-height: 100vh;
    display: flex;
    background-color: #0C1829;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.zav-left {
    width: 52%;
    background-color: #0C1829;
    padding: 1.25rem 3rem 1.5rem;
    flex-direction: column;
    justify-content: flex-start;
    gap: 1.25rem;
    position: relative;
    overflow: hidden;
    display: none;
}
@media (min-width: 1024px) {
    .zav-left { display: flex; }
}

.zav-grid-bg {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(20,144,168,0.055) 1px, transparent 1px),
        linear-gradient(90deg, rgba(20,144,168,0.055) 1px, transparent 1px);
    background-size: 38px 38px;
}

.zav-glow-teal {
    position: absolute;
    top: -80px; right: -80px;
    width: 340px; height: 340px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(20,144,168,0.16) 0%, transparent 70%);
    pointer-events: none;
}
.zav-glow-gold {
    position: absolute;
    bottom: -100px; left: -60px;
    width: 300px; height: 300px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(186,117,23,0.13) 0%, transparent 70%);
    pointer-events: none;
}
.zav-diag {
    position: absolute;
    top: 0; right: 140px;
    width: 1.5px;
    height: 100%;
    background: linear-gradient(to bottom, transparent, rgba(25,221,7,0.22), transparent);
    transform: rotate(16deg) translateX(40px);
    pointer-events: none;
}

.zav-brand {
    position: relative; z-index: 2;
    display: flex; align-items: center; gap: 0.75rem;
}
.zav-logo-mark {
    width: 38px; height: 38px;
    background: linear-gradient(135deg, #074b12, #328f46);
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.zav-logo-mark svg { width: 20px; height: 20px; color: white; stroke: white; }
.zav-brand-name {
    font-size: 1.1rem; font-weight: 800;
    color: #F1F5F9; letter-spacing: 0.06em; line-height: 1.1;
}
.zav-brand-sub {
    font-size: 0.6rem; font-weight: 500;
    color: #8bc494; letter-spacing: 0.14em;
    text-transform: uppercase; margin-top: 1px;
}

.zav-mid { position: relative; z-index: 2; margin-top: 0; }
.zav-eyebrow {
    font-size: 1rem; font-weight: 600;
    letter-spacing: 0.14em; text-transform: uppercase;
    color: #0dafe0; margin-bottom: 0.8rem;
}
.zav-headline {
    font-size: 1.75rem; font-weight: 600;
    color: #F1F5F9; line-height: 1.5;
    margin-bottom: 0.8rem; letter-spacing: 0.05em;
    padding: 5px 0;
}
.zav-headline-accent { color: #289236; }
.zav-tagline {
    font-size: 1rem; color: #ffffff;
    line-height: 1.7; margin-bottom: 1.5rem;
}

.zav-modules-label {
    font-size: 0.8rem; font-weight: 600;
    letter-spacing: 0.14em; text-transform: uppercase;
    color: #87883f; margin-bottom: 0.6rem;
}
.zav-modules-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.zav-mod {
    background: rgba(17,34,64,0.75);
    border: 1px solid #1b5834;
    border-radius: 7px;
    padding: 7px 10px;
    font-size: 0.8rem; color: #ffffff;
    transition: border-color 0.18s ease, color 0.18s ease, background 0.18s ease;
    display: flex; align-items: center; gap: 6px;
    cursor: default;
}
.zav-mod:hover {
    border-color: rgba(20,144,168,0.45);
    color: #48C4D8;
    background: rgba(20,144,168,0.05);
}
.zav-mod-dot {
    width: 5px; height: 5px;
    border-radius: 50%; background: #1490A8;
    opacity: 0.7; flex-shrink: 0;
}
.zav-mod-dot-gold { background: #BA7517; }
.zav-mod-gold:hover {
    border-color: rgba(186,117,23,0.45);
    color: #FAC775;
    background: rgba(186,117,23,0.05);
}

.zav-bottom { position: relative; z-index: 2; }
.zav-stats {
    display: flex; gap: 2rem;
    padding-top: 1.1rem;
    border-top: 1px solid #1B3558;
    margin-bottom: 0.75rem;
}
.zav-stat-num {
    font-size: 1.5rem; font-weight: 800;
    color: #1490A8; line-height: 1.1;
}
.zav-stat-gold { color: #289236; }
.zav-stat-label { font-size: 0.65rem; color: #6B96B8; margin-top: 2px; }
.zav-footer-left { font-size: 0.65rem; color: #1490A8; }
.zav-footer-squad { color: #ffffff; }

.zav-right {
    width: 100%;
    background-color: #0E1E34;
    border-inline-start: 1px solid #1B3558;
    display: flex; flex-direction: column;
    position: relative;
}
@media (min-width: 1024px) {
    .zav-right { width: 48%; }
}

.zav-lang-switch {
    position: absolute;
    top: 1rem;
    inset-inline-end: 1rem;
    z-index: 5;
}
.zav-logo-area {
    padding: 1rem 1.5rem 0.5rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-bottom: -40px;
}
.zav-logo-img {
    height: auto;
    max-height: 160px;
    width: auto;
    max-width: min(100%, 320px);
    object-fit: contain;
    margin-top: 0;
    margin-bottom: 10px;
}

.zav-form-wrap {
    flex: 1; display: flex;
    align-items: flex-start; justify-content: center;
    padding: 0 2.5rem 1.5rem;
}
.zav-form-inner { width: 100%; max-width: 360px; }

.mobile-logo {
    display: flex; justify-content: center; margin-bottom: 2rem;
}
.mobile-logo img {
    height: auto;
    max-height: 4.5rem;
    width: auto;
    max-width: 220px;
    object-fit: contain;
}
@media (min-width: 1024px) {
    .mobile-logo { display: none; }
}

.zav-form-header { margin-bottom: 0.75rem; }
.zav-welcome {
    font-size: 1.4rem; font-weight: 800;
    color: #F1F5F9; letter-spacing: -0.01em; line-height: 1.2;
}
.zav-welcome-line {
    height: 2px; width: 300px;
    background: linear-gradient(90deg, #1490A8, transparent);
    border-radius: 2px; margin: 5px 0 6px;
}
.zav-welcome-sub { font-size: 0.78rem; color: #6B96B8; }

.zav-form { display: flex; flex-direction: column; gap: 0; }
.zav-field { margin-bottom: 1rem; }
.zav-label {
    display: block; font-size: 0.7rem; font-weight: 600;
    letter-spacing: 0.13em; text-transform: uppercase;
    color: #48C4D8; margin-bottom: 0.45rem;
}
.zav-input {
    width: 100%; background-color: #112240;
    border: 1px solid #1B3558; border-radius: 8px;
    padding: 0.65rem 0.9rem; font-size: 0.85rem; color: #F1F5F9;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    outline: none;
}
.zav-input::placeholder { color: #3d5a80; }
.zav-input:focus {
    border-color: #1490A8;
    box-shadow: 0 0 0 3px rgba(20,144,168,0.12);
}
.zav-input.is-invalid {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239,68,68,0.15);
}
.zav-input:disabled { opacity: 0.65; cursor: not-allowed; }

.zav-pw-label-row {
    display: flex; align-items: center;
    justify-content: space-between; margin-bottom: 0.45rem;
}
.zav-forgot {
    font-size: 0.9rem; color: #ffffff;
    transition: color 0.15s ease; text-decoration: none;
}
.zav-forgot:hover { color: #1490A8; }
.zav-input-wrap { position: relative; }
.zav-input-pw { padding-inline-end: 2.75rem; }
.zav-eye-btn {
    position: absolute; inset-inline-end: 0.75rem; top: 50%;
    transform: translateY(-50%);
    background: transparent; border: none; cursor: pointer;
    color: #1B3558; transition: color 0.15s ease;
    padding: 0; display: flex; align-items: center;
}
.zav-eye-btn:hover { color: #6B96B8; }
.zav-eye-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; }

.zav-remember {
    display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.2rem;
    cursor: pointer;
}
.zav-checkbox {
    width: 18px; height: 18px; border-radius: 4px;
    background: transparent; border: 1.5px solid #1B3558;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
    flex-shrink: 0;
}
.zav-checkbox.checked { background: #1490A8; border-color: #1490A8; }
.zav-checkbox svg { width: 10px; height: 10px; stroke: white; fill: none; display: none; }
.zav-checkbox.checked svg { display: block; }
.zav-remember-text { font-size: 0.78rem; color: #6B96B8; }

.zav-btn-submit {
    width: 100%;
    background: linear-gradient(135deg, #095309, #063d06);
    border: 1px solid rgba(83,214,105,0.4);
    border-radius: 8px; padding: 0.75rem;
    font-size: 0.88rem; font-weight: 700;
    color: #FAEEDA; letter-spacing: 0.03em;
    cursor: pointer;
    box-shadow: 0 4px 18px rgba(1,17,3,0.4);
    transition: all 0.2s ease;
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.zav-btn-submit:hover {
    background: linear-gradient(135deg, #07148b, #323c92);
    box-shadow: 0 6px 26px rgba(50,127,199,0.38);
    transform: translateY(-1px);
}
.zav-btn-submit:active { transform: translateY(0); }
.zav-btn-submit:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

.zav-access-note { margin-top: 1.25rem; }
.zav-access-divider {
    display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem;
}
.zav-access-line { flex: 1; height: 1px; background: #1B3558; }
.zav-access-text {
    font-size: 0.62rem; color: #3d5a80;
    letter-spacing: 0.1em; text-transform: uppercase; white-space: nowrap;
}
.zav-access-copy {
    text-align: center; font-size: 0.7rem;
    color: #3d5a80; line-height: 1.7;
}

.zav-back-link {
    display: inline-flex; align-items: center; gap: 0.4rem;
    font-size: 0.8rem; color: #6B96B8;
    text-decoration: none; margin-bottom: 1rem;
    transition: color 0.15s ease;
}
.zav-back-link:hover { color: #1490A8; }

.zav-alert {
    border-radius: 8px;
    padding: 0.75rem 0.9rem;
    margin-bottom: 1rem;
    font-size: 0.8rem;
    line-height: 1.5;
}
.zav-alert-danger {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #fca5a5;
}
.zav-alert-success {
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.35);
    color: #6ee7b7;
}
.zav-alert ul { margin: 0; padding-inline-start: 1.1rem; }
</style>
