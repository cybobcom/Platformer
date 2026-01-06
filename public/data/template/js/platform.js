
//
//
//

function CBLog(text) {
    alert( JSON.stringify(text) );
}

/**
 * CB Framework - JavaScript Edition
 * Konsistent mit PHP, Swift, Kotlin Versionen
 *
 * Funktionen:
 * - CBloadView()     - Lädt HTML-Views in Container (GET/POST, Vue-Support)
 * - CBapi()          - API-Calls mit JSON-Response
 * - CBlog()          - Logging (konsistent mit PHP CBLog)
 * - CBevents         - Event-System für Modul-Kommunikation
 *
 * Standard Events:
 * - 'list-refresh'   - Liste neu laden
 * - 'data-saved'     - Daten wurden gespeichert
 * - 'data-deleted'   - Daten wurden gelöscht
 * - 'modal-opened'   - Modal wurde geöffnet
 * - 'modal-closed'   - Modal wurde geschlossen
 * - 'error'          - Fehler ist aufgetreten
 * - 'success'        - Aktion erfolgreich
 */
(function(global) {

    const loadedResources = new Set();

    /**
     * Lädt HTML-View in Container
     * Unterstützt GET/POST, Vue-Cleanup, externe CSS/JS
     *
     * @param {string|HTMLElement} container - Container-Selector oder Element
     * @param {string} url - URL der zu ladenden View
     * @param {function} callback - Optional: Wird nach Laden aufgerufen
     * @param {object} options - Optional: { method, data, processResult, cleanupVue }
     *
     * Beispiele:
     * CBloadView('#container', '/views/address/editItem/?id=123');
     * CBloadView('#container', '/api/save/', null, { method: 'POST', data: {...} });
     */
    global.CBloadView = function(container, url, callback, options = {}) {
        const el = typeof container === 'string' ? document.querySelector(container) : container;
        if (!el) {
            CBlog('CBloadView: Container nicht gefunden', container);
            return;
        }

        const cleanupVue = options.cleanupVue !== false;
        const method = options.method || 'GET';
        const data = options.data || null;
        const processResult = options.processResult || null;

        // Vue-Cleanup
        if (cleanupVue && el.__vueApp) {
            el.__vueApp.unmount();
            el.__vueApp = null;
        }

        // Loader anzeigen
        el.innerHTML = '<div class="mx-auto p-2 text-center ajax_loader">Lade...</div>';

        // Fetch-Optionen
        const fetchOptions = {
            method: method,
            cache: 'no-cache'
        };

        // POST-Daten hinzufügen
        if (method === 'POST' && data) {
            if (data instanceof FormData) {
                fetchOptions.body = data;
            } else {
                fetchOptions.headers = { 'Content-Type': 'application/json' };
                fetchOptions.body = JSON.stringify(data);
            }
        }

        // Fetch ausführen
        fetch(url, fetchOptions)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);

                // ✅ FIX: Immer als Text laden, dann prüfen ob JSON
                return res.text();
            })
            .then(responseText => {

                // Versuche zu erkennen ob JSON oder HTML
                let response = responseText;
                let isJSON = false;

                try {
                    // Versuche JSON zu parsen
                    response = JSON.parse(responseText);
                    isJSON = true;
                } catch(e) {
                    // Ist kein JSON, bleibt Text/HTML
                    response = responseText;
                }

                // JSON-Response mit processResult verarbeiten
                if (isJSON && processResult) {
                    processResult(response);
                    return;
                }

                // HTML-Response verarbeiten
                const html = typeof response === 'string' ? response : response.html || '';
                el.innerHTML = html;

                const head = document.head;

                // CSS laden (nur einmal)
                el.querySelectorAll('link[rel="stylesheet"]').forEach(link => {
                    if (!loadedResources.has(link.href)) {
                        const newLink = document.createElement('link');
                        newLink.rel = 'stylesheet';
                        newLink.href = link.href;
                        head.appendChild(newLink);
                        loadedResources.add(link.href);
                    }
                    link.remove();
                });

                // Externe Scripts laden (nur einmal)
                el.querySelectorAll('script[src]').forEach(script => {
                    if (!loadedResources.has(script.src)) {
                        const newScript = document.createElement('script');
                        newScript.src = script.src;
                        newScript.async = false;
                        head.appendChild(newScript);
                        loadedResources.add(script.src);
                    }
                    script.remove();
                });

                // Inline-Scripts ausführen
                el.querySelectorAll('script:not([src])').forEach(script => {
                    const newScript = document.createElement('script');
                    newScript.textContent = script.textContent;
                    document.body.appendChild(newScript);
                    document.body.removeChild(newScript);
                });

                // Callback aufrufen
                if (typeof callback === 'function') callback(response);
            })
            .catch(err => {
                CBlog('Fehler beim Laden von ' + url, err);
                el.innerHTML = '<p class="text-danger text-center">Fehler beim Laden.</p>';
            });
    };

    /**
     * API-Call mit JSON-Response
     *
     * @param {string} url - API-Endpoint URL
     * @param {object} options - { method, data, onSuccess, onError }
     * @returns {Promise} Promise mit Response-Daten
     *
     * Beispiele:
     * CBapi('/api/getData/', { method: 'GET', onSuccess: (result) => {...} });
     * CBapi('/api/save/', { method: 'POST', data: {...}, onSuccess: (result) => {...} });
     */
    global.CBapi = function(url, options = {}) {
        const method = options.method || 'GET';
        const data = options.data || null;
        const onSuccess = options.onSuccess || null;
        const onError = options.onError || null;

        const fetchOptions = {
            method: method,
            cache: 'no-cache'
        };

        // POST-Daten hinzufügen
        if (method === 'POST' && data) {
            if (data instanceof FormData) {
                fetchOptions.body = data;
            } else {
                fetchOptions.headers = { 'Content-Type': 'application/json' };
                fetchOptions.body = JSON.stringify(data);
            }
        }

        return fetch(url, fetchOptions)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(result => {
                if (onSuccess) onSuccess(result);
                return result;
            })
            .catch(err => {
                CBlog('API-Fehler: ' + url, err);
                if (onError) onError(err);
                throw err;
            });
    };

    /**
     * Logging (konsistent mit CBLog in PHP)
     *
     * @param {*} data - Optional: Zusätzliche Daten
     * @param {string} message - Log-Nachricht
     *
     * Beispiele:
     * CBlog('App gestartet');
     * CBlog('User data', { name: 'Bob', id: 123 });
     */
    global.CBlog = function(data, message) {
        if (typeof data !== 'undefined') {
            console.log('[CB]', message, data);
        } else {
            console.log('[CB]', message);
        }
    };

    //
    global.CBLog = function(data, message) {
        CBlog(data,message);
    }

        /**
     * Event-System für Modul-Kommunikation
     *
     * Beispiele:
     * CBevents.on('list-refresh', function() { console.log('refresh'); });
     * CBevents.emit('list-refresh');
     * CBevents.emit('list-refresh', { id: 123 });
     * CBevents.off('list-refresh');
     */
    global.CBevents = {
        events: {},

        /**
         * Event-Listener registrieren
         * @param {string} event - Event-Name
         * @param {function} callback - Callback-Funktion
         */
        on: function(event, callback) {
            if (!this.events[event]) this.events[event] = [];
            this.events[event].push(callback);
        },

        /**
         * Event feuern
         * @param {string} event - Event-Name
         * @param {*} data - Optional: Daten mitgeben
         */
        emit: function(event, data) {
            if (!this.events[event]) return;
            this.events[event].forEach(cb => cb(data));
        },

        /**
         * Event-Listener entfernen
         * @param {string} event - Event-Name
         * @param {function} callback - Optional: Spezifischer Callback
         */
        off: function(event, callback) {
            if (!this.events[event]) return;
            if (callback) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            } else {
                delete this.events[event];
            }
        }
    };

})(window);