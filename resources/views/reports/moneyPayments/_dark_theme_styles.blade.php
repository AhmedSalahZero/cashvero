{{-- Dark theme: Money Payments + Money Received + Vue MR index. Wrap main content in .money-flow-dark --}}
<style>
.money-flow-dark {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    /* لوحة موحّدة: مدفوعات + استلام + Vue */
    --bg-page: #0c1829;
    --bg-card: #112240;
    --bg-card-hover: #162d54;
    --bg-input: #0c1829;
    --border: #1490a833;
    --border-solid: #1490a8;
    --border-focus: #00b4c8;
    --teal: #00b4c8;
    --teal-dark: #009eb5;
    --teal-subtle: rgba(0, 180, 200, 0.15);
    --readonly-bg: rgba(20, 144, 168, 0.18);
    --gold: #c9a84c;
    --gold-dark: #a6852a;
    --gold-subtle: rgba(201,168,76,0.10);
    --text-primary: #e2e8f0;
    --select-text: #ffffff;
    --text-secondary: white;
    --text-muted: #64748b;
    --danger: #ef4444;
    --success: #10b981;
    --warning: #f59e0b;
    background: var(--bg-page) !important;
    color: var(--text-primary);
    padding: 0.75rem 0.5rem 1.5rem;
    border-radius: 10px;
    min-height: calc(100vh - 220px);
    color-scheme: dark;
}

.money-flow-dark .page-link {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    color: var(--teal) !important;
}

.money-flow-dark .page-item.active .page-link {
    background: var(--teal) !important;
    color: #0C1829 !important;
    border-color: var(--teal) !important;
}

/* كل الـ portlets داخل الصفحة (بدون استثناء الأبيض الافتراضي من Metronic) */
#kt_content .money-flow-dark .kt-portlet,
.money-flow-dark .kt-portlet {
    background: var(--bg-card) !important;
    background-color: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.45) !important;
    border-top: 3px solid var(--teal) !important;
    overflow: visible !important;
}

.money-flow-dark .kt-portlet__body {
    background: transparent !important;
    background-color: transparent !important;
    color: var(--text-primary);
    padding-top: 0 !important;
}

.money-flow-dark .kt-portlet__head {
    background: var(--bg-card-hover) !important;
    background-color: var(--bg-card-hover) !important;
	margin-bottom: 10px !important;
}

.money-flow-dark .kt-portlet.kt-portlet--tabs {
    background: var(--bg-card) !important;
    background-color: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.5) !important;
    border-top: 3px solid var(--teal) !important;
    overflow: visible !important;
}

.money-flow-dark .kt-portlet.kt-portlet--tabs > .kt-portlet__head {
    border-bottom: 3px solid var(--teal) !important;
}

.money-flow-dark .kt-portlet__head .nav-link {
    color: var(--text-secondary) !important;
    font-weight: 600;
}

.money-flow-dark .kt-portlet__head .nav-link.active {
    color: var(--teal) !important;
}

.money-flow-dark .kt-portlet.kt-portlet--mobile {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 24px rgba(0,0,0,0.5) !important;
    border-top: 3px solid var(--teal) !important;
    overflow: visible !important;
}

.money-flow-dark .kt-portlet.kt-portlet--mobile .kt-portlet__head.kt-portlet__head--lg {
    background: var(--bg-card-hover) !important;
    border-bottom: 1px solid var(--border) !important;
}

.money-flow-dark .kt-portlet.kt-portlet--mobile .kt-portlet__head-title,
.money-flow-dark .kt-portlet.kt-portlet--mobile h3.kt-portlet__head-title {
    color: var(--teal) !important;
    border-left: 4px solid var(--gold);
    padding-left: 0.5rem;
}

.money-flow-dark .table {
    background: var(--bg-card) !important;
    background-color: var(--bg-card) !important;
    color: var(--text-primary);
}

.money-flow-dark .table thead tr th,
.money-flow-dark .table thead tr.table-standard-color th {
    background: #0c1829 !important;
    color: var(--teal) !important;
    text-transform: uppercase;
    font-size: 0.75rem;
    border-color: var(--border) !important;
}

.money-flow-dark .table tbody td {
    color: var(--text-primary) !important;
    border-color: var(--border) !important;
    padding: 12px 16px !important;
    vertical-align: middle !important;
}

.money-flow-dark .table tbody tr:hover {
    background: var(--teal-subtle) !important;
    box-shadow: inset 3px 0 0 var(--teal);
}

#kt_content .money-flow-dark select,
.money-flow-dark .form-control,
.money-flow-dark select,
.money-flow-dark select.form-control,
.money-flow-dark textarea.form-control,
.money-flow-dark .custom-select {
    background: var(--bg-input) !important;
    background-color: var(--bg-input) !important;
    border: 1px solid var(--border) !important;
    border-radius: 6px !important;
    color: var(--text-primary) !important;
}

