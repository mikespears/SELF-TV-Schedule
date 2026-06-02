(function () {
    const el = document.getElementById('clock');
    if (!el || el.dataset.frozen) {
        return;
    }

    const timeZone =
        document.body.dataset.timezone || 'America/New_York';

    function tick() {
        el.textContent = new Date().toLocaleString('en-US', {
            timeZone,
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
        });
    }

    tick();
    setInterval(tick, 1000);
})();
