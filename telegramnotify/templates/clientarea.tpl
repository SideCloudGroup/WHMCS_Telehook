<div id="tg-notify" data-token="{$token|escape:'html'}">
{literal}
<style>
#tg-notify {
  max-width: 640px;
  margin: 8px auto 32px;
  color: #1c2430;
  font-family: "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
#tg-notify * { box-sizing: border-box; }
#tg-notify .tg-card {
  background: #fff;
  border: 1px solid #e6edf5;
  border-radius: 20px;
  box-shadow: 0 16px 40px rgba(28, 52, 84, 0.08);
  overflow: hidden;
}
#tg-notify .tg-hero {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px 24px 20px;
  background: linear-gradient(135deg, #e8f7ff 0%, #f7fbff 55%, #fff 100%);
}
#tg-notify .tg-mark {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: #2aabee;
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  box-shadow: 0 8px 18px rgba(42, 171, 238, 0.35);
}
#tg-notify .tg-hero h2 {
  margin: 0 0 4px;
  font-size: 22px;
  font-weight: 650;
  letter-spacing: -0.02em;
}
#tg-notify .tg-hero p {
  margin: 0;
  color: #5d6b7c;
  font-size: 14px;
}
#tg-notify .tg-pill {
  margin-left: auto;
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 650;
  background: #eef2f6;
  color: #5d6b7c;
  white-space: nowrap;
}
#tg-notify .tg-pill.is-on { background: #e5f8ee; color: #0f7a45; }
#tg-notify .tg-pill.is-off { background: #fff4e5; color: #9a5b00; }
#tg-notify .tg-body { padding: 4px 24px 24px; }
#tg-notify .tg-alert {
  margin: 0 0 14px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #f4f7fb;
  color: #3d4b5c;
  font-size: 14px;
}
#tg-notify .tg-alert.is-ok { background: #e9f8ef; color: #0f7a45; }
#tg-notify .tg-alert.is-bad { background: #fdecec; color: #a12626; }
#tg-notify .tg-account {
  margin: 0 0 14px;
  font-size: 15px;
  color: #1c2430;
}
#tg-notify .tg-account b { font-weight: 650; }
#tg-notify .tg-points {
  list-style: none;
  margin: 0 0 18px;
  padding: 0;
  display: grid;
  gap: 8px;
}
#tg-notify .tg-points li {
  display: flex;
  gap: 12px;
  align-items: center;
  padding: 12px 14px;
  border-radius: 14px;
  background: #f7f9fc;
  color: #3d4b5c;
  font-size: 14px;
}
#tg-notify .tg-points span {
  flex: 0 0 auto;
  min-width: 44px;
  text-align: center;
  padding: 4px 8px;
  border-radius: 999px;
  background: #fff;
  color: #1a8fd0;
  font-size: 12px;
  font-weight: 700;
}
#tg-notify .tg-linkbox {
  margin-bottom: 16px;
  padding: 14px;
  border-radius: 14px;
  border: 1px dashed #b9dff3;
  background: #f5fbff;
}
#tg-notify .tg-linkbox p {
  margin: 0 0 10px;
  color: #3d4b5c;
  font-size: 13px;
}
#tg-notify .tg-linkrow {
  display: flex;
  gap: 8px;
  margin-bottom: 10px;
}
#tg-notify .tg-linkrow input {
  flex: 1;
  min-width: 0;
  height: 40px;
  padding: 0 12px;
  border: 1px solid #d5e3ef;
  border-radius: 10px;
  background: #fff;
  color: #1c2430;
  font-size: 13px;
}
#tg-notify .tg-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
#tg-notify .tg-btn {
  appearance: none;
  border: 0;
  cursor: pointer;
  height: 42px;
  padding: 0 16px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 650;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