/* نصوص القوائم المنسدلة الأصلية — أبيض صريح (+ webkit للمتصفحات التي تتجاهل color) */
#kt_content .money-flow-dark select,
.money-flow-dark select,
.money-flow-dark select.form-control,
.money-flow-dark .custom-select {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

.money-flow-dark select.form-control::-ms-expand {
    display: none;
}

.money-flow-dark select option,
.money-flow-dark .custom-select option {
    background-color: var(--bg-card) !important;
    color: var(--select-text) !important;
}

.money-flow-dark .form-control::placeholder {
    color: var(--text-muted) !important;
}

.money-flow-dark .form-control:focus,
.money-flow-dark select:focus,
.money-flow-dark .custom-select:focus {
    border-color: var(--border-focus) !important;
    box-shadow: 0 0 0 3px rgba(0, 180, 200, 0.2) !important;
    background-color: var(--bg-input) !important;
    color: var(--text-primary) !important;
}

.money-flow-dark select:focus,
.money-flow-dark .custom-select:focus {
    color: var(--select-text) !important;
}

/* بدون uppercase حتى لا تظهر NAME / SELECT بشكل مبالغ */
.money-flow-dark label,
.money-flow-dark .label {
    color: var(--text-secondary) !important;
    font-weight: 500 !important;
    font-size: 0.875rem !important;
    text-transform: none !important;
    letter-spacing: 0.01em;
}

.money-flow-dark .required-label,
.money-flow-dark .text-danger.required-label {
    color: var(--gold) !important;
}

/* عناوين خيارات Metronic (checkbox rows) — واضحة على الخلفية الداكنة */
.money-flow-dark .kt-option__title,
.money-flow-dark .kt-option__title b,
.money-flow-dark .kt-option__title strong {
    color: #ffffff !important;
}

/* صف «Select All» يستخدم bg-secondary — النص أسود للقراءة على الخلفية الفاتحة */
.money-flow-dark .kt-option.bg-secondary .kt-option__title,
.money-flow-dark .kt-option.bg-secondary .kt-option__title b,
.money-flow-dark .kt-option.bg-secondary .kt-option__title strong {
    color: #1a1a1a !important;
}

.money-flow-dark input.form-control[disabled]:not(.ignore-global-style),
.money-flow-dark input.form-control:not(.is-date-css)[readonly],
.money-flow-dark textarea.form-control[disabled],
.money-flow-dark textarea.form-control[readonly],
.money-flow-dark .form-control:disabled {
    background-color: var(--readonly-bg) !important;
    color: var(--text-primary) !important;
    border-color: var(--border) !important;
    opacity: 1 !important;
    font-weight: 600 !important;
}

.money-flow-dark select:disabled,
.money-flow-dark select.form-control:disabled {
    color: var(--select-text) !important;
}

.money-flow-dark .input-group.date .form-control.is-date-css,
.money-flow-dark .input-group.date .form-control.is-date-css:focus,
.money-flow-dark .input-group.date .form-control:focus {
    background-color: var(--bg-input) !important;
    color: var(--text-primary) !important;
    border-color: var(--border) !important;
    box-shadow: none !important;
}

.money-flow-dark .input-group.date .form-control.is-date-css:focus,
.money-flow-dark .input-group.date .form-control:focus {
    border-color: var(--teal) !important;
    box-shadow: 0 0 0 3px rgba(0, 180, 200, 0.2) !important;
}

.money-flow-dark hr {
    border-top-color: var(--border) !important;
    opacity: 1;
}

.money-flow-dark .text-muted {
    color: var(--text-secondary) !important;
}

.money-flow-dark .text-primary {
    color: var(--teal) !important;
}

.money-flow-dark .kt-portlet__head-title,
.money-flow-dark h3.kt-portlet__head-title {
    color: var(--teal) !important;
}

.money-flow-dark .table-bordered,
.money-flow-dark .table-bordered td,
.money-flow-dark .table-bordered th {
    border-color: var(--border) !important;
}

.money-flow-dark .table td,
.money-flow-dark .table th {
    border-color: var(--border) !important;
}

.money-flow-dark .modal .close,
.money-flow-dark .modal .close span {
    color: var(--text-primary) !important;
    opacity: 0.85;
    text-shadow: none;
}

.money-flow-dark .modal-title {
    color: var(--teal) !important;
}

.money-flow-dark .btn-primary,
.money-flow-dark .btn-success:not(.bg-red) {
    background: var(--teal) !important;
    color: white !important;
    border: none !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    transition: all 0.2s ease !important;
}

.money-flow-dark .btn-primary:hover,
.money-flow-dark .btn-success:hover {
    background: var(--teal-dark) !important;
    box-shadow: inset 3px 0 0 var(--gold);
}

.money-flow-dark .btn-secondary {
    background: transparent !important;
    color: var(--teal) !important;
    border: 1px solid var(--teal) !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
}

.money-flow-dark .btn-secondary:hover {
    background: var(--teal-subtle) !important;
}

.money-flow-dark .btn-danger {
    background: var(--danger) !important;
    color: #fff !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
}

.money-flow-dark .active-style,
.money-flow-dark a.active-style {
    background: var(--gold) !important;
    color: white !important;
    border-radius: 6px !important;
    font-weight: 600 !important;
    border: none !important;
}

.money-flow-dark .active-style:hover {
    background: var(--gold-dark) !important;
}

.money-flow-dark .color-green,
.money-flow-dark td.color-green {
    background: rgba(16,185,129,0.15) !important;
    color: var(--success) !important;
}

.money-flow-dark .modal-content {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    border-radius: 10px !important;
    border-top: 3px solid var(--teal) !important;
}

.money-flow-dark .modal-header {
    background: var(--bg-card-hover) !important;
    border-bottom: 1px solid var(--border) !important;
}

.money-flow-dark .modal-header.blue {
    border-bottom-color: var(--border) !important;
}

.money-flow-dark .modal-footer {
    border-top: 1px solid var(--border) !important;
}

.money-flow-dark .datepicker {
    background: var(--bg-card) !important;
    color: var(--text-primary) !important;
    border: 1px solid var(--border) !important;
}

.money-flow-dark .datepicker table tr td.active,
.money-flow-dark .datepicker table tr td.active:hover {
    background: var(--teal) !important;
}

.money-flow-dark .datepicker table tr td.today {
    border: 1px solid var(--gold) !important;
}

.money-flow-dark .datepicker table tr th,
.money-flow-dark .datepicker .datepicker-switch {
    color: var(--text-secondary) !important;
}

.money-flow-dark .select2-container--default .select2-selection--single {
    background: var(--bg-input) !important;
    border: 1px solid var(--border) !important;
    border-radius: 6px !important;
    color: var(--select-text) !important;
}

.money-flow-dark .select2-dropdown {
    background: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    color: var(--select-text) !important;
}

.money-flow-dark .select2-container--default .select2-results__option {
    color: var(--select-text) !important;
}

.money-flow-dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: var(--teal) !important;
}

