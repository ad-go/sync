<?php /** @var string $siteName */ /** @var string $account */ ?>
<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?><?= lang('Totp.verify.title') ?><?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3"><?= lang('Totp.verify.title') ?></h5>
            <p class="mb-2 text-muted" style="font-size:13px">
                <strong><?= esc($siteName) ?></strong> &bull; <?= esc($account) ?>
            </p>
            <p><?= lang('Totp.verify.intro') ?></p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('auth-action-verify') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <input type="text" class="form-control" name="token" placeholder="<?= esc(lang('Totp.verify.placeholder')) ?>"
                        inputmode="numeric" autocomplete="one-time-code" required autofocus>
                </div>
                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block"><?= lang('Totp.verify.confirm') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
