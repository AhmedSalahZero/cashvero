<script>
    function zavTogglePassword(inputId, openIconId, closedIconId) {
        const inp = document.getElementById(inputId);
        const open = document.getElementById(openIconId);
        const closed = document.getElementById(closedIconId);
        if (!inp || !open || !closed) return;

        if (inp.type === 'password') {
            inp.type = 'text';
            open.style.display = 'none';
            closed.style.display = 'block';
        } else {
            inp.type = 'password';
            open.style.display = 'block';
            closed.style.display = 'none';
        }
    }

    function zavToggleRemember(boxId, inputId) {
        const box = document.getElementById(boxId);
        const input = document.getElementById(inputId);
        if (!box || !input) return;

        box.classList.toggle('checked');
        input.checked = box.classList.contains('checked');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-zav-password-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                zavTogglePassword(
                    btn.dataset.targetInput,
                    btn.dataset.iconOpen,
                    btn.dataset.iconClosed
                );
            });
        });

        document.querySelectorAll('[data-zav-remember-toggle]').forEach(function (el) {
            el.addEventListener('click', function () {
                zavToggleRemember(el.dataset.rememberBox, el.dataset.rememberInput);
            });
        });
    });
</script>
