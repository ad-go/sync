<?php
/** @var string $secret */
/** @var string $qr */
/** @var list<array{code:string,used:bool}> $codes */
/** @var string $siteName */
/** @var string $account */
?>
<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Set up your authenticator<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-7 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Set up your authenticator app</h5>
            <p class="mb-2 text-muted" style="font-size:13px">
                <strong><?= esc($siteName) ?></strong> &bull; <?= esc($account) ?>
            </p>
            <p>Your account is in the <b>superadmin</b> group, which requires a second factor. Scan this code
                with Google Authenticator, Authy, or any other TOTP app, then enter the 6-digit code it shows.</p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <div class="text-center my-3" style="max-width:220px;margin-inline:auto"><?= $qr ?></div>

            <p class="text-center"><small>Can't scan it? Enter this key manually:<br>
                <code style="word-break:break-all"><?= esc($secret) ?></code></small></p>

            <form action="<?= url_to('auth-action-verify') ?>" method="post" class="mt-4">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <input type="text" class="form-control" name="token" placeholder="000000 or recovery code"
                        autocomplete="one-time-code" required autofocus>
                </div>
                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block">Confirm</button>
                </div>
            </form>

            <?php if ($codes !== []) : ?>
            <hr>
            <h6>Recovery codes</h6>
            <p><small>Save these somewhere safe — each works once, if you ever lose access to your authenticator
                app. You can see them again later from the Users page ("View 2FA").</small></p>
            <div class="row row-cols-2 g-1" style="font-family:ui-monospace,Consolas,monospace;font-size:13px">
                <?php foreach ($codes as $c): ?>
                <div class="col"><?= esc($c['code']) ?></div>
                <?php endforeach ?>
            </div>
            <?php endif ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
