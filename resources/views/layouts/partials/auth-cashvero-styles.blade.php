<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #0C1829;
        min-height: 100vh;
    }

    .zav-root {
        min-height: 100vh;
        display: flex;
        background-color: #0C1829;
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
        border-left: 1px solid #1B3558;
        display: flex; flex-direction: column;
    }
    @media (min-width: 1024px) {
        .zav-right { width: 48%; }
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
    .zav-input-pw { padding-right: 2.75rem; }
    .zav-eye-btn {
        position: absolute; right: 0.75rem; top: 50%;
        transform: translateY(-50%);
        background: transparent; border: none; cursor: pointer;
        color: #1B3558; transition: color 0.15s ease;
        padding: 0; display: flex; align-items: center;
    }
    .zav-eye-btn:hover { color: #6B96B8; }
    .zav-eye-btn svg { width: 16px; height: 16px; stroke: currentColor; fill: none; }

    .zav-remember {
        display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.2rem;
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

    .zav-btn-secondary {
        width: 100%;
        margin-top: 0.75rem;
        background: transparent;
        border: 1px solid #1B3558;
        border-radius: 8px;
        padding: 0.65rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #6B96B8;
        text-align: center;
        text-decoration: none;
        display: block;
        transition: border-color 0.18s ease, color 0.18s ease;
    }
    .zav-btn-secondary:hover {
        border-color: #1490A8;
        color: #48C4D8;
    }

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
    .zav-alert ul { margin: 0; padding-left: 1.1rem; }
    .zav-field-error {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.72rem;
        color: #fca5a5;
    }
</style>
