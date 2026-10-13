/* Tanish Arts Preview — admin helpers (copy link, confirms, upload state). */
(function () {
    'use strict';

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); }
            document.body.removeChild(ta);
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-copy');
        if (!btn) { return; }
        var label = btn.textContent;
        copyText(btn.getAttribute('data-copy')).then(function () {
            btn.textContent = '✓ کپی شد';
            btn.classList.add('copied');
        }, function () {
            window.prompt('لینک را کپی کنید:', btn.getAttribute('data-copy'));
        });
        setTimeout(function () { btn.textContent = label; btn.classList.remove('copied'); }, 1800);
    });

    document.querySelectorAll('.js-select').forEach(function (inp) {
        inp.addEventListener('focus', function () { inp.select(); });
    });

    document.querySelectorAll('form.js-confirm').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
        });
    });

    var MAX = 25 * 1024 * 1024;
    document.querySelectorAll('.js-upload-form').forEach(function (f) {
        f.addEventListener('change', function (e) {
            var inp = e.target;
            if (!inp.files || !inp.files[0]) { return; }
            if (inp.classList.contains('js-file') && inp.files[0].size > MAX) {
                window.alert('حجم این فایل بیشتر از ۲۵ مگابایت است.');
                inp.value = '';
            }
        });
        f.addEventListener('submit', function () {
            var s = f.querySelector('.js-upload-status');
            var b = f.querySelector('button[type="submit"]');
            if (s) { s.hidden = false; }
            if (b) { setTimeout(function () { b.disabled = true; }, 0); }
        });
    });
})();
