{{-- Critical anti-FOUC: server sets html.money-flow-dark-page before first paint. --}}
<style>
html.money-flow-dark-page,
html.money-flow-dark-page body {
    background-color: #0c1829 !important;
    background-image: none !important;
}

html.money-flow-dark-page #kt_content,
html.money-flow-dark-page #kt_wrapper,
html.money-flow-dark-page .kt-grid__item--fluid,
html.money-flow-dark-page .kt-page {
    background-color: #0c1829 !important;
}

html.money-flow-dark-page .money-flow-dark:not(.money-flow-dark--ready) {
    visibility: hidden;
}

html.money-flow-dark-page .money-flow-dark.money-flow-dark--ready {
    visibility: visible;
}

html.money-flow-dark-page #loader_id {
    background-color: #0c1829;
}

html.money-flow-dark-page #kt_footer {
    background-color: #112240 !important;
    background-image: none !important;
}
</style>
