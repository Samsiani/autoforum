/**
 * AutoForum — News Ticker
 * Hydrates #af-ticker from AF_DATA.ticker (passed by class-assets.php).
 *
 * Data shape:
 *   AF_DATA.ticker = {
 *     enabled:      bool,
 *     label:        string,
 *     speed:        number,   // seconds for one full scroll cycle
 *     pauseOnHover: bool,
 *     items:        [ { text: string, icon: string } ]   // icon = 'fa-fire'
 *   }
 */

const Ticker = (() => {
    /** @type {HTMLElement|null} */
    let ticker    = null;
    let track     = null;
    let pauseBtn  = null;
    let isPaused  = false;

    // ─── build item HTML ───────────────────────────────────────────────
    function _itemHtml(item) {
        const iconClass = item.icon ? `fa-solid ${item.icon}` : 'fa-solid fa-circle-dot';
        const text      = item.text || '';
        return `<span class="af-ticker-item"><i class="${iconClass}" aria-hidden="true"></i>${_esc(text)}</span>`;
    }

    function _esc(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ─── apply speed via CSS custom property ──────────────────────────
    function _applySpeed(seconds) {
        // Set on both :root and the element directly so the animation picks it up
        document.documentElement.style.setProperty('--ticker-speed', seconds + 's');
        if (track) track.style.animationDuration = seconds + 's';
    }

    // ─── toggle pause ─────────────────────────────────────────────────
    function _setPaused(paused) {
        isPaused = paused;
        ticker.classList.toggle('af-ticker--paused', paused);
        if (pauseBtn) {
            pauseBtn.setAttribute(
                'aria-label',
                paused ? 'Resume ticker' : 'Pause ticker'
            );
        }
    }

    // ─── main init ────────────────────────────────────────────────────
    function init() {
        ticker   = document.getElementById('af-ticker');
        track    = document.getElementById('af-ticker-track');
        pauseBtn = document.getElementById('af-ticker-pause');

        const data = window.AF_DATA?.ticker;

        // Hide if element missing, feature disabled, or no data
        if (!ticker) return;

        if (!data || !data.enabled) {
            ticker.classList.add('af-ticker--hidden');
            return;
        }

        const items = Array.isArray(data.items) ? data.items.filter(i => i.text) : [];

        if (items.length === 0) {
            ticker.classList.add('af-ticker--hidden');
            return;
        }

        // ── update label ──────────────────────────────────────────────
        const labelSpan = ticker.querySelector('.af-ticker-label span');
        if (labelSpan && data.label) labelSpan.textContent = data.label;

        // ── build track (duplicate for seamless loop) ─────────────────
        const itemsHtml = items.map(_itemHtml).join('');
        track.innerHTML = itemsHtml + itemsHtml;   // duplicate set

        // ── speed ─────────────────────────────────────────────────────
        const speed = (data.speed && data.speed > 0) ? data.speed : 40;
        _applySpeed(speed);

        // ── pause on hover ────────────────────────────────────────────
        if (data.pauseOnHover) {
            ticker.addEventListener('mouseenter', () => _setPaused(true));
            ticker.addEventListener('mouseleave', () => {
                if (!isPaused || _manualPause) return; // don't unpause if manually paused
                _setPaused(false);
            });
        }

        // ── manual pause button ───────────────────────────────────────
        let _manualPause = false;
        if (pauseBtn) {
            pauseBtn.addEventListener('click', () => {
                _manualPause = !isPaused;
                _setPaused(!isPaused);
            });
        }

        // ── add play icon to button (CSS toggles visibility) ──────────
        if (pauseBtn) {
            pauseBtn.insertAdjacentHTML(
                'beforeend',
                '<i class="fa-solid fa-play" aria-hidden="true"></i>'
            );
        }
    }

    return { init };
})();

// Boot after DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', Ticker.init);
} else {
    Ticker.init();
}
