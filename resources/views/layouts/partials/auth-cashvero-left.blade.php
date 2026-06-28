@php
    $authModules = [
        ['name' => 'Dashboard'],
        ['name' => 'Overdrafts'],
        ['name' => 'Letter of Guarantee'],
        ['name' => 'Letter of Credit'],
        ['name' => 'Customer Section', 'gold' => true],
        ['name' => 'Supplier Section', 'gold' => true],
        ['name' => 'Int. Transfers'],
        ['name' => 'FX Trading'],
        ['name' => 'FX Rates'],
        ['name' => 'Customer Aging'],
        ['name' => 'Supplier Aging'],
        ['name' => 'Expense Payment'],
    ];
    $authStats = [
        ['value' => '12', 'label' => 'Modules'],
        ['value' => '5', 'label' => 'User Roles'],
        ['value' => '100%', 'label' => 'Secure & Private', 'gold' => true],
    ];
@endphp
<div class="zav-left">
    <div class="zav-grid-bg"></div>
    <div class="zav-glow-teal"></div>
    <div class="zav-glow-gold"></div>
    <div class="zav-diag"></div>

    <div class="zav-brand">
        <div class="zav-logo-mark">
            <svg viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="11" width="20" height="10" rx="1"/>
                <path d="M6 11V8a6 6 0 0112 0v3"/>
                <line x1="12" y1="15" x2="12" y2="17"/>
            </svg>
        </div>
        <div>
            <div class="zav-brand-name">CashVero</div>
            <div class="zav-brand-sub">Cash &amp; Banking Facilities</div>
        </div>
    </div>

    <div class="zav-mid">
        <p class="zav-eyebrow">Financial Intelligence &amp; Control</p>
        <h1 class="zav-headline">
            Beyond Transactions<br/>
            <span class="zav-headline-accent">Insight That Drives Decisions</span>
        </h1>
        <p class="zav-tagline">
            Built for Finance Teams &amp; Treasury Managers<br/>
            Monitor, Control &amp; Optimise.
        </p>

        <p class="zav-modules-label">12 Active Modules</p>
        <div class="zav-modules-grid">
            @foreach ($authModules as $module)
                <div class="zav-mod{{ !empty($module['gold']) ? ' zav-mod-gold' : '' }}">
                    <span class="zav-mod-dot{{ !empty($module['gold']) ? ' zav-mod-dot-gold' : '' }}"></span>
                    {{ $module['name'] }}
                </div>
            @endforeach
        </div>
    </div>

    <div class="zav-bottom">
        <div class="zav-stats">
            @foreach ($authStats as $stat)
                <div>
                    <div class="zav-stat-num{{ !empty($stat['gold']) ? ' zav-stat-gold' : '' }}">{{ $stat['value'] }}</div>
                    <div class="zav-stat-label">{{ $stat['label'] }}</div>
                </div>
            @endforeach
        </div>
        <p class="zav-footer-left">
            © {{ date('Y') }} CashVero · Built by
            <span class="zav-footer-squad">SQUAD Business Consulting</span>
            · Cairo, Egypt
        </p>
    </div>
</div>
