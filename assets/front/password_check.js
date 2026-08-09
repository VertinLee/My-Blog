/**
 * 通用改密/重置密码前端校验：强度校验 + 两次输入一致性实时反馈，不合法阻止提交
 * 口径与服务端 Auth::validate_password_strength 对齐（弱口令黑名单仍由服务端终判，
 * 前端仅为体验，不作为安全判定依据）
 *
 * 接入方式：form 加 data-pwd-check；表单内需有 name="new_password" 与
 * name="new_password2" 或 name="confirm_password"；「不得含用户名」规则的账号来源：
 * data-account（选择器，取该输入框的值）或 data-username（直接给值）
 */
(function () {
    'use strict';

    var forms = document.querySelectorAll('form[data-pwd-check]');
    if (!forms.length) {
        return;
    }

    /** 密码强度：长度 8-64、四类字符至少三类、不含用户名（与后端口径一致） */
    function pwdError(v, name) {
        if (v === '') {
            return '请输入新密码';
        }
        if (v.length < 8 || v.length > 64) {
            return '密码长度须为 8-64 位';
        }
        var classes = 0;
        if (/[A-Z]/.test(v)) { classes++; }
        if (/[a-z]/.test(v)) { classes++; }
        if (/[0-9]/.test(v)) { classes++; }
        if (/[^A-Za-z0-9]/.test(v)) { classes++; }
        if (classes < 3) {
            return '密码须包含大写/小写/数字/特殊字符中的至少三类';
        }
        if (name !== '' && v.toLowerCase().indexOf(name.toLowerCase()) !== -1) {
            return '密码不得包含用户名';
        }
        return '';
    }

    function trim(v) {
        return v.replace(/^\s+|\s+$/g, '');
    }

    Array.prototype.forEach.call(forms, function (form) {
        var pwd = form.querySelector('input[name="new_password"]');
        var pwd2 = form.querySelector('input[name="new_password2"], input[name="confirm_password"]');
        var matchEl = form.querySelector('.pwd-match');
        if (!pwd || !pwd2) {
            return;
        }

        /** 「不得含用户名」的账号来源：优先 data-account 指向的输入框 */
        function accountName() {
            var sel = form.getAttribute('data-account');
            if (sel) {
                var el = document.querySelector(sel);
                if (el) {
                    return trim(el.value);
                }
            }
            return trim(form.getAttribute('data-username') || '');
        }

        /** 在字段后方显示错误（同一字段复用同一提示节点） */
        function setError(input, msg) {
            var holder = input.parentNode.querySelector('.field-error');
            if (!holder) {
                holder = document.createElement('span');
                holder.className = 'field-error';
                input.parentNode.insertBefore(holder, input.nextSibling);
            }
            holder.textContent = msg;
            input.classList.add('invalid');
        }

        function clearError(input) {
            var holder = input.parentNode.querySelector('.field-error');
            if (holder) {
                holder.parentNode.removeChild(holder);
            }
            input.classList.remove('invalid');
        }

        /** 确认密码实时反馈（输入即显示一致/不一致） */
        function updateMatch() {
            if (!matchEl) {
                return;
            }
            if (pwd2.value === '') {
                matchEl.textContent = '';
                matchEl.className = 'pwd-match';
            } else if (pwd2.value === pwd.value) {
                matchEl.textContent = '两次输入一致 ✓';
                matchEl.className = 'pwd-match ok';
            } else {
                matchEl.textContent = '两次输入不一致';
                matchEl.className = 'pwd-match err';
            }
        }

        function validatePwd() {
            return pwdError(pwd.value, accountName());
        }

        function validatePwd2() {
            if (pwd2.value === '') {
                return '请再次输入新密码';
            }
            if (pwd2.value !== pwd.value) {
                return '两次输入的密码不一致';
            }
            return '';
        }

        // 输入即重验：已标错的字段修正后立刻消除提示
        [pwd, pwd2].forEach(function (input) {
            input.addEventListener('input', function () {
                var msg = input === pwd ? validatePwd() : validatePwd2();
                if (input.classList.contains('invalid') && msg === '') {
                    clearError(input);
                }
                updateMatch();
            });
        });

        // 提交前校验：任一不通过即阻止请求并定位到第一个错误字段
        form.addEventListener('submit', function (ev) {
            var firstInvalid = null;
            var checks = [
                { input: pwd, msg: validatePwd() },
                { input: pwd2, msg: validatePwd2() }
            ];
            for (var i = 0; i < checks.length; i++) {
                if (checks[i].msg !== '') {
                    setError(checks[i].input, checks[i].msg);
                    if (!firstInvalid) {
                        firstInvalid = checks[i].input;
                    }
                } else {
                    clearError(checks[i].input);
                }
            }
            updateMatch();
            if (firstInvalid) {
                ev.preventDefault();
                firstInvalid.focus();
            }
        });

        updateMatch();
    });
})();
