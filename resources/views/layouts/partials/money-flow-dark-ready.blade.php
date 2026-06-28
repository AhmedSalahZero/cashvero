<script>
(function () {
    function markMoneyFlowDarkReady() {
        var blocks = document.querySelectorAll('.money-flow-dark');

        if (!blocks.length) {
            return;
        }

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                blocks.forEach(function (el) {
                    el.classList.add('money-flow-dark--ready');
                });
            });
        });
    }

    function scheduleReady() {
        if (document.readyState === 'complete') {
            markMoneyFlowDarkReady();
            return;
        }

        window.addEventListener('load', markMoneyFlowDarkReady, { once: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleReady, { once: true });
    } else {
        scheduleReady();
    }
})();
</script>