.money-flow-dark .select2-container--default .select2-selection__rendered {
    color: var(--select-text) !important;
}

.money-flow-dark .select2-container--default .select2-selection--multiple {
    background: var(--bg-input) !important;
    background-color: var(--bg-input) !important;
    border: 1px solid var(--border) !important;
}

.money-flow-dark .select2-container--default .select2-search--inline .select2-search__field {
    color: var(--select-text) !important;
}

/* Bootstrap-select — زر الاسم/المورد (select2-select في اللayout = selectpicker) */
.money-flow-dark .bootstrap-select {
    width: 100% !important;
    max-width: 100%;
}

.money-flow-dark .bootstrap-select > .dropdown-toggle,
.money-flow-dark .bootstrap-select > .btn.dropdown-toggle,
.money-flow-dark .bootstrap-select > .dropdown-toggle.btn-light,
.money-flow-dark .bootstrap-select > .dropdown-toggle.btn-secondary {
    background: var(--bg-input) !important;
    background-color: var(--bg-input) !important;
    border: 1px solid var(--border) !important;
    color: var(--select-text) !important;
    border-radius: 6px !important;
    min-height: 40px;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
    box-shadow: none !important;
}

.money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder,
.money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder:hover,
.money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder:focus,
.money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder:active {
    color: var(--select-text) !important;
}

.money-flow-dark .bootstrap-select > .dropdown-toggle:focus,
.money-flow-dark .bootstrap-select > .dropdown-toggle:hover {
    background: var(--bg-card-hover) !important;
    color: var(--select-text) !important;
    border-color: var(--border-focus) !important;
}

.money-flow-dark .bootstrap-select .filter-option,
.money-flow-dark .bootstrap-select .filter-option-inner,
.money-flow-dark .bootstrap-select .filter-option-inner-inner {
    color: var(--select-text) !important;
}

.money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder .filter-option-inner-inner {
    color: var(--select-text) !important;
}

.money-flow-dark .bootstrap-select .dropdown-toggle::after {
    border-top-color: var(--teal) !important;
}

.money-flow-dark .bootstrap-select.show > .dropdown-toggle {
    background: var(--bg-input) !important;
    border-color: var(--border-focus) !important;
}

.money-flow-dark .bootstrap-select .dropdown-menu li a {
    color: var(--select-text) !important;
}

.money-flow-dark .bootstrap-select .dropdown-menu li a:hover,
.money-flow-dark .bootstrap-select .dropdown-menu li a:focus,
.money-flow-dark .bootstrap-select .dropdown-menu li.active a {
    background: var(--teal-subtle) !important;
    color: var(--select-text) !important;
}

.money-flow-dark .bootstrap-select .dropdown-menu .bs-actionsbox .btn-group button {
    background: transparent !important;
    border: 1px solid var(--border) !important;
    color: var(--teal) !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    border-radius: 6px !important;
}

.money-flow-dark .bootstrap-select .dropdown-menu .bs-actionsbox .btn-group button:hover {
    background: var(--teal-subtle) !important;
}

/* قوائم منسدلة داخل الصفحة */
.money-flow-dark .dropdown-menu,
.money-flow-dark .bootstrap-select .dropdown-menu {
    background: var(--bg-card) !important;
    background-color: var(--bg-card) !important;
    border: 1px solid var(--border) !important;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5) !important;
}

