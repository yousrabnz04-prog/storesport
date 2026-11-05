/* StoreSport front-end helpers (vanilla JS, no dependencies). */
(function () {
    'use strict';

    var body = document.body;
    var baseUrl = body.dataset.baseUrl || '';
    var csrf = body.dataset.csrf || '';

    // ---------- Toast notifications ----------
    function toast(message, type) {
        var box = document.getElementById('toastBox');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toastBox';
            box.className = 'position-fixed top-0 end-0 p-3';
            box.style.zIndex = '2000';
            document.body.appendChild(box);
        }
        var el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'success') + ' shadow-sm mb-2';
        el.setAttribute('role', 'alert');
        el.textContent = message;
        box.appendChild(el);
        setTimeout(function () { el.remove(); }, 3500);
    }

    // ---------- Confirmation prompts: <button data-confirm="Are you sure?"> ----------
    document.addEventListener('click', function (ev) {
        var el = ev.target.closest('[data-confirm]');
        if (el && !window.confirm(el.getAttribute('data-confirm'))) {
            ev.preventDefault();
        }
    });

    // ---------- Auto-submit selects: <select data-autosubmit> ----------
    document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
        el.addEventListener('change', function () { el.form.submit(); });
    });

    // ---------- Add to cart without a page reload ----------
    function updateCartCount(count) {
        document.querySelectorAll('.js-cart-count').forEach(function (badge) {
            badge.textContent = count;
            badge.classList.toggle('d-none', count < 1);
        });
    }

    document.querySelectorAll('form.js-add-to-cart').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (!window.fetch) { return; }
            ev.preventDefault();
            var button = form.querySelector('button[type="submit"]');
            if (button) { button.disabled = true; }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf },
                credentials: 'same-origin'
            }).then(function (res) {
                return res.json().then(function (data) { return { status: res.status, data: data }; });
            }).then(function (r) {
                if (r.status === 401 && r.data.login) {
                    window.location.href = r.data.login;
                    return;
                }
                toast(r.data.message || 'Done.', r.data.ok ? 'success' : 'danger');
                if (typeof r.data.count === 'number') { updateCartCount(r.data.count); }
            }).catch(function () {
                toast('Something went wrong. Please try again.', 'danger');
            }).finally(function () {
                if (button) { button.disabled = false; }
            });
        });
    });

    // ---------- Password strength and confirmation ----------
    var pw = document.getElementById('password');
    var confirmPw = document.getElementById('confirm_password');
    var bar = document.getElementById('passwordStrength');
    var match = document.getElementById('passwordMatch');

    if (pw && bar) {
        pw.addEventListener('input', function () {
            var v = pw.value, score = 0;
            if (v.length >= 8) { score++; }
            if (/[A-Z]/.test(v) && /[a-z]/.test(v)) { score++; }
            if (/\d/.test(v)) { score++; }
            if (/[^A-Za-z0-9]/.test(v)) { score++; }
            bar.style.width = (score * 25) + '%';
            bar.className = 'progress-bar ' + (score <= 1 ? 'bg-danger' : score === 2 ? 'bg-warning' : 'bg-success');
        });
    }
    if (pw && confirmPw && match) {
        var check = function () {
            if (!confirmPw.value) { match.textContent = ''; return; }
            var ok = pw.value === confirmPw.value;
            match.textContent = ok ? 'Passwords match' : 'Passwords do not match';
            match.className = 'form-text ' + (ok ? 'text-success' : 'text-danger');
        };
        pw.addEventListener('input', check);
        confirmPw.addEventListener('input', check);
    }

    // ---------- Image preview: <input id="image_input"> + <img id="image_preview"> ----------
    var imageInput = document.getElementById('image_input');
    var imagePreview = document.getElementById('image_preview');
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function () {
            if (imageInput.files && imageInput.files[0]) {
                imagePreview.src = URL.createObjectURL(imageInput.files[0]);
            }
        });
    }

    // ---------- Chat: scroll to bottom and poll for new messages ----------
    var chat = document.getElementById('chatMessages');
    if (chat) {
        var self = chat.dataset.self;
        var pollUrl = chat.dataset.pollUrl;
        var threadUser = chat.dataset.threadUser;

        var lastId = function () {
            var nodes = chat.querySelectorAll('.chat-msg');
            return nodes.length ? parseInt(nodes[nodes.length - 1].dataset.id, 10) : 0;
        };
        var scrollDown = function () { chat.scrollTop = chat.scrollHeight; };

        var render = function (m) {
            var wrap = document.createElement('div');
            wrap.className = 'chat-msg ' + (m.mine ? 'me' : 'them');
            wrap.dataset.id = m.id;
            if (m.message) {
                var text = document.createElement('div');
                text.textContent = m.message; // textContent: never interpreted as HTML
                text.style.whiteSpace = 'pre-wrap';
                wrap.appendChild(text);
            }
            if (m.file) {
                var fileRow = document.createElement('div');
                var link = document.createElement('a');
                link.href = m.file.url;
                link.className = 'chat-file';
                link.textContent = '\uD83D\uDCCE ' + m.file.name;
                fileRow.appendChild(link);
                wrap.appendChild(fileRow);
            }
            var time = document.createElement('div');
            time.className = 'chat-time';
            time.textContent = m.time;
            wrap.appendChild(time);
            chat.appendChild(wrap);
        };

        var poll = function () {
            if (document.hidden) { return; }
            var url = pollUrl + '?after=' + lastId() + (threadUser ? '&user_id=' + encodeURIComponent(threadUser) : '');
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (!data || !data.messages || !data.messages.length) { return; }
                    var empty = chat.querySelector('.js-chat-empty');
                    if (empty) { empty.remove(); }
                    data.messages.forEach(render);
                    scrollDown();
                })
                .catch(function () { /* ignore transient network errors */ });
        };

        scrollDown();
        setInterval(poll, 5000);
    }
})();
