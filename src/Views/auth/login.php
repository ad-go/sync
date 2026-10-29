<?php
declare(strict_types=1);
/** Login view. Rendered by AdGo\Sync\Controllers\LoginController::loginView() via Config\Auth::$views['login']. */
$lf      = (string) (setting('Sync.loginField') ?: 'email');
$show2fa = env('sync.disable2fa') !== true;
$site    = \AdGo\Sync\Libraries\Branding::siteName();
$logo    = \AdGo\Sync\Libraries\Branding::logoUrl();
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in · <?= esc($site) ?></title>
<?= view('AdGo\Sync\Views\ui\_theme') ?>
<style>
html,body{height:100%}
body{display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px;box-sizing:border-box}
.lc{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:28px 24px;width:100%;max-width:400px;box-shadow:var(--shadow)}
.lc-brand{text-align:center;margin-bottom:22px}
.lc-brand img{max-height:40px;display:block;margin:0 auto 8px;border-radius:4px}
.lc-brand h1{font-size:17px;font-weight:700;margin:0}
.lf{margin-bottom:12px}
.lf label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--mute);margin-bottom:4px}
.lf input{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid var(--line);border-radius:8px;background:var(--card);color:var(--fg);font-size:14px}
.lf input:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px color-mix(in srgb,var(--acc) 18%,transparent)}
.lf-hint{font-size:11px;color:var(--mute);margin:3px 0 0}
.lf-sep{display:flex;align-items:center;gap:8px;margin:10px 0;color:var(--mute);font-size:11px}
.lf-sep::before,.lf-sep::after{content:'';flex:1;height:1px;background:var(--line)}
.lc-submit{width:100%;margin-top:4px;font-size:14px;padding:9px}
.lc-links{text-align:center;margin-top:14px;font-size:13px}
.lc-links a{color:var(--acc)}
.alert-err{background:color-mix(in srgb,#e53935 10%,var(--card));border:1px solid color-mix(in srgb,#e53935 35%,transparent);border-radius:8px;padding:9px 12px;margin-bottom:14px;font-size:13px;color:color-mix(in srgb,#c62828,var(--fg) 20%)}
.alert-ok{background:color-mix(in srgb,#2e7d32 10%,var(--card));border:1px solid color-mix(in srgb,#2e7d32 35%,transparent);border-radius:8px;padding:9px 12px;margin-bottom:14px;font-size:13px}
</style>
</head>
<body>
<div class="lc">

  <div class="lc-brand">
    <?php if ($logo !== ''): ?>
      <img src="<?= esc(site_url($logo)) ?>" alt="<?= esc($site) ?>">
    <?php else: ?>
      <h1><?= esc($site) ?></h1>
    <?php endif ?>
  </div>

  <?php if (session('error') !== null): ?>
    <div class="alert-err"><?= esc(session('error')) ?></div>
  <?php elseif (session('errors') !== null): ?>
    <div class="alert-err">
      <?php foreach ((array) session('errors') as $e): ?>
        <?= esc($e) ?><br>
      <?php endforeach ?>
    </div>
  <?php endif ?>

  <?php if (session('message') !== null): ?>
    <div class="alert-ok"><?= esc(session('message')) ?></div>
  <?php endif ?>

  <form action="<?= url_to('login') ?>" method="post">
    <?= csrf_field() ?>

    <?php if (in_array($lf, ['email', 'both'], true)): ?>
    <div class="lf">
      <label for="li-email">E-mail</label>
      <input type="email" id="li-email" name="email" inputmode="email" autocomplete="email"
             value="<?= esc(old('email')) ?>" <?= $lf === 'email' ? 'required' : '' ?>>
    </div>
    <?php endif ?>

    <?php if ($lf === 'both'): ?>
    <div class="lf-sep">or</div>
    <?php endif ?>

    <?php if (in_array($lf, ['username', 'both'], true)): ?>
    <div class="lf">
      <label for="li-user">Username</label>
      <input type="text" id="li-user" name="username" autocomplete="username"
             value="<?= esc(old('username')) ?>" <?= $lf === 'username' ? 'required' : '' ?>>
    </div>
    <?php endif ?>

    <div class="lf">
      <label for="li-pass">Password</label>
      <input type="password" id="li-pass" name="password" autocomplete="current-password" required>
    </div>

    <?php if ($show2fa): ?>
    <div class="lf">
      <label for="li-token">2FA code</label>
      <input type="text" id="li-token" name="token" inputmode="numeric" autocomplete="one-time-code"
             maxlength="32" placeholder="000000 or recovery code" spellcheck="false">
      <div class="lf-hint">Superadmin only — leave blank if not applicable.</div>
    </div>
    <?php endif ?>

    <?php if (setting('Auth.sessionConfig')['allowRemembering'] ?? false): ?>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;margin:10px 0 4px;cursor:pointer">
      <input type="checkbox" name="remember" <?= old('remember') ? 'checked' : '' ?>> Remember me
    </label>
    <?php endif ?>

    <button type="submit" class="lc-submit">Sign in</button>
  </form>

  <?php if (setting('Auth.allowMagicLinkLogins')): ?>
  <div class="lc-links">
    <a href="<?= url_to('magic-link') ?>">Forgot password?</a>
  </div>
  <?php endif ?>

</div>
</body>
</html>