.money-flow-dark .dropdown-item,
.money-flow-dark .bootstrap-select .dropdown-menu .dropdown-item {
    color: var(--select-text) !important;
}

.money-flow-dark .dropdown-item:hover,
.money-flow-dark .dropdown-item:focus,
.money-flow-dark .bootstrap-select .dropdown-menu .dropdown-item:hover,
.money-flow-dark .bootstrap-select .dropdown-menu .dropdown-item:focus {
    background: var(--teal-subtle) !important;
    color: var(--select-text) !important;
}

.money-flow-dark .dropdown-item.active,
.money-flow-dark .dropdown-item:active {
    background: var(--teal) !important;
    color: #0C1829 !important;
}

.money-flow-dark .bs-searchbox .form-control {
    background: var(--bg-input) !important;
    border-color: var(--border) !important;
    color: var(--select-text) !important;
}

.money-flow-dark .action-class,
.money-flow-dark th.action-class {
    background: var(--teal) !important;
    color: #0C1829 !important;
}

.money-flow-dark .border-green.bg-green,
.money-flow-dark .bg-green {
    background: var(--teal) !important;
    color: #0C1829 !important;
}

.money-flow-dark .kt-portlet__foot {
    background: var(--bg-card) !important;
    border-top: 1px solid var(--border) !important;
}

.money-flow-dark .head-title.text-primary {
    color: var(--teal) !important;
}

.money-flow-dark .input-group-text {
    background: var(--bg-card-hover) !important;
    border-color: var(--border) !important;
    color: var(--teal) !important;
}

.money-flow-dark th:not(.bank-max-width),
.money-flow-dark td:not(.bank-max-width) {
    text-wrap: nowrap !important;
}

.money-flow-dark .bank-max-width {
    max-width: 200px !important;
}

.money-flow-dark input[type="checkbox"] {
    cursor: pointer;
}

.money-flow-dark button[type="submit"],
.money-flow-dark button[type="button"] {
    font-size: 1rem !important;
}

/*
 * Shell + عناصر تُرفَق لـ body (bootstrap-select / datepicker / select2 / modals)
 * Scoped بـ body:has(.money-flow-dark)
 */
body:has(.money-flow-dark) #kt_content.kt-content {
    background: #0c1829 !important;
}

body:has(.money-flow-dark) #kt_subheader.kt-subheader {
    background: linear-gradient(90deg, #112240 0%, #0c1829 100%) !important;
    border-bottom: 1px solid #1490a833 !important;
    box-shadow: none !important;
}

/* Header (kt_header) follows same dark shell */
body:has(.money-flow-dark) #kt_header.kt-header {
    background: linear-gradient(90deg, #112240 0%, #0c1829 100%) !important;
    border-bottom: 1px solid #1490a833 !important;
    box-shadow: none !important;
}

body:has(.money-flow-dark) #kt_header .kt-header__brand,
body:has(.money-flow-dark) #kt_header .kt-header__topbar {
    background: transparent !important;
}

body:has(.money-flow-dark) #kt_header_menu_wrapper,
body:has(.money-flow-dark) #kt_header_menu.kt-header-menu {
    background: transparent !important;
}

body:has(.money-flow-dark) #kt_header_menu .kt-menu__link-text,
body:has(.money-flow-dark) #kt_header .kt-header__topbar .kt-header__topbar-icon i,
body:has(.money-flow-dark) #kt_header .kt-header__topbar .kt-header__topbar-username,
body:has(.money-flow-dark) #kt_header .kt-header__topbar .kt-header__topbar-welcome {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) #kt_header_menu .kt-menu__item--active > .kt-menu__link .kt-menu__link-text,
body:has(.money-flow-dark) #kt_header_menu .kt-menu__link:hover .kt-menu__link-text {
    color: #00b4c8 !important;
}

/* Header dropdowns / classic submenus */
body:has(.money-flow-dark) .kt-menu__submenu.kt-menu__submenu--classic.kt-menu__submenu--left,
body:has(.money-flow-dark) .dropdown-menu.dropdown-menu-fit.dropdown-menu-right.dropdown-menu-anim.dropdown-menu-xl.show {
    background: #112240 !important;
    border: 1px solid #1490a833 !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45) !important;
}

