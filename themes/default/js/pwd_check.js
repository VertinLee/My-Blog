/**
 * 注册页密码规范实时提示
 * 规则与后端 Auth::validate_password_strength 对齐（弱口令黑名单仅服务端判定）
 */
(function () {
    'use strict';

    var pwd = document.getElementById('regPassword');
    var username = document.getElementById('regUsername');
    var rules = {
        'rule-len': function (v) { return v.length >= 8 && v.length <= 64; },
        'rule-upper': function (v) { return /[A-Z]/.test(v); },
        'rule-lower': function (v) { return /[a-z]/.test(v); },
        'rule-digit': function (v) { return /[0-9]/.test(v); },
        'rule-special': function (v) { return /[^A-Za-z0-9]/.test(v); },
        'rule-classes': function (v) {
            var classes = 0;
            if (/[A-Z]/.test(v)) { classes++; }
            if (/[a-z]/.test(v)) { classes++; }
            if (/[0-9]/.test(v)) { classes++; }
            if (/[^A-Za-z0-9]/.test(v)) { classes++; }
            return classes >= 3;
        },
        'rule-username': function (v) {
            var name = username ? username.value : '';
            return name === '' || v.toLowerCase().indexOf(name.toLowerCase()) === -1;
        }
    };

    function check() {
        var value = pwd.value;
        for (var id in rules) {
            if (!rules.hasOwnProperty(id)) {
                continue;
            }
            var el = document.getElementById(id);
            if (!el) {
                continue;
            }
            // 未输入时全部置灰（不加 ok），输入后实时判定
            if (value === '') {
                el.className = 'pwd-rule';
            } else {
                el.className = rules[id](value) ? 'pwd-rule ok' : 'pwd-rule';
            }
        }
    }

    if (!pwd) {
        return;
    }
    pwd.addEventListener('input', check);
    if (username) {
        username.addEventListener('input', check);
    }
    check();
})();