#tg-notify .tg-btn:disabled { opacity: 0.6; cursor: default; }
#tg-notify .tg-btn-primary { background: #2aabee; color: #fff; }
#tg-notify .tg-btn-primary:hover:not(:disabled) { background: #1b9adf; }
#tg-notify .tg-btn-ghost { background: transparent; color: #8a4a4a; }
#tg-notify .tg-btn-ghost:hover:not(:disabled) { background: #fdecec; }
#tg-notify .tg-btn-lite {
  background: #fff;
  color: #1a8fd0;
  border: 1px solid #d5e3ef;
  height: 40px;
}
@media (max-width: 640px) {
  #tg-notify .tg-hero { flex-wrap: wrap; padding: 20px; }
  #tg-notify .tg-pill { margin-left: 68px; }
  #tg-notify .tg-body { padding: 0 20px 20px; }
  #tg-notify .tg-linkrow { flex-direction: column; }
}
</style>
<section class="tg-card">
  <div class="tg-hero">
    <div class="tg-mark" aria-hidden="true">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <path d="M20.5 4.5 3.8 10.9c-1.1.4-1.1 1.9.1 2.2l4.3 1.3 1.6 5c.3.9 1.5 1.1 2.1.3l2.3-2.9 4.3 3.2c.8.6 1.9.1 2.1-.8l2.6-13.1c.2-1.1-.8-2-1.8-1.6Z" fill="#fff"/>
      </svg>
    </div>
    <div>
      <h2>Telegram 通知</h2>
      <p>这个账号下的产品动态，发到你的 Telegram</p>
    </div>
    <span id="tg-pill" class="tg-pill">加载中</span>
  </div>
  <div class="tg-body">
    <div id="tg-alert" class="tg-alert" hidden></div>
    <div id="tg-account" class="tg-account" hidden></div>
    <ul class="tg-points">
      <li><span>开通</span>产品开通成功后通知你</li>
      <li><span>账单</span>续费账单生成时通知你</li>
      <li><span>到期</span>到期前提醒你</li>
    </ul>
    <div id="tg-linkbox" class="tg-linkbox" hidden>
      <p>在 Telegram 里打开并点击开始。完成后这个页面会自动更新，不用刷新。</p>
      <div class="tg-linkrow">
        <input id="tg-link" readonly aria-label="绑定链接" />
        <button type="button" id="tg-copy" class="tg-btn tg-btn-lite">复制</button>
      </div>
      <a id="tg-open" class="tg-btn tg-btn-primary" target="_blank" rel="noopener">打开 Telegram</a>
    </div>
    <div class="tg-actions">
      <button type="button" id="tg-bind" class="tg-btn tg-btn-primary" disabled>生成绑定链接</button>
      <button type="button" id="tg-unbind" class="tg-btn tg-btn-ghost" hidden>解除绑定</button>
    </div>
  </div>
