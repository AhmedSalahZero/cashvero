import { getCurrentInstance, onBeforeUnmount, ref } from 'vue';

/**
 * createPoller
 * ==================================================================
 * A background polling loop that behaves itself.
 *
 * Built for the Messages inbox, where polling isn't a fallback — it IS
 * the live-update mechanism, because no broadcasting driver is
 * configured (BROADCAST_DRIVER=log, so window.Echo never exists). That
 * makes every weakness of a bare `setInterval(fetch, 5000)` a real bug
 * users hit, not a theoretical one:
 *
 *  - setTimeout chain, not setInterval. The next run is scheduled only
 *    AFTER the current response lands, so a slow request can't build up
 *    a queue of overlapping calls that then all fire at once.
 *
 *  - One in-flight request, aborted via AbortController on stop().
 *
 *  - A monotonic sequence number, so a response that has already been
 *    superseded is dropped instead of applied. This is the actual fix
 *    for "delete a message and it comes back": that was an in-flight
 *    read from BEFORE the delete landing after it and overwriting the
 *    result. It used to be worked around in the merge step by deciding
 *    a local deletion always wins; ordering is the right place to fix
 *    it, so callers no longer need that rule.
 *
 *  - Adaptive interval. Idles out towards maxInterval while nothing is
 *    changing and snaps back to `interval` the moment something does,
 *    or when poke() reports user activity. An open tab left alone all
 *    afternoon costs a fraction of what a fixed 5s loop costs.
 *
 *  - Pauses completely while the tab is hidden or the browser is
 *    offline, and fires immediately when either comes back — so you
 *    return to an up-to-date screen without having paid for polling
 *    nobody was looking at.
 *
 *  - Exponential backoff on errors plus a consecutive-failure count,
 *    exposed as `failing` so the UI can say "reconnecting" instead of
 *    silently showing stale data.
 *
 * Usage:
 *
 *   const poller = createPoller({
 *       interval: 3000,
 *       maxInterval: 15000,
 *       request: (signal) => window.axios.get(url, { signal }).then(r => r.data),
 *       onResult: (data) => applyIt(data),   // MUST return true if anything changed
 *   });
 *   poller.start();
 *
 * onResult's return value drives the adaptive interval, so returning
 * `true` unconditionally keeps the loop permanently at its base rate.
 */
export function createPoller({
    request,
    onResult,
    onError = null,
    interval = 5000,
    maxInterval = 30000,
    idleGrowth = 1.5,
    errorBackoff = [5000, 10000, 20000, 60000],
}) {
    /** True once requests have failed enough times to be worth telling the user about. */
    const failing = ref(false);
    const running = ref(false);

    let timer = null;
    let controller = null;
    let seq = 0;
    let appliedSeq = 0;
    let currentInterval = interval;
    let errorCount = 0;

    function clearTimer() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function schedule(delay) {
        clearTimer();
        if (!running.value) return;
        timer = setTimeout(run, delay);
    }

    /** Nobody is looking, or there's no network: don't spend the request. */
    function suspended() {
        if (typeof document !== 'undefined' && document.hidden) return true;
        if (typeof navigator !== 'undefined' && navigator.onLine === false) return true;
        return false;
    }

    /**
     * Our own abort, not a real failure — must not trip the backoff.
     * Axios rejects cancelled requests with ERR_CANCELED; a raw fetch
     * throws a DOMException named AbortError.
     */
    function isAborted(error) {
        return error?.code === 'ERR_CANCELED'
            || error?.name === 'AbortError'
            || error?.name === 'CanceledError';
    }

    async function run() {
        clearTimer();
        if (!running.value) return;

        // No timer is left pending here on purpose: the visibility and
        // online listeners below are what wake the loop back up.
        if (suspended()) return;

        controller = new AbortController();
        const mySeq = ++seq;

        try {
            const result = await request(controller.signal);

            // Superseded while in flight (stop(), or a newer run already
            // applied). Applying this now would move state backwards.
            if (!running.value || mySeq < appliedSeq) return;
            appliedSeq = mySeq;

            errorCount = 0;
            failing.value = false;

            const changed = onResult(result) === true;
            currentInterval = changed
                ? interval
                : Math.min(Math.round(currentInterval * idleGrowth), maxInterval);

            schedule(currentInterval);
        } catch (error) {
            if (isAborted(error) || !running.value) return;

            errorCount += 1;
            // One dropped request is normal on a flaky connection and not
            // worth alarming anyone about; a second means something's up.
            failing.value = errorCount >= 2;
            onError?.(error, errorCount);

            schedule(errorBackoff[Math.min(errorCount - 1, errorBackoff.length - 1)]);
        }
    }

    function onVisibilityChange() {
        if (!running.value) return;
        if (document.hidden) {
            clearTimer();
            return;
        }
        poke();
    }

    function onOnline() {
        if (running.value) poke();
    }

    function onOffline() {
        clearTimer();
    }

    function addListeners() {
        if (typeof window === 'undefined') return;
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('focus', onOnline);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
    }

    function removeListeners() {
        if (typeof window === 'undefined') return;
        document.removeEventListener('visibilitychange', onVisibilityChange);
        window.removeEventListener('focus', onOnline);
        window.removeEventListener('online', onOnline);
        window.removeEventListener('offline', onOffline);
    }

    /**
     * Begin polling. The first run is one interval away, not immediate —
     * callers normally have just loaded the same data themselves.
     */
    function start() {
        if (running.value) return;
        running.value = true;
        errorCount = 0;
        failing.value = false;
        currentInterval = interval;
        addListeners();
        schedule(currentInterval);
    }

    function stop() {
        if (!running.value && !timer && !controller) return;
        running.value = false;
        failing.value = false;
        clearTimer();
        removeListeners();
        if (controller) {
            controller.abort();
            controller = null;
        }
        // Nothing still in flight may be applied after a stop — e.g. the
        // response for a conversation the user has already closed.
        appliedSeq = seq + 1;
    }

    /**
     * "Something happened / the user is active": reset the idle interval
     * back to its base rate, and by default check right now.
     */
    function poke(immediate = true) {
        currentInterval = interval;
        if (!running.value) return;
        schedule(immediate ? 0 : currentInterval);
    }

    // Safety net for the usual case of being created in setup(); callers
    // outside a component are responsible for their own stop().
    if (getCurrentInstance()) {
        onBeforeUnmount(stop);
    }

    return { start, stop, poke, failing, running };
}