body:has(.money-flow-dark) .kt-menu__submenu.kt-menu__submenu--classic.kt-menu__submenu--left .kt-menu__link-text,
body:has(.money-flow-dark) .dropdown-menu.dropdown-menu-fit.dropdown-menu-right.dropdown-menu-anim.dropdown-menu-xl.show .dropdown-item {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .kt-menu__submenu.kt-menu__submenu--classic.kt-menu__submenu--left .kt-menu__link:hover,
body:has(.money-flow-dark) .kt-menu__submenu.kt-menu__submenu--classic.kt-menu__submenu--left .kt-menu__item--hover > .kt-menu__link,
body:has(.money-flow-dark) .dropdown-menu.dropdown-menu-fit.dropdown-menu-right.dropdown-menu-anim.dropdown-menu-xl.show .dropdown-item:hover {
    background: rgba(0, 180, 200, 0.18) !important;
}

body:has(.money-flow-dark) .kt-user-card {
    background: linear-gradient(135deg, #112240 0%, #0c1829 100%) !important;
    border-bottom: 1px solid #1490a833 !important;
}

body:has(.money-flow-dark) .kt-user-card .kt-user-card__name,
body:has(.money-flow-dark) .kt-user-card .kt-user-card__email {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .kt-grid-nav__item {
    background: #112240 !important;
    border: 1px solid #1490a833 !important;
}

body:has(.money-flow-dark) .kt-grid-nav__item .kt-grid-nav__icon,
body:has(.money-flow-dark) .kt-grid-nav__item .kt-grid-nav__title,
body:has(.money-flow-dark) .kt-grid-nav__item .kt-grid-nav__desc {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .kt-grid-nav__item:hover {
    background: rgba(0, 180, 200, 0.18) !important;
}

body:has(.money-flow-dark) #kt_header_mobile.kt-header-mobile {
    background: #112240 !important;
    border-bottom: 1px solid #1490a833 !important;
}

body:has(.money-flow-dark) #kt_header_mobile .kt-header-mobile__toolbar-toggler span,
body:has(.money-flow-dark) #kt_header_mobile .kt-header-mobile__toolbar-toggler span {
    background: #e2e8f0 !important;
}

body:has(.money-flow-dark) #kt_header_mobile .kt-header-mobile__toolbar-topbar-toggler i {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) #kt_subheader .kt-subheader__title {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) #kt_subheader .kt-container {
    background: transparent !important;
}

body:has(.money-flow-dark) #kt_content > .kt-container {
    background: transparent !important;
}

body:has(.money-flow-dark) #kt_body#kt_body {
    background-color: #0c1829 !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu,
