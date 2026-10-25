<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use OTPHP\TOTP;

/**
 * TOTP (authenticator app) second factor for superadmin users. Identities live in Shield's own
 * `auth_identities` table (type `totp_2fa` for the secret, `totp_recovery` for the recovery codes); the
 * `secret` column of both is encrypted with the application's own `encryption.key` (the same key that already
 * protects Shield's HMAC access tokens). Recovery codes are encrypted, not hashed, on purpose: unlike a
 * password, they must be re-displayable later (the "View 2FA" modal on the Users page), so a one-way hash
 * would not work here.
 */
class TwoFactor
{
    public const TYPE_SECRET   = 'totp_2fa';
    public const TYPE_RECOVERY = 'totp_recovery';

    private UserIdentityModel $identities;

    public function __construct()
    {
        $this->identities = model(UserIdentityModel::class);
    }

    /** The user's existing secret, or a freshly generated one — never regenerates one that already exists. */
    public function ensureSecret(User $user): string
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_SECRET);

        if ($row !== null) {
            return $this->decrypt($row->secret);
        }

        $secret = TOTP::create(secretSize: 20)->getSecret();

        $this->identities->insert([
            'user_id' => $user->id,
            'type'    => self::TYPE_SECRET,
            'name'    => 'totp',
            'secret'  => $this->encrypt($secret),
            'extra'   => 'unconfirmed',
        ]);

        return $secret;
    }

    public function isConfirmed(User $user): bool
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_SECRET);

        return $row !== null && $row->extra === 'confirmed';
    }

    public function confirm(User $user): void
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_SECRET);

        if ($row !== null) {
            $this->identities->update($row->id, ['extra' => 'confirmed']);
        }
    }

    public function hasSecret(User $user): bool
    {
        return $this->identities->getIdentityByType($user, self::TYPE_SECRET) !== null;
    }

    /** True TOTP codes only (6 digits, 1-step leeway for clock drift); recovery codes go through verifyRecoveryCode(). */
    public function verifyCode(User $user, string $code): bool
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_SECRET);

        if ($row === null || trim($code) === '') {
            return false;
        }

        $totp = TOTP::createFromSecret($this->decrypt($row->secret));

        if (! $totp->verify(trim($code), null, 1)) {
            return false;
        }

        $this->identities->update($row->id, ['last_used_at' => date('Y-m-d H:i:s')]);

        return true;
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel((string) ($user->email ?? $user->username));
        $totp->setIssuer(Branding::siteName());

        return $totp->getProvisioningUri();
    }

    /** Raw inline `<svg>...</svg>` markup, not a data: URI — embeds directly in a page or HTML email. */
    public function qrSvg(string $uri): string
    {
        $options                  = new QROptions();
        $options->outputInterface = QRMarkupSVG::class;
        $options->outputBase64    = false;
        $options->addQuietzone    = true;

        return (new QRCode($options))->render($uri);
    }

    /**
     * 10 fresh recovery codes, replacing any existing ones. Returns the plain codes — the only place that
     * generates them; `recoveryCodes()` returns the same codes (plus their used/unused state) afterwards.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];

        for ($i = 0; $i < 10; $i++) {
            $codes[] = bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(2));
        }

        $this->identities->deleteIdentitiesByType($user, self::TYPE_RECOVERY);
        $this->identities->insert([
            'user_id' => $user->id,
            'type'    => self::TYPE_RECOVERY,
            'name'    => 'totp-recovery',
            'secret'  => $this->encrypt(json_encode(array_map(static fn (string $c): array => ['code' => $c, 'used' => false], $codes))),
        ]);

        return $codes;
    }

    /** @return list<array{code:string,used:bool}> */
    public function recoveryCodes(User $user): array
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_RECOVERY);

        if ($row === null) {
            return [];
        }

        return json_decode($this->decrypt($row->secret), true) ?? [];
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $row  = $this->identities->getIdentityByType($user, self::TYPE_RECOVERY);
        $code = strtolower(trim($code));

        if ($row === null || $code === '') {
            return false;
        }

        $codes = json_decode($this->decrypt($row->secret), true) ?? [];
        $found = false;

        foreach ($codes as &$entry) {
            if (! $entry['used'] && hash_equals($entry['code'], $code)) {
                $entry['used'] = true;
                $found         = true;

                break;
            }
        }

        unset($entry);

        if ($found) {
            $this->identities->update($row->id, ['secret' => $this->encrypt(json_encode($codes))]);
        }

        return $found;
    }

    private function encrypt(string $plain): string
    {
        return base64_encode(service('encrypter')->encrypt($plain));
    }

    private function decrypt(string $encoded): string
    {
        return service('encrypter')->decrypt(base64_decode($encoded));
    }
}
