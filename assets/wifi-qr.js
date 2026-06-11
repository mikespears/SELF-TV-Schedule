(function () {
    if (typeof qrcode !== 'function') {
        return;
    }

    document.querySelectorAll('[data-wifi-qr]').forEach(function (el) {
        var payload = el.getAttribute('data-wifi-qr');
        if (!payload) {
            return;
        }

        var qr = qrcode(0, 'M');
        qr.addData(payload);
        qr.make();

        var cellSize = el.closest('.wifi-card--override') ? 10 : 6;
        el.innerHTML = qr.createSvgTag(cellSize, 2);

        var svg = el.querySelector('svg');
        if (svg) {
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', 'WiFi QR code');
        }
    });
})();