body:has(.money-flow-dark) .bootstrap-select.bs-container .dropdown-menu {
    background-color: #112240 !important;
    background: #112240 !important;
    border: 1px solid #1490a833 !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45) !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu .inner {
    background: #112240 !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu li a {
    color: #ffffff !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu li a:hover,
body:has(.money-flow-dark) .bootstrap-select .dropdown-menu li a:focus,
body:has(.money-flow-dark) .bootstrap-select .dropdown-menu li.active a {
    background: rgba(0, 180, 200, 0.22) !important;
    color: #ffffff !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu li.disabled a {
    color: #64748b !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu .notify {
    background: #0c1829 !important;
    border: 1px solid #1490a833 !important;
    color: #94a3b8 !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu .bs-actionsbox .btn-group button {
    background: transparent !important;
    border: 1px solid #1490a833 !important;
    color: #00b4c8 !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
    border-radius: 6px !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-menu .bs-actionsbox .btn-group button:hover {
    background: rgba(0, 180, 200, 0.15) !important;
}

body:has(.money-flow-dark) .datepicker,
body:has(.money-flow-dark) .datepicker.datepicker-dropdown,
body:has(.money-flow-dark) .datepicker.dropdown-menu {
    background: #112240 !important;
    border: 1px solid #1490a833 !important;
    color: #e2e8f0 !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45) !important;
    padding: 6px !important;
}

body:has(.money-flow-dark) .datepicker .datepicker-days,
body:has(.money-flow-dark) .datepicker .datepicker-months,
body:has(.money-flow-dark) .datepicker .datepicker-years,
body:has(.money-flow-dark) .datepicker > div {
    background: #112240 !important;
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .datepicker table.table-condensed,
body:has(.money-flow-dark) .datepicker table {
    background: #112240 !important;
    background-color: #112240 !important;
}

body:has(.money-flow-dark) .datepicker table thead tr,
body:has(.money-flow-dark) .datepicker table thead tr th,
body:has(.money-flow-dark) .datepicker table thead tr td {
    background: #112240 !important;
    background-color: #112240 !important;
    color: #e2e8f0 !important;
    border-color: transparent !important;
}

body:has(.money-flow-dark) .datepicker table tbody {
    background: #112240 !important;
}

body:has(.money-flow-dark) .datepicker-dropdown:after {
    border-bottom-color: #112240 !important;
}

body:has(.money-flow-dark) .datepicker-dropdown:before {
    border-bottom-color: rgba(0, 0, 0, 0.25) !important;
}

body:has(.money-flow-dark) .datepicker-dropdown.datepicker-orient-top:after {
    border-top-color: #112240 !important;
}

body:has(.money-flow-dark) .datepicker-dropdown.datepicker-orient-top:before {
    border-top-color: rgba(0, 0, 0, 0.25) !important;
}

body:has(.money-flow-dark) .datepicker table tr th,
body:has(.money-flow-dark) .datepicker table tr td {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .datepicker table tr th.dow {
    color: #94a3b8 !important;
}

body:has(.money-flow-dark) .datepicker table tr td.old,
body:has(.money-flow-dark) .datepicker table tr td.new {
    color: #64748b !important;
}

body:has(.money-flow-dark) .datepicker .datepicker-switch,
body:has(.money-flow-dark) .datepicker .prev,
body:has(.money-flow-dark) .datepicker .next,
body:has(.money-flow-dark) .datepicker tfoot tr th {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .datepicker .datepicker-switch:hover,
body:has(.money-flow-dark) .datepicker .prev:hover,
body:has(.money-flow-dark) .datepicker .next:hover,
body:has(.money-flow-dark) .datepicker tfoot tr th:hover {
    background: rgba(0, 180, 200, 0.2) !important;
}

body:has(.money-flow-dark) .datepicker table tr td.day:hover,
body:has(.money-flow-dark) .datepicker table tr td.day.focused {
    background: rgba(0, 180, 200, 0.28) !important;
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .datepicker table tr td.disabled,
body:has(.money-flow-dark) .datepicker table tr td.disabled:hover {
    color: #475569 !important;
}

body:has(.money-flow-dark) .datepicker table tr td.today {
    background: rgba(201, 168, 76, 0.2) !important;
    color: #fbbf24 !important;
    border-color: #c9a84c !important;
}

body:has(.money-flow-dark) .datepicker table tr td.active,
body:has(.money-flow-dark) .datepicker table tr td.active:hover,
body:has(.money-flow-dark) .datepicker table tr td span.active,
body:has(.money-flow-dark) .datepicker table tr td span.active:hover {
    background: #00b4c8 !important;
    background-image: none !important;
    border-color: #00b4c8 !important;
    color: #0c1829 !important;
    text-shadow: none !important;
}

body:has(.money-flow-dark) .select2-dropdown {
    background: #112240 !important;
    border: 1px solid #1490a833 !important;
    color: #ffffff !important;
}

body:has(.money-flow-dark) .select2-container--default .select2-results__option {
    background-color: #112240;
    color: #ffffff;
}

body:has(.money-flow-dark) .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background: #00b4c8 !important;
    color: #0c1829 !important;
}

body:has(.money-flow-dark) .select2-search--dropdown .select2-search__field {
    background: #0c1829 !important;
    border: 1px solid #1490a833 !important;
    color: #ffffff !important;
}

body:has(.money-flow-dark) .select2-container--default .select2-selection--single {
    background: #0c1829 !important;
    border: 1px solid #1490a833 !important;
    color: #ffffff !important;
}

body:has(.money-flow-dark) .select2-container--default .select2-selection__rendered {
    color: #ffffff !important;
}

body:has(.money-flow-dark) .modal-content {
    background: #112240 !important;
    color: #e2e8f0 !important;
    border: 1px solid rgba(0, 180, 200, 0.28) !important;
}

body:has(.money-flow-dark) .modal-header {
    border-bottom: 1px solid rgba(20, 144, 168, 0.22) !important;
    background: #162d54 !important;
}

body:has(.money-flow-dark) .modal-title {
    color: #00b4c8 !important;
}

body:has(.money-flow-dark) .modal-body label {
    color: #94a3b8 !important;
}

body:has(.money-flow-dark) .modal-body .form-control,
body:has(.money-flow-dark) .modal-footer .form-control {
    background: #0c1829 !important;
    color: #e2e8f0 !important;
    border-color: rgba(20, 144, 168, 0.25) !important;
}

body:has(.money-flow-dark) .modal-body select,
body:has(.money-flow-dark) .modal-body select.form-control,
body:has(.money-flow-dark) .modal-footer select {
    color: #ffffff !important;
}

body:has(.money-flow-dark) .modal-footer {
    border-top-color: rgba(20, 144, 168, 0.22) !important;
    background: #112240 !important;
}

/*
 * Metronic style.bundle: .bootstrap-select > .dropdown-toggle.btn-light { color: #595d6e }
 * و .dropdown-menu.inner > li > a .text { color: #595d6e } — تفوق أعلى + webkit fill
 */
#kt_content .money-flow-dark select,
#kt_content .money-flow-dark select.form-control,
#kt_content .money-flow-dark .custom-select {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-light,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-secondary,
#kt_content .money-flow-dark .bootstrap-select.show > .dropdown-toggle.btn-light,
#kt_content .money-flow-dark .bootstrap-select.show > .dropdown-toggle.btn-secondary,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-light:focus,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-light.active,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-secondary:focus,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.btn-secondary.active {
    background-color: #0c1829 !important;
    background: #0c1829 !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder.btn-light,
#kt_content .money-flow-dark .bootstrap-select > .dropdown-toggle.bs-placeholder.btn-secondary {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

#kt_content .money-flow-dark .bootstrap-select .dropdown-toggle .filter-option,
#kt_content .money-flow-dark .bootstrap-select .dropdown-toggle .filter-option-inner,
#kt_content .money-flow-dark .bootstrap-select .dropdown-toggle .filter-option-inner-inner {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

#kt_content .money-flow-dark .bootstrap-select .dropdown-menu.inner > li > a,
#kt_content .money-flow-dark .bootstrap-select .dropdown-menu.inner > li > a .text {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

body:has(.money-flow-dark) .bootstrap-select > .dropdown-toggle.btn-light,
body:has(.money-flow-dark) .bootstrap-select > .dropdown-toggle.btn-secondary,
body:has(.money-flow-dark) .bootstrap-select.show > .dropdown-toggle.btn-light,
body:has(.money-flow-dark) .bootstrap-select.show > .dropdown-toggle.btn-secondary {
    background-color: #0c1829 !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

body:has(.money-flow-dark) .bootstrap-select > .dropdown-toggle.bs-placeholder.btn-light,
body:has(.money-flow-dark) .bootstrap-select > .dropdown-toggle.bs-placeholder.btn-secondary {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

body:has(.money-flow-dark) .bootstrap-select .dropdown-toggle .filter-option-inner-inner,
body:has(.money-flow-dark) .bootstrap-select .dropdown-menu.inner > li > a,
body:has(.money-flow-dark) .bootstrap-select .dropdown-menu.inner > li > a .text {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

#kt_content .money-flow-dark .select2-container--default .select2-selection__rendered,
body:has(.money-flow-dark) .select2-container--default .select2-selection__rendered {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

/* ── Vue Money Received (Teleport / modals خارج الـ scoped) ── */
.money-received-hidden-bootstrap-modals {
    position: fixed;
    left: 0;
    bottom: 0;
    width: 0;
    height: 0;
    overflow: visible;
    pointer-events: none;
}

.money-received-hidden-bootstrap-modals .kt-portlet__head-actions > a {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

body:has(.money-flow-dark) .mr-modal-backdrop {
    background: rgba(6, 12, 22, 0.88) !important;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

body:has(.money-flow-dark) .mr-modal {
    background: #112240 !important;
    color: #e2e8f0 !important;
    border: 1px solid rgba(0, 180, 200, 0.28) !important;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.55) !important;
}

body:has(.money-flow-dark) .mr-modal-header,
body:has(.money-flow-dark) .mr-modal-footer {
    border-color: rgba(20, 144, 168, 0.2) !important;
}

body:has(.money-flow-dark) .mr-modal-body,
body:has(.money-flow-dark) .mr-modal-text,
body:has(.money-flow-dark) .mr-modal-list {
    color: #e2e8f0 !important;
}

body:has(.money-flow-dark) .mr-modal-title {
    color: #00b4c8 !important;
}

body:has(.money-flow-dark) .modal[id^="send-to-under-collection-modal"] .modal-content {
    background: #112240 !important;
    color: #e2e8f0 !important;
    border: 1px solid rgba(0, 180, 200, 0.28) !important;
}

body:has(.money-flow-dark) .modal[id^="send-to-under-collection-modal"] .modal-header {
    border-bottom-color: rgba(20, 144, 168, 0.22) !important;
}

body:has(.money-flow-dark) .modal[id^="send-to-under-collection-modal"] .modal-title {
    color: #00b4c8 !important;
}

body:has(.money-flow-dark) .modal[id^="send-to-under-collection-modal"] .modal-body label {
    color: #94a3b8 !important;
}

body:has(.money-flow-dark) .modal[id^="send-to-under-collection-modal"] .form-control {
    background: #0c1829 !important;
    color: #e2e8f0 !important;
    border-color: rgba(20, 144, 168, 0.25) !important;
}

body:has(.money-flow-dark) .modal-backdrop.show {
    opacity: 0.65;
}

#money-received-vue-app .mr-page {
    color-scheme: dark;
}

#money-received-vue-app .mr-page input[type="date"],
#money-received-vue-app .mr-page input[type="datetime-local"],
body:has(.money-flow-dark) .mr-modal input[type="date"],
body:has(.money-flow-dark) .mr-modal input[type="datetime-local"] {
    min-height: 40px !important;
    box-sizing: border-box !important;
    line-height: 1.35 !important;
    background-color: #0c1829 !important;
    color: #ffffff !important;
    border: 1px solid rgba(20, 144, 168, 0.28) !important;
    border-radius: 6px !important;
}

#money-received-vue-app .mr-page input[type="date"]::-webkit-calendar-picker-indicator,
body:has(.money-flow-dark) .mr-modal input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.88) brightness(1.12) !important;
    opacity: 0.95 !important;
    cursor: pointer !important;
}

#money-received-vue-app .mr-page select.mr-input,
#money-received-vue-app .mr-page select.mr-input-wide,
body:has(.money-flow-dark) .mr-modal select.mr-input,
body:has(.money-flow-dark) .mr-modal select.mr-input-wide {
    appearance: none !important;
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    min-height: 40px !important;
    box-sizing: border-box !important;
    background-color: #0c1829 !important;
    color: #ffffff !important;
    border: 1px solid rgba(20, 144, 168, 0.28) !important;
    border-radius: 6px !important;
    padding: 8px 36px 8px 12px !important;
    font-size: 0.875rem !important;
    line-height: 1.35 !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%2300b4c8' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 10px center !important;
    background-size: 12px !important;
    cursor: pointer !important;
}

#money-received-vue-app .mr-page select.mr-input option,
#money-received-vue-app .mr-page select.mr-input-wide option,
body:has(.money-flow-dark) .mr-modal select.mr-input option {
    background: #112240 !important;
    color: #ffffff !important;
}

#money-received-vue-app .mr-page .mr-input[type="text"]:not([disabled]),
body:has(.money-flow-dark) .mr-modal .mr-input[type="text"]:not([disabled]) {
    background-color: #0c1829 !important;
    color: #ffffff !important;
    border-color: rgba(20, 144, 168, 0.28) !important;
}

/* DataTables footer (e.g. "Showing 1 to 50 of 50 entries" on uploading tables) */
.money-flow-dark .dataTables_wrapper .dataTables_info,
.money-flow-dark .dataTables_wrapper .dataTables_length,
.money-flow-dark .dataTables_wrapper .dataTables_length label,
.money-flow-dark .dataTables_wrapper .dataTables_filter,
.money-flow-dark .dataTables_wrapper .dataTables_filter label {
    color: #ffffff !important;
}

.money-flow-dark .dataTables_wrapper .dataTables_length select,
.money-flow-dark .dataTables_wrapper .dataTables_filter input {
    color: #ffffff !important;
    background-color: var(--bg-input, #0c1829) !important;
    border: 1px solid var(--border, rgba(20, 144, 168, 0.28)) !important;
}

.money-flow-dark .dataTables_wrapper .dataTables_processing,
.money-flow-dark .dataTables_wrapper .dataTables_empty {
    color: #ffffff !important;
}

/* Dashboard layout footer (outside .money-flow-dark wrapper) */
html.money-flow-dark-page #kt_footer.money-flow-footer,
body:has(.money-flow-dark) #kt_footer.money-flow-footer {
    background-color: #112240 !important;
    background-image: none !important;
    border-top: 1px solid rgba(20, 144, 168, 0.2);
    box-shadow: 0 -12px 40px rgba(0, 0, 0, 0.28);
    position: relative;
    overflow: hidden;
}

html.money-flow-dark-page #kt_footer .money-flow-footer__accent,
body:has(.money-flow-dark) #kt_footer .money-flow-footer__accent {
    height: 2px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(20, 144, 168, 0.15) 15%,
        #00b4c8 40%,
        #c9a84c 50%,
        #00b4c8 60%,
        rgba(20, 144, 168, 0.15) 85%,
        transparent 100%
    );
    opacity: 0.7;
}

html.money-flow-dark-page #kt_footer .kt-footer__bottom,
body:has(.money-flow-dark) #kt_footer .kt-footer__bottom {
    background: transparent !important;
    padding: 1.35rem 0 1.5rem;
}

html.money-flow-dark-page #kt_footer .kt-footer__wrapper,
body:has(.money-flow-dark) #kt_footer .kt-footer__wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

html.money-flow-dark-page #kt_footer .cashvero-footer__copyright,
body:has(.money-flow-dark) #kt_footer .cashvero-footer__copyright {
    width: 100%;
    text-align: left !important;
    color: #64748b !important;
    font-size: 0.8125rem;
    letter-spacing: 0.02em;
}

/* Cash dashboard currency tabs — prevent white flash on tab switch */
.money-flow-dark .tab-content,
body:has(.money-flow-dark) .tab-content {
    background-color: #112240 !important;
    margin-top: 0 !important;
}

.money-flow-dark .tab-pane,
body:has(.money-flow-dark) .tab-pane {
    background-color: #112240 !important;
}

.money-flow-dark .chartdiv_two_lines,
.money-flow-dark .chartDiv,
.money-flow-dark [id*="chartdiv"],
body:has(.money-flow-dark) .chartdiv_two_lines,
body:has(.money-flow-dark) .chartDiv,
body:has(.money-flow-dark) [id*="chartdiv"] {
    background-color: #112240 !important;
}

/* Disable white wave animation on iconbox cards (causes white blob on tab switch) */
.money-flow-dark .kt-iconbox--animate-slower::before,
.money-flow-dark .kt-iconbox--animate-slower::after,
.money-flow-dark .kt-iconbox--animate::before,
.money-flow-dark .kt-iconbox--animate::after,
body:has(.money-flow-dark) .kt-iconbox--animate-slower::before,
body:has(.money-flow-dark) .kt-iconbox--animate-slower::after,
body:has(.money-flow-dark) .kt-iconbox--animate::before,
body:has(.money-flow-dark) .kt-iconbox--animate::after {
    content: none !important;
    display: none !important;
    animation: none !important;
}

.money-flow-dark .kt-iconbox--animate-slower,
.money-flow-dark .kt-iconbox--animate,
body:has(.money-flow-dark) .kt-iconbox--animate-slower,
body:has(.money-flow-dark) .kt-iconbox--animate {
    background-color: transparent !important;
    overflow: visible !important;
}

/* Loan schedule settlement page */
.money-flow-dark .header-tr,
body:has(.money-flow-dark) .header-tr {
    background-color: #074FA4 !important;
}

.money-flow-dark .header-tr th,
body:has(.money-flow-dark) .header-tr th {
    color: #ffffff !important;
    border-color: #334155 !important;
}

.money-flow-dark .table-for-currency tbody tr.parent-tr:hover td,
body:has(.money-flow-dark) .table-for-currency tbody tr.parent-tr:hover td {
    background-color: #1e3a5f !important;
}
</style>
