(function () {
    function bindRemove(container, selector, attr) {
        container.addEventListener('click', function (event) {
            var btn = event.target.closest(selector);
            if (!btn) {
                return;
            }
            var row = btn.closest('[' + attr + ']');
            if (row) {
                row.remove();
            }
        });
    }

    function bindSponsorScope(row) {
        var picks = row.querySelector('[data-sponsor-room-picks]');
        var radios = row.querySelectorAll('[data-sponsor-scope]');
        function syncScope() {
            var checked = row.querySelector('[data-sponsor-scope]:checked');
            if (picks) {
                picks.hidden = !checked || checked.value !== 'rooms';
            }
        }
        radios.forEach(function (radio) {
            radio.addEventListener('change', syncScope);
        });
        syncScope();
    }

    function renumberSponsorRows() {
        var sponsorRows = document.getElementById('sponsor-rows');
        if (!sponsorRows) {
            return;
        }
        sponsorRows.querySelectorAll('[data-sponsor-row]').forEach(function (row, idx) {
            row.querySelectorAll('[data-sponsor-scope]').forEach(function (radio) {
                radio.name = 'sponsor_scope[' + idx + ']';
            });
            row.querySelectorAll('.sponsor-room-picks__option input[type="checkbox"]').forEach(function (box) {
                box.name = 'sponsor_rooms[' + idx + '][]';
            });
        });
    }

    function appendFromTemplate(container, template, idxPlaceholder, idx) {
        if (!template || !container) {
            return null;
        }

        var wrap = document.createElement('div');
        wrap.innerHTML = template.innerHTML.replace(/__IDX__/g, String(idx)).trim();
        var row = wrap.firstElementChild;
        if (!row) {
            return null;
        }

        container.appendChild(row);
        return row;
    }

    var sponsorRows = document.getElementById('sponsor-rows');
    var sponsorTemplate = document.getElementById('sponsor-template');
    if (sponsorRows && sponsorTemplate) {
        bindRemove(sponsorRows, '[data-remove-sponsor]', 'data-sponsor-row');
        sponsorRows.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove-sponsor]')) {
                setTimeout(renumberSponsorRows, 0);
            }
        });
        sponsorRows.querySelectorAll('[data-sponsor-row]').forEach(bindSponsorScope);
        renumberSponsorRows();

        var addSponsor = document.getElementById('add-sponsor');
        if (addSponsor) {
            addSponsor.addEventListener('click', function () {
                var idx = sponsorRows.querySelectorAll('[data-sponsor-row]').length;
                var row = appendFromTemplate(sponsorRows, sponsorTemplate, '__IDX__', idx);
                if (row) {
                    bindSponsorScope(row);
                    renumberSponsorRows();
                }
            });
        }
    }

    function bindPretalxRoomSelect(row) {
        var select = row.querySelector('[data-room-pretalx-select]');
        if (!select) {
            return;
        }
        function syncFromPretalx() {
            var opt = select.options[select.selectedIndex];
            var idInput = row.querySelector('[data-room-id]');
            var labelInput = row.querySelector('[data-room-label]');
            var slugInput = row.querySelector('[data-room-slug]');
            if (!opt || opt.value === '') {
                if (idInput) {
                    idInput.value = '';
                }
                if (labelInput) {
                    labelInput.value = '';
                }
                return;
            }
            if (idInput) {
                idInput.value = opt.value;
            }
            if (labelInput) {
                labelInput.value = opt.dataset.label || '';
            }
            if (slugInput && !slugInput.value && opt.dataset.slug) {
                slugInput.value = opt.dataset.slug;
            }
        }
        select.addEventListener('change', syncFromPretalx);
        syncFromPretalx();
    }

    function bindRoomScope(row) {
        var picks = row.querySelector('[data-room-scope-picks]');
        var radios = row.querySelectorAll('[data-room-scope]');
        function syncScope() {
            var checked = row.querySelector('[data-room-scope]:checked');
            if (picks) {
                picks.hidden = !checked || checked.value !== 'rooms';
            }
        }
        radios.forEach(function (radio) {
            radio.addEventListener('change', syncScope);
        });
        syncScope();
    }

    function renumberMessageRows() {
        var messageRows = document.getElementById('message-rows');
        if (!messageRows) {
            return;
        }
        messageRows.querySelectorAll('[data-message-row]').forEach(function (row, idx) {
            row.querySelectorAll('[data-room-scope]').forEach(function (radio) {
                radio.name = 'message_scope[' + idx + ']';
            });
            row.querySelectorAll('.room-scope-picks__option input[type="checkbox"]').forEach(function (box) {
                box.name = 'message_rooms[' + idx + '][]';
            });
            var enabled = row.querySelector('input[name^="message_enabled"]');
            if (enabled) {
                enabled.name = 'message_enabled[' + idx + ']';
            }
        });
    }

    var messageRows = document.getElementById('message-rows');
    var messageTemplate = document.getElementById('message-template');
    if (messageRows && messageTemplate) {
        bindRemove(messageRows, '[data-remove-message]', 'data-message-row');
        messageRows.addEventListener('click', function (event) {
            if (event.target.closest('[data-remove-message]')) {
                setTimeout(renumberMessageRows, 0);
            }
        });
        messageRows.querySelectorAll('[data-message-row]').forEach(function (row) {
            bindRoomScope(row);
        });
        renumberMessageRows();

        var addMessage = document.getElementById('add-message');
        if (addMessage) {
            addMessage.addEventListener('click', function () {
                var idx = messageRows.querySelectorAll('[data-message-row]').length;
                var row = appendFromTemplate(messageRows, messageTemplate, '__IDX__', idx);
                if (row) {
                    bindRoomScope(row);
                    renumberMessageRows();
                }
            });
        }
    }

    var roomRows = document.getElementById('room-rows');
    var roomTemplate = document.getElementById('room-template');
    if (roomRows && roomTemplate) {
        bindRemove(roomRows, '[data-remove-room]', 'data-room-row');
        roomRows.querySelectorAll('[data-room-row]').forEach(bindPretalxRoomSelect);

        var addRoom = document.getElementById('add-room');
        if (addRoom) {
            addRoom.addEventListener('click', function () {
                var node = roomTemplate.content.cloneNode(true);
                roomRows.appendChild(node);
                var row = roomRows.lastElementChild;
                if (row) {
                    bindPretalxRoomSelect(row);
                }
            });
        }
    }
})();
