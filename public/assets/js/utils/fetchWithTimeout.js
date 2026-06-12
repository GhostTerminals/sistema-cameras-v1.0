(function () {
    'use strict';

    var DEFAULT_TIMEOUT = 30000;

    window.fetchWithTimeout = function (input, init, timeout) {
        init = init || {};

        var effectiveTimeout = timeout || window.FETCH_TIMEOUT || DEFAULT_TIMEOUT;

        var controller = new AbortController();
        var timeoutId = setTimeout(function () {
            controller.abort();
        }, effectiveTimeout);

        var signals = [controller.signal];
        if (init.signal) {
            signals.push(init.signal);
        }

        var mergedInit = Object.assign({}, init, {
            signal: AbortSignal.any ? AbortSignal.any(signals) : controller.signal
        });

        return window.fetch.call(window, input, mergedInit).then(function (response) {
            clearTimeout(timeoutId);
            var csrfHeader = response.headers.get('X-CSRF-Token');
            if (csrfHeader && /^[a-f0-9]{64}$/i.test(csrfHeader)) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) meta.setAttribute('content', csrfHeader);
            }
            return response;
        }).catch(function (err) {
            clearTimeout(timeoutId);
            if (err.name === 'AbortError') {
                throw new Error('Requisicao excedeu o tempo limite de ' + (effectiveTimeout / 1000) + 's');
            }
            throw err;
        });
    };
})();
