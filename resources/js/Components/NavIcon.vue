<script setup>
/**
 * NavIcon.vue
 * ------------------------------------------------------------------
 * Renders a single Lucide outline icon for the CashVero sidebar /
 * header. Only the icons used by SidebarMenu + AppLayout header are
 * imported (tree-shakeable) — never the full Lucide set.
 *
 * `name` is the Lucide kebab-case id (e.g. 'home', 'banknote'),
 * matching the `icon` strings emitted by app/Support/SidebarMenu.php.
 */
import { computed } from 'vue';
import {
    Home,
    Banknote,
    LayoutDashboard,
    FileText,
    ChartLine,
    ChartColumn,
    ChartPie,
    Landmark,
    Users,
    Truck,
    Wallet,
    ScrollText,
    Link,
    Vault,
    Settings,
    Archive,
    Receipt,
    FileBadge,
    Building,
    Files,
    CreditCard,
    Handshake,
    ArrowDownCircle,
    Building2,
    TrendingDown,
    CircleDollarSign,
    FileStack,
    Hourglass,
    Target,
    Upload,
    Download,
    RefreshCw,
    ArrowLeftRight,
    Coins,
    Currency,
    Minus,
    Factory,
    Radio,
    UserRound,
    Bell,
    Sparkles,
    TrendingUp,
    Menu,
    Wrench,
    User,
    Sun,
    Moon,
    LogOut,
    ChevronDown,
    ChevronRight,
    Circle,
    Shield,
} from '@lucide/vue';

const props = defineProps({
    name: { type: String, default: 'circle' },
    size: { type: [Number, String], default: 16 },
    strokeWidth: { type: [Number, String], default: 1.75 },
});

const ICONS = {
    home: Home,
    banknote: Banknote,
    'layout-dashboard': LayoutDashboard,
    'file-text': FileText,
    'chart-line': ChartLine,
    'chart-column': ChartColumn,
    'chart-pie': ChartPie,
    landmark: Landmark,
    users: Users,
    truck: Truck,
    wallet: Wallet,
    'scroll-text': ScrollText,
    link: Link,
    vault: Vault,
    settings: Settings,
    archive: Archive,
    receipt: Receipt,
    'file-badge': FileBadge,
    building: Building,
    files: Files,
    'credit-card': CreditCard,
    handshake: Handshake,
    'arrow-down-circle': ArrowDownCircle,
    'building-2': Building2,
    'trending-down': TrendingDown,
    'circle-dollar-sign': CircleDollarSign,
    'file-stack': FileStack,
    hourglass: Hourglass,
    target: Target,
    upload: Upload,
    download: Download,
    'refresh-cw': RefreshCw,
    'arrow-left-right': ArrowLeftRight,
    coins: Coins,
    currency: Currency,
    minus: Minus,
    factory: Factory,
    radio: Radio,
    'user-round': UserRound,
    bell: Bell,
    sparkles: Sparkles,
    'trending-up': TrendingUp,
    menu: Menu,
    wrench: Wrench,
    user: User,
    sun: Sun,
    moon: Moon,
    'log-out': LogOut,
    'chevron-down': ChevronDown,
    'chevron-right': ChevronRight,
    circle: Circle,
    shield: Shield,
};

const icon = computed(() => ICONS[props.name] ?? Circle);
</script>

<template>
    <component
        :is="icon"
        :size="Number(size)"
        :stroke-width="Number(strokeWidth)"
        class="cvr-nav-icon-svg shrink-0"
        aria-hidden="true"
    />
</template>