</section>
<script>
(function () {
  var root = document.getElementById('tg-notify');
  if (!root) return;
  var token = root.getAttribute('data-token') || '';
  var endpoint = window.location.pathname + window.location.search;
  var pill = document.getElementById('tg-pill');
  var alertBox = document.getElementById('tg-alert');
  var account = document.getElementById('tg-account');
  var linkbox = document.getElementById('tg-linkbox');
  var linkInput = document.getElementById('tg-link');
  var openLink = document.getElementById('tg-open');
  var copyBtn = document.getElementById('tg-copy');
  var bindBtn = document.getElementById('tg-bind');
  var unbindBtn = document.getElementById('tg-unbind');
  var pollTimer = null;
  var busy = false;
  var confirmUnbind = false;

  function showAlert(text, kind) {
    if (!text) {
      alertBox.hidden = true;
      alertBox.textContent = '';
      alertBox.className = 'tg-alert';
      return;
    }
    alertBox.hidden = false;
    alertBox.textContent = text;
    alertBox.className = 'tg-alert' + (kind === 'ok' ? ' is-ok' : kind === 'bad' ? ' is-bad' : '');
  }

  function render(state) {
    pill.className = 'tg-pill';
    if (state.bound) {
      pill.textContent = '已绑定';
      pill.className = 'tg-pill is-on';
      account.hidden = false;
      account.innerHTML = state.username
        ? '当前绑定 <b>@' + escapeText(state.username) + '</b>'
        : '当前已绑定 Telegram';
      bindBtn.textContent = '更换 Telegram';
      unbindBtn.hidden = false;
      linkbox.hidden = true;
      stopPoll();
    } else if (state.blocked) {
      pill.textContent = '已失效';
      pill.className = 'tg-pill is-off';
      account.hidden = false;
      account.textContent = '之前的绑定已失效，可能是停用了 Bot 或会话不存在。请重新绑定。';
      bindBtn.textContent = '重新绑定';
      unbindBtn.hidden = false;
    } else {
      pill.textContent = '未绑定';
      account.hidden = true;
      account.textContent = '';
      bindBtn.textContent = '生成绑定链接';
      unbindBtn.hidden = true;
      unbindBtn.textContent = '解除绑定';
      confirmUnbind = false;
    }
    bindBtn.disabled = busy;
  }

  function escapeText(value) {
    return String(value).replace(/[&<>"']/g, function (ch) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch];
    });
  }

  function post(action) {
    var body = new URLSearchParams();
    body.set('ajax', '1');
    body.set('token', token);
    body.set('action', action);
    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok && data && !data.message) {
          data.message = '操作失败，请稍后再试';
        }
        return data;
      });
    });
  }

  function stopPoll() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
  }

  function startPoll() {
    stopPoll();
    pollTimer = setInterval(function () {
      post('status').then(function (data) {
        if (!data || data.ok === false) return;
        if (data.bound) {
          render(data);
          showAlert('绑定成功', 'ok');
        }
      }).catch(function () {});
    }, 2500);
  }

  function refresh() {
    return post('status').then(function (data) {
      if (!data || data.ok === false) {
        showAlert((data && data.message) || '暂时无法读取绑定状态', 'bad');
        bindBtn.disabled = false;
        return;
      }
      render(data);
    }).catch(function () {
      showAlert('网络异常，请重试', 'bad');
      bindBtn.disabled = false;
    });
  }

  bindBtn.addEventListener('click', function () {
    if (busy) return;
    busy = true;
    bindBtn.disabled = true;
    confirmUnbind = false;
    unbindBtn.textContent = '解除绑定';
    post('bind').then(function (data) {
      busy = false;
      if (!data || data.ok === false || !data.link) {
        showAlert((data && data.message) || '暂时无法生成绑定链接', 'bad');
        bindBtn.disabled = false;
        return;
      }
      linkInput.value = data.link;
      openLink.href = data.link;
      linkbox.hidden = false;
      showAlert(data.message || '请在 Telegram 中完成绑定', 'ok');
      render(data);
      startPoll();
    }).catch(function () {
      busy = false;
      bindBtn.disabled = false;
      showAlert('网络异常，请重试', 'bad');
    });
  });

  unbindBtn.addEventListener('click', function () {
    if (busy) return;
    if (!confirmUnbind) {
      confirmUnbind = true;
      unbindBtn.textContent = '确认解除';
      return;
    }
    busy = true;
    unbindBtn.disabled = true;
    post('unbind').then(function (data) {
      busy = false;
      unbindBtn.disabled = false;
      confirmUnbind = false;
      unbindBtn.textContent = '解除绑定';
      if (!data || data.ok === false) {
        showAlert((data && data.message) || '解除绑定失败，请稍后再试', 'bad');
        return;
      }
      linkbox.hidden = true;
      stopPoll();
      render(data);
      showAlert(data.message || '已解除绑定', 'ok');
    }).catch(function () {
      busy = false;
      unbindBtn.disabled = false;
      showAlert('网络异常，请重试', 'bad');
    });
  });

  copyBtn.addEventListener('click', function () {
    var value = linkInput.value;
    if (!value) return;
    var done = function () {
      copyBtn.textContent = '已复制';
      setTimeout(function () { copyBtn.textContent = '复制'; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(done).catch(function () {
        linkInput.select();
        document.execCommand('copy');
        done();
      });
      return;
    }
    linkInput.select();
    document.execCommand('copy');
    done();
  });

  refresh();
})();
</script>
{/literal}
</div>
