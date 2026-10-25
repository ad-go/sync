<?= $this->extend(config('Auth')->views['layout']) ?>

<?= $this->section('title') ?>Enter your authenticator code<?= $this->endSection() ?>

<?= $this->section('main') ?>

<div class="container d-flex justify-content-center p-5">
    <div class="card col-12 col-md-5 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Enter your authenticator code</h5>
            <p>Open your authenticator app and enter the 6-digit code, or one of your recovery codes.</p>

            <?php if (session('error') !== null) : ?>
                <div class="alert alert-danger"><?= esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?= url_to('auth-action-verify') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <input type="text" class="form-control" name="token" placeholder="000000 or recovery code"
                        inputmode="numeric" autocomplete="one-time-code" required autofocus>
                </div>
                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-primary btn-block">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
