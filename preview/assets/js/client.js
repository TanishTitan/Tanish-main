/* Tanish Arts Preview — client page behaviour (vanilla JS, no dependencies). */
(function () {
    'use strict';

    var data = {};
    try {
        data = JSON.parse(document.getElementById('ta-data').textContent || '{}');
    } catch (e) { /* keep defaults */ }

    var body = document.body;
    var store = {
        get: function (k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
    };

    function all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function setSeg(seg, btn) {
        all('button', seg).forEach(function (b) {
            var on = b === btn;
            b.classList.toggle('on', on);
            if (b.hasAttribute('aria-selected')) { b.setAttribute('aria-selected', on ? 'true' : 'false'); }
        });
    }

    /* ---------- light deterrents ---------- */
    document.addEventListener('contextmenu', function (e) {
        if (e.target.closest && e.target.closest('.yt-thumb, .pick-img, .yt-av, img')) { e.preventDefault(); }
    });
    document.addEventListener('dragstart', function (e) {
        if (e.target.tagName === 'IMG') { e.preventDefault(); }
    });

    /* ---------- YouTube theme ---------- */
    var themeSeg = document.querySelector('.js-theme');
    function applyTheme(t) {
        body.setAttribute('data-yt', t);
        var btn = themeSeg && themeSeg.querySelector('[data-theme="' + t + '"]');
        if (btn) { setSeg(themeSeg, btn); }
    }
    if (themeSeg) {
        var saved = store.get('ta-yt-theme');
        if (saved === 'light' || saved === 'dark') { applyTheme(saved); }
        themeSeg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            applyTheme(b.getAttribute('data-theme'));
            store.set('ta-yt-theme', b.getAttribute('data-theme'));
        });
    }

    /* ---------- version tabs ---------- */
    var srcByKey = {};
    (data.versions || []).forEach(function (v) { srcByKey[v.key] = v.src; });
    var verSeg = document.querySelector('.js-versions');
    if (verSeg) {
        verSeg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) { return; }
            var key = b.getAttribute('data-key');
            setSeg(verSeg, b);
            all('img.js-v').forEach(function (img) {
                if (srcByKey[key]) { img.src = srcByKey[key]; img.setAttribute('data-key', key); }
            });
        });
    }

    /* ---------- in context / side by side ---------- */
    var viewSeg = document.querySelector('.js-view');
    function showView(name) {
        all('.js-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
        if (verSeg) { verSeg.hidden = name === 'compare'; }
        if (viewSeg) {
            var b = viewSeg.querySelector('[data-view="' + name + '"]');
            if (b) { setSeg(viewSeg, b); }
        }
        fitAll();
    }
    if (viewSeg) {
        viewSeg.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (b) { showView(b.getAttribute('data-view')); }
        });
    }

    /* "I like Version X" buttons in the comparison */
    all('.js-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var key = btn.getAttribute('data-key');
            var radio = document.querySelector('.js-fb input[name="version"][value="' + key + '"]');
            if (radio) { radio.checked = true; }
            var fb = document.getElementById('feedback');
            if (fb) { fb.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            var ta = document.getElementById('fb-comment');
            if (ta) { setTimeout(function () { ta.focus({ preventScroll: true }); }, 450); }
        });
    });

    /* ---------- desktop mockup: lay out at 1280px, scale to fit ---------- */
    var scalers = all('.js-scale');
    function fit(el) {
        var inner = el.firstElementChild;
        var design = parseInt(el.getAttribute('data-width'), 10) || 1280;
        var avail = el.clientWidth;
        if (!inner || !avail) { return; }
        var s = Math.min(1, avail / design);
        el.classList.add('is-scaled');
        inner.style.transform = s < 1 ? 'scale(' + s + ')' : '';
        inner.style.left = s < 1 ? '0' : Math.max(0, (avail - design) / 2) + 'px';
        el.style.height = Math.ceil(inner.offsetHeight * s) + 'px';
        var note = el.parentNode.parentNode.querySelector('.js-scale-note');
        if (note) { note.hidden = s > 0.85; }
    }
    function fitAll() { scalers.forEach(fit); }
    var raf = 0;
    window.addEventListener('resize', function () {
        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(fitAll);
    });
    fitAll();
    window.addEventListener('load', fitAll);
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(fitAll); }

    /* ---------- competitor thumbnails (loaded from i.ytimg.com by the browser) ---------- */
    function checkComp(img) {
        // YouTube answers unknown IDs with a 120×90 grey placeholder.
        if (img.complete && (img.naturalWidth === 0 || img.naturalWidth === 120)) {
            var box = img.parentNode;
            box.classList.add('is-ph');
            img.remove();
        }
    }
    all('img.js-comp').forEach(function (img) {
        if (img.complete) { checkComp(img); }
        img.addEventListener('load', function () { checkComp(img); });
        img.addEventListener('error', function () { checkComp(img); });
    });

    /* ---------- feedback ---------- */
    var form = document.querySelector('.js-fb');
    var thanks = document.querySelector('.js-thanks');
    var errBox = document.querySelector('.js-fb-error');
    if (form && window.fetch && window.FormData) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = form.querySelector('button[type="submit"]');
            var fd = new FormData(form);
            if (form.querySelector('input[type="radio"]') && !fd.get('version') && !String(fd.get('comment') || '').trim()) {
                errBox.textContent = 'Pick a version or write a short comment.';
                errBox.hidden = false;
                return;
            }
            btn.disabled = true;
            errBox.hidden = true;
            fetch(data.feedbackUrl || form.action, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().catch(function () { return { ok: false, message: 'Something went wrong — please try again.' }; });
            }).then(function (res) {
                btn.disabled = false;
                if (res && res.ok) {
                    form.hidden = true;
                    thanks.hidden = false;
                    form.querySelector('textarea').value = '';
                    thanks.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    errBox.textContent = (res && res.message) || 'Something went wrong — please try again.';
                    errBox.hidden = false;
                }
            }).catch(function () {
                btn.disabled = false;
                errBox.textContent = 'Network error — please check your connection and try again.';
                errBox.hidden = false;
            });
        });
    }
    var again = document.querySelector('.js-again');
    if (again && form) {
        again.addEventListener('click', function () {
            thanks.hidden = true;
            form.hidden = false;
        });
    }
})();
