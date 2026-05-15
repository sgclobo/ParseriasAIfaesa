<?php

/**
 * index.php — Login page (landing)
 * Mobile-first, styled to match AIFAESA design system.
 */
require_once __DIR__ . '/auth.php';

// If already logged in, redirect to app
if (is_logged_in()) {
  header('Location: app.php');
  exit;
}

// Check if there's a cookie-resident username for pre-filling
$cookieUserId = get_cookie_user_id();
$cookieAge    = get_session_cookie_age();
$prefilledUsername = '';
$sessionFresh      = false; // < 48h

if ($cookieUserId) {
  $db = get_db();
  $row = $db->querySingle("SELECT name FROM users WHERE id = $cookieUserId", true);
  if ($row) {
    $prefilledUsername = $row['name'];
    $sessionFresh      = ($cookieAge !== null && $cookieAge < SESSION_48H);
  }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="ParseriasAifaesa — Plataforma de Artigos e Comunicação Interna da AIFAESA" />
  <title>ParseriasAifaesa — Login</title>

  <!-- PWA Meta Tags -->
  <meta name="theme-color" content="#0059bb" />
  <meta name="color-scheme" content="light dark" />
  <link rel="manifest" href="/manifest.json" />
  <link rel="icon" href="/assets/images/favicon.png" type="image/png" />
  <link rel="apple-touch-icon" href="/assets/images/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <meta name="apple-mobile-web-app-title" content="Parseiras" />

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet" />
  <!-- Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
  <!-- Tailwind -->
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#0059bb',
            'primary-container': '#0070ea',
            'primary-fixed': '#d8e2ff',
            'on-primary': '#ffffff',
            secondary: '#575f67',
            'secondary-container': '#d8e1ea',
            'surface': '#f8f9fa',
            'surface-container-lowest': '#ffffff',
            'surface-container-low': '#f3f4f5',
            'surface-container': '#edeeef',
            'surface-container-high': '#e7e8e9',
            'on-surface': '#191c1d',
            'on-surface-variant': '#414754',
            error: '#ba1a1a',
            'error-container': '#ffdad6',
            tertiary: '#006b24',
            'tertiary-container': '#008730',
            'tertiary-fixed': '#83fc8e',
          },
          fontFamily: {
            headline: ['Manrope'],
            body: ['Inter'],
            label: ['Inter'],
          },
          borderRadius: {
            DEFAULT: '0.25rem',
            lg: '0.5rem',
            xl: '0.75rem',
            full: '9999px'
          },
        },
      },
    }
  </script>
  <style>
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    body {
      font-family: 'Inter', sans-serif;
    }

    h1,
    h2,
    h3 {
      font-family: 'Manrope', sans-serif;
    }

    .login-gradient {
      background: linear-gradient(135deg, #0059bb 0%, #0070ea 40%, #006b24 100%);
    }

    .glass {
      background: rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    @keyframes fadeSlideUp {
      from {
        opacity: 0;
        transform: translateY(24px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .animate-in {
      animation: fadeSlideUp 0.5s ease both;
    }

    .animate-delay-1 {
      animation-delay: 0.1s;
    }

    .animate-delay-2 {
      animation-delay: 0.2s;
    }

    .animate-delay-3 {
      animation-delay: 0.3s;
    }
  </style>
</head>

<body class="min-h-screen login-gradient flex items-center justify-center px-4 py-8">

  <div class="w-full max-w-sm">

    <!-- Logo / Brand -->
    <div class="text-center mb-8 animate-in">
      <div class="w-20 h-20 rounded-2xl bg-white/20 glass flex items-center justify-center mx-auto mb-4 shadow-lg">
        <span class="material-symbols-outlined text-white" style="font-size:40px; font-variation-settings:'FILL' 1,'wght' 600,'GRAD' 0,'opsz' 48;">article</span>
      </div>
      <h1 class="text-3xl font-black text-white tracking-tight">Parseiras</h1>
      <p class="text-white/70 text-sm font-medium mt-1 uppercase tracking-widest">AIfaesa Platform</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-3xl shadow-2xl p-8 animate-in animate-delay-1">
      <h2 class="text-xl font-bold text-slate-900 mb-1">Bem-vindo de volta</h2>
      <p class="text-sm text-slate-500 mb-6">Por favor, faça o login para continuar.</p>

      <!-- Error display -->
      <div id="login-error" class="hidden mb-4 p-3 bg-error-container rounded-xl text-error text-sm font-medium flex items-center gap-2">
        <span class="material-symbols-outlined text-sm">error</span>
        <span id="login-error-msg"></span>
      </div>

      <form id="login-form" class="space-y-4">
        <!-- Username -->
        <div>
          <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-1.5 block" for="identifier">Utilizador</label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
            <input
              id="identifier"
              name="identifier"
              type="text"
              autocomplete="username"
              required
              value="<?= htmlspecialchars($prefilledUsername) ?>"
              placeholder="nome de utilizador"
              class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
        </div>

        <!-- Password -->
        <div>
          <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-1.5 block" for="password">Senha</label>
          <div class="relative">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">lock</span>
            <input
              id="password"
              name="password"
              type="password"
              autocomplete="current-password"
              required
              placeholder="••••••••"
              class="w-full pl-10 pr-10 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            <button type="button" id="toggle-pass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition-colors">
              <span class="material-symbols-outlined text-lg" id="toggle-pass-icon">visibility</span>
            </button>
          </div>
          <?php if ($sessionFresh): ?>
            <p class="text-[10px] text-tertiary mt-1 flex items-center gap-1">
              <span class="material-symbols-outlined" style="font-size:12px">check_circle</span>
              Sessão ativa — senha preenchida automaticamente.
            </p>
          <?php endif; ?>
        </div>

        <!-- Submit -->
        <button
          id="login-btn"
          type="submit"
          class="w-full bg-primary hover:bg-primary-container text-white py-3.5 rounded-xl font-bold text-sm transition-all active:scale-95 flex items-center justify-center gap-2 shadow-md shadow-primary/30 mt-2">
          <span class="material-symbols-outlined text-sm" id="login-icon">login</span>
          <span id="login-label">Entrar</span>
        </button>
      </form>

      <p class="text-center text-xs text-slate-400 mt-6">
        Acesso restrito a utilizadores registados.<br />Contacte o administrador para acesso.
      </p>
    </div>

    <p class="text-center text-white/40 text-xs mt-6">
      &copy; <?= date('Y') ?> AIFAESA • ParseriasAifaesa v1.0
    </p>
  </div>

  <script>
    // ── Password visibility toggle ──────────────────────────────────────────────
    document.getElementById('toggle-pass').addEventListener('click', () => {
      const inp = document.getElementById('password');
      const icon = document.getElementById('toggle-pass-icon');
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.textContent = 'visibility_off';
      } else {
        inp.type = 'password';
        icon.textContent = 'visibility';
      }
    });

    // ── Remember username in localStorage ────────────────────────────────────────
    const savedUsername = localStorage.getItem('parseiras_username');
    if (savedUsername && !document.getElementById('identifier').value) {
      document.getElementById('identifier').value = savedUsername;
    }

    <?php if ($sessionFresh && $prefilledUsername): ?>
      // Session is fresh (<48h) — signal PHP-side auto-fill note already shown
      document.getElementById('password').value = '__SESSION_FRESH__';
      document.getElementById('password').placeholder = 'Sessão ativa';
    <?php endif; ?>

    // ── Form submit ─────────────────────────────────────────────────────────────
    document.getElementById('login-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('login-btn');
      const icon = document.getElementById('login-icon');
      const label = document.getElementById('login-label');
      const err = document.getElementById('login-error');
      const errMsg = document.getElementById('login-error-msg');

      const identifier = document.getElementById('identifier').value.trim();
      const password = document.getElementById('password').value;

      btn.disabled = true;
      icon.textContent = 'progress_activity';
      icon.style.animation = 'spin 1s linear infinite';
      label.textContent = 'A entrar...';
      err.classList.add('hidden');

      try {
        const res = await fetch('api/login.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            identifier,
            password
          }),
        });
        const data = await res.json();

        if (data.success) {
          localStorage.setItem('parseiras_username', identifier);
          window.location.href = 'app.php';
        } else {
          errMsg.textContent = data.error || 'Erro ao autenticar.';
          err.classList.remove('hidden');
          btn.disabled = false;
          icon.textContent = 'login';
          icon.style.animation = '';
          label.textContent = 'Entrar';
        }
      } catch (ex) {
        errMsg.textContent = 'Erro de ligação. Tente novamente.';
        err.classList.remove('hidden');
        btn.disabled = false;
        icon.textContent = 'login';
        icon.style.animation = '';
        label.textContent = 'Entrar';
      }
    });

    // Spin keyframe for loading icon
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
    document.head.appendChild(style);

    // ── Service Worker Registration (PWA) ───────────────────────────────────────
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
          .then((registration) => {
            console.log('[PWA] Service Worker registered:', registration);
          })
          .catch((error) => {
            console.warn('[PWA] Service Worker registration failed:', error);
          });
      });
    }
  </script>
</body>

</html>