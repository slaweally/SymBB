/* SymBB custom JavaScript - theme-specific scripts */

(function () {
    'use strict';

    const xmlhttpUrl = '/xmlhttp.php';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : '';
    }

    function getFetchHeaders(method) {
        const headers = {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const token = getCsrfToken() || getCookie('symbb_csrf');
        if (token) {
            headers['X-CSRF-Token'] = token;
        }
        return headers;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.postbit-quick-edit-btn');
            if (!btn) return;

            e.preventDefault();
            const pid = btn.getAttribute('data-pid');
            const article = document.getElementById('post-' + pid);
            if (!article) return;

            const wrap = article.querySelector('.postbit-message-wrap');
            const messageDiv = wrap.querySelector('.postbit-message');
            const formDiv = wrap.querySelector('.postbit-quick-edit-form');
            const textarea = formDiv.querySelector('.postbit-quick-edit-textarea');

            if (formDiv.classList.contains('hidden')) {
                messageDiv.classList.add('hidden');
                formDiv.classList.remove('hidden');
                textarea.value = '';
                textarea.disabled = true;

                fetch(xmlhttpUrl + '?action=get_post&pid=' + pid, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        textarea.value = data.message || '';
                        textarea.disabled = false;
                        textarea.focus();
                    })
                    .catch(function () {
                        alert('Mesaj yüklenemedi.');
                        messageDiv.classList.remove('hidden');
                        formDiv.classList.add('hidden');
                        textarea.disabled = false;
                    });
            }
        });

        document.body.addEventListener('click', function (e) {
            const saveBtn = e.target.closest('.postbit-quick-edit-save');
            if (!saveBtn) return;

            const formDiv = saveBtn.closest('.postbit-quick-edit-form');
            const wrap = formDiv.closest('.postbit-message-wrap');
            const article = wrap.closest('article');
            const pid = article.id.replace('post-', '');
            const textarea = formDiv.querySelector('.postbit-quick-edit-textarea');
            const messageDiv = wrap.querySelector('.postbit-message');

            const value = textarea.value.trim();
            saveBtn.disabled = true;

            const params = new URLSearchParams();
            params.append('action', 'edit_post');
            params.append('pid', pid);
            params.append('value', value);

            fetch(xmlhttpUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: getFetchHeaders('POST'),
                body: params.toString(),
            })
                .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success && data.html !== undefined) {
                            messageDiv.innerHTML = data.html;
                            messageDiv.classList.remove('hidden');
                            formDiv.classList.add('hidden');
                            textarea.value = '';
                    } else {
                        alert(data.error || 'Kaydetme başarısız.');
                    }
                })
                .catch(function () {
                    alert('Bağlantı hatası.');
                })
                .finally(function () {
                    saveBtn.disabled = false;
                });
        });

        document.body.addEventListener('click', function (e) {
            const cancelBtn = e.target.closest('.postbit-quick-edit-cancel');
            if (!cancelBtn) return;

            const formDiv = cancelBtn.closest('.postbit-quick-edit-form');
            const wrap = formDiv.closest('.postbit-message-wrap');
            const messageDiv = wrap.querySelector('.postbit-message');

            messageDiv.classList.remove('hidden');
            formDiv.classList.add('hidden');
        });

        document.body.addEventListener('click', function (e) {
            const btn = e.target.closest('.thread-quick-edit-subject-btn');
            if (!btn) return;

            e.preventDefault();
            const tid = btn.getAttribute('data-tid');
            const span = document.getElementById('thread-subject');
            if (!span) return;

            const currentSubject = span.textContent;
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'thread-subject-edit-input';
            input.value = currentSubject;
            input.style.width = Math.max(300, span.offsetWidth) + 'px';
            span.replaceWith(input);
            input.focus();
            input.select();

            function restoreSpan(content) {
                const s = document.createElement('span');
                s.className = 'thread-title';
                s.id = 'thread-subject';
                s.textContent = content;
                input.replaceWith(s);
            }

            function finishEdit() {
                const newVal = input.value.trim();
                if (newVal === currentSubject) {
                    restoreSpan(currentSubject);
                    return;
                }
                const params = new URLSearchParams();
                params.append('action', 'edit_subject');
                params.append('tid', tid);
                params.append('value', newVal);
                fetch(xmlhttpUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: getFetchHeaders('POST'),
                    body: params.toString(),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.success && data.html) {
                            const newSpan = document.createElement('span');
                            newSpan.className = 'thread-title';
                            newSpan.id = 'thread-subject';
                            newSpan.innerHTML = data.html;
                            input.replaceWith(newSpan);
                        } else {
                            restoreSpan(currentSubject);
                            if (data.error) alert(data.error);
                        }
                    })
                    .catch(function () {
                        restoreSpan(currentSubject);
                        alert('Bağlantı hatası.');
                    });
            }

            input.addEventListener('blur', function () { setTimeout(finishEdit, 150); });
            input.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter') {
                    ev.preventDefault();
                    input.blur();
                }
                if (ev.key === 'Escape') {
                    input.value = currentSubject;
                    restoreSpan(currentSubject);
                }
            });
        });

        /* Çoklu Alıntı (Multi-quote) */
        const STORAGE_KEY = 'symbb_multiquote_pids';
        document.querySelectorAll('.postbit-multiquote-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const container = document.querySelector('.thread-show-page[data-tid]');
                if (!container) return;
                const tid = parseInt(container.getAttribute('data-tid'), 10);
                const pid = parseInt(this.getAttribute('data-pid'), 10);
                let data = {};
                try {
                    const raw = sessionStorage.getItem(STORAGE_KEY);
                    data = raw ? JSON.parse(raw) : {};
                } catch (e) {}
                let pids = data[tid] || [];
                const idx = pids.indexOf(pid);
                if (idx >= 0) pids.splice(idx, 1);
                else pids.push(pid);
                pids.sort(function (a, b) { return a - b; });
                data[tid] = pids;
                sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                document.querySelectorAll('.postbit-multiquote-btn').forEach(function (b) {
                    const p = parseInt(b.getAttribute('data-pid'), 10);
                    b.classList.toggle('multiquote-active', pids.indexOf(p) >= 0);
                });
            });
        });
        (function () {
            const container = document.querySelector('.thread-show-page[data-tid]');
            if (!container) return;
            const tid = parseInt(container.getAttribute('data-tid'), 10);
            let pids = [];
            try {
                const raw = sessionStorage.getItem(STORAGE_KEY);
                const data = raw ? JSON.parse(raw) : {};
                pids = data[tid] || [];
            } catch (e) {}
            document.querySelectorAll('.postbit-multiquote-btn').forEach(function (btn) {
                const pid = parseInt(btn.getAttribute('data-pid'), 10);
                btn.classList.toggle('multiquote-active', pids.indexOf(pid) >= 0);
            });
        })();
    });
})();
