(function () {
    'use strict';

    var DEFAULT_TIMEOUT = 30000;

    var originalFetch = window.fetch;

    window.fetch = function (input, init) {
        init = init || {};

        var timeout = window.FETCH_TIMEOUT || DEFAULT_TIMEOUT;

        var controller = new AbortController();
        var timeoutId = setTimeout(function () {
            controller.abort();
        }, timeout);

        init.signal = controller.signal;

        return originalFetch.call(window, input, init).then(function (response) {
            clearTimeout(timeoutId);
            var csrfHeader = response.headers.get('X-CSRF-Token');
            if (csrfHeader) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', csrfHeader);
            }
            return response;
        })['catch'](function (err) {
            clearTimeout(timeoutId);
            if (err.name === 'AbortError') {
                throw new Error('Requisição excedeu o tempo limite de ' + (timeout / 1000) + 's');
            }
            throw err;
        });
    };
})();
