<?php
/** @var string $secret */
/** @var string $qr */
/** @var list<array{code:string,used:bool}> $codes */
/** @var string $siteName */
/** @var string $account */
?>
<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Totp.setup.title') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-7 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= lang('Totp.setup.heading') ?></h5>
            <p class="mb-2 text-muted" style="font-size:13px">
                <strong><?= esc($siteName) ?></strong> &bull; <?= esc($account) ?>
            </p>
            <p><?= lang('Totp.setup.intro') ?></p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <div class="text-center my-3" style="max-width:220px;margin-inline:auto"><?= $qr ?></div>

            <p class="text-center"><small><?= lang('Totp.setup.cant_scan') ?><br>
                <code style="word-break:break-all"><?= esc($secret) ?></code></small></p>

            <form action="<?= url_to('auth-action-verify') ?>" method="post" class="mt-4">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <input type="text" class="form-control" name="token" placeholder="<?= esc(lang('Totp.setup.placeholder')) ?>"
                        autocomplete="one-time-code" required autofocus>
                </div>
                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Totp.setup.confirm') ?></button>
                </div>
            </form>

            <?php if ($codes !== []) : ?>
            <hr>
            <h6><?= lang('Totp.setup.codes_title') ?></h6>
            <p><small><?= lang('Totp.setup.codes_text', [lang('Users.twofa.tooltip')]) ?></small></p>
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
