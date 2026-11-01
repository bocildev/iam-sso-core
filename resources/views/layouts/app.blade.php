<!doctype html>
<html lang="id" class="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'IAM-SSO-CORE') — Central Identity & OIDC Provider</title>
<script>
(function () {
  try {
    var saved = localStorage.getItem('iam_theme');
    var mode = saved ? saved : 'dark';
    document.documentElement.classList.toggle('dark', mode === 'dark');
  } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/design-tokens.css">
</head>
<body>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
@yield('content')
<script>
(function () {
  function syncThemeLabels() {
    var isDark = document.documentElement.classList.contains('dark');
    document.querySelectorAll('[data-theme-label]').forEach(function (el) {
      el.textContent = isDark ? 'Dark' : 'Light';
    });
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.setAttribute('aria-pressed', String(isDark));
    });
  }
  syncThemeLabels();

  document.addEventListener('click', function (e) {
    var themeBtn = e.target.closest('[data-theme-toggle]');
    if (themeBtn) {
      var nextDark = !document.documentElement.classList.contains('dark');
      document.documentElement.classList.toggle('dark', nextDark);
      try { localStorage.setItem('iam_theme', nextDark ? 'dark' : 'light'); } catch (err) {}
      syncThemeLabels();
      return;
    }

    var copyBtn = e.target.closest('[data-copy]');
    if (copyBtn) {
      var text = copyBtn.getAttribute('data-copy');
      var prev = copyBtn.dataset.label || copyBtn.textContent;
      copyBtn.dataset.label = prev;
      function flash(msg) {
        copyBtn.textContent = msg;
        setTimeout(function () { copyBtn.textContent = prev; }, 1600);
      }
      function legacyCopy() {
        // Fallback untuk HTTP non-secure (mis. http://iam-sso-core.test:8888) di mana navigator.clipboard tidak tersedia
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.cssText = 'position:fixed;top:-1000px;opacity:0';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
        flash(ok ? 'Tersalin' : 'Gagal salin');
      }
      if (text) {
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function () { flash('Tersalin'); }, legacyCopy);
        } else {
          legacyCopy();
        }
      }
      return;
    }

    var pwBtn = e.target.closest('[data-toggle-password]');
    if (pwBtn) {
      var input = document.getElementById(pwBtn.getAttribute('data-toggle-password'));
      if (input) {
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        pwBtn.setAttribute('aria-pressed', String(!showing));
        pwBtn.textContent = showing ? 'Tampilkan' : 'Sembunyikan';
      }
    }
  });
})();
</script>
<script>
document.querySelectorAll('details.help[data-help-key]').forEach(function (d) {
  var k = 'iam_help_' + d.getAttribute('data-help-key');
  try { if (localStorage.getItem(k) === 'closed') d.removeAttribute('open'); } catch (e) {}
  d.addEventListener('toggle', function () {
    try { localStorage.setItem(k, d.open ? 'open' : 'closed'); } catch (e) {}
  });
});
</script>
</body>
</html>
