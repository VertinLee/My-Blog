/**
 * 验证码发送按钮通用逻辑：点击后 60 秒倒计时，AJAX 请求服务端发送
 */
(function () {
    'use strict';

    if (!window.CB_VERIFY) {
        return;
    }

    var buttons = document.querySelectorAll('[data-scene][data-channel][data-target]');

    Array.prototype.forEach.call(buttons, function (btn) {
        btn.addEventListener('click', function () {
            var targetInput = document.querySelector(btn.getAttribute('data-target'));
            if (!targetInput || !targetInput.value) {
                alert('请先填写邮箱或手机号');
                return;
            }
            var body = 'scene=' + encodeURIComponent(btn.getAttribute('data-scene'))
                + '&channel=' + encodeURIComponent(btn.getAttribute('data-channel'))
                + '&target=' + encodeURIComponent(targetInput.value)
                + '&_csrf=' + encodeURIComponent(window.CB_VERIFY.csrf);

            btn.disabled = true;
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.CB_VERIFY.url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }
                var resp = null;
                try {
                    resp = JSON.parse(xhr.responseText);
                } catch (err) {
                    resp = { code: 1, msg: '网络异常，请重试' };
                }
                if (resp.code === 0) {
                    alert(resp.msg);
                    var left = 60;
                    var timer = setInterval(function () {
                        left -= 1;
                        btn.textContent = left + ' 秒后重发';
                        if (left <= 0) {
                            clearInterval(timer);
                            btn.textContent = '发送验证码';
                            btn.disabled = false;
                        }
                    }, 1000);
                } else {
                    alert(resp.msg);
                    btn.disabled = false;
                }
            };
            xhr.send(body);
        });
    });
})();
