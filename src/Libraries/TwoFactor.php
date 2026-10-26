<?php

declare(strict_types=1);

namespace AdGo\Sync\Libraries;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use CodeIgniter\Encryption\EncrypterInterface as Encrypter;
use CodeIgniter\Encryption\Exceptions\EncryptionException;
use CodeIgniter\Shield\Entities\User;
use Config\Encryption as EncryptionConfig;
use CodeIgniter\Shield\Models\UserIdentityModel;
use OTPHP\TOTP;

/**
 * TOTP (authenticator app) second factor for superadmin users. Identities live in Shield's own
 * `auth_identities` table: type `totp_2fa` keeps the encrypted secret in `secret`, type `totp_recovery` keeps the
 * encrypted recovery list in `extra` (TEXT; `secret` is VARCHAR(255) and only holds a random handle there). Recovery
 * codes are encrypted, not hashed, on purpose: unlike a password, they must be re-displayable later (the
 * "View 2FA" modal on the Users page), so a one-way hash would not work here.
 *
 * The `secret` column of both is encrypted with a key derived from `sync.secret`, NOT with the application's
 * `encryption.key`: `auth_identities` is synchronised between the nodes, and each node has its own
 * `encryption.key`, so a blob written on one node would be unreadable on the others (login there would fail
 * with an EncryptionException). `sync.secret` is by design identical on every node. Blobs written by earlier
 * versions with the application key are still read (fallback) and re-encrypted with the shared key on first use.
 */
class TwoFactor
{
    public const TYPE_SECRET   = 'totp_2fa';
    public const TYPE_RECOVERY = 'totp_recovery';

    /** Domain separation: the derived key is used for nothing else. */
    private const KEY_INFO = 'ad-go/sync totp identities';

    private UserIdentityModel $identities;
    private ?Encrypter $shared = null;

    public function __construct()
    {
        $this->identities = model(UserIdentityModel::class);
    }

    /** The user's existing secret, or a freshly generated one — never regenerates one that already exists. */
    public function ensureSecret(User $user): string
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_SECRET);

        if ($row !== null) {
            return $this->read($row);
        }

        $secret = TOTP::create(secretSize: 20)->getSecret();
        $blob   = $this->encrypt($secret);

        if (strlen($blob) > 255) {
            throw new EncryptionException('Encrypted 2FA secret does not fit auth_identities.secret (255).'); // never with a 20-byte secret
        }

        $this->identities->insert([
            'user_id' => $user->id,
            'type'    => self::TYPE_SECRET,
            'name'    => 'totp',
            'secret'  => $blob,
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

        $totp = TOTP::createFromSecret($this->read($row));

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
            // `secret` is VARCHAR(255) and part of a unique key: it only carries a random handle here. The encrypted
            // list (about 600 characters) lives in `extra` (TEXT); MySQL would silently truncate it in `secret`.
            'secret'  => 'recovery:' . bin2hex(random_bytes(16)),
            'extra'   => $this->encrypt(json_encode(array_map(static fn (string $c): array => ['code' => $c, 'used' => false], $codes))),
        ]);

        return $codes;
    }

    /** @return list<array{code:string,used:bool}> */
    public function recoveryCodes(User $user): array
    {
        $row = $this->identities->getIdentityByType($user, self::TYPE_RECOVERY);

        return $row === null ? [] : $this->readRecovery($row);
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $row  = $this->identities->getIdentityByType($user, self::TYPE_RECOVERY);
        $code = strtolower(trim($code));

        if ($row === null || $code === '') {
            return false;
        }

        $codes = $this->readRecovery($row);
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
            $this->identities->update($row->id, ['extra' => $this->encrypt(json_encode($codes))]);
        }

        return $found;
    }

    /**
     * The recovery list of a `totp_recovery` row. Rows written by earlier versions kept the blob in `secret`, where
     * MySQL truncated it to 255 characters: such a row (or any other that no key can open) is unusable and is removed
     * so that the next setup page issues a fresh list, instead of failing every login with an exception.
     *
     * @param object{id: int, secret: string, extra: ?string} $row
     *
     * @return list<array{code:string,used:bool}>
     */
    private function readRecovery(object $row): array
    {
        $legacy = ! str_starts_with((string) $row->secret, 'recovery:');

        try {
            $plain = $this->decryptBlob((string) ($legacy ? $row->secret : $row->extra), $migrated);
        } catch (EncryptionException $e) {
            log_message('warning', "sync: 2FA recovery codes of user {$row->user_id} are unreadable ({$e->getMessage()}); removed, a new list will be issued");
            $this->identities->delete($row->id);

            return [];
        }

        if ($legacy || $migrated) {
            $this->identities->update($row->id, [
                'secret' => $legacy ? 'recovery:' . bin2hex(random_bytes(16)) : $row->secret,
                'extra'  => $this->encrypt($plain),
            ]);
        }

        $codes = json_decode($plain, true);

        return is_array($codes) ? $codes : [];
    }

    private function encrypt(string $plain): string
    {
        return base64_encode($this->encrypter()->encrypt($plain));
    }

    /**
     * Decrypts an identity's `secret`. A blob that only the application's own `encryption.key` can open was
     * written by an earlier version on this node: it is re-encrypted with the shared key so the peers can read
     * it too once it has synchronised.
     *
     * @param object{id: int, secret: string} $row
     */
    private function read(object $row): string
    {
        $plain = $this->decryptBlob((string) $row->secret, $migrated);

        if ($migrated) {
            $this->identities->update($row->id, ['secret' => $this->encrypt($plain)]);
        }

        return $plain;
    }

    /**
     * Opens a stored blob with the shared key, or — for one written by an earlier version — with the application's
     * own key, in which case `$migrated` is set so the caller re-encrypts it with the shared key.
     *
     * @throws EncryptionException when neither key opens it
     */
    private function decryptBlob(string $encoded, ?bool &$migrated): string
    {
        $migrated = false;
        $blob     = base64_decode($encoded, true);

        if ($blob === false || $blob === '') {
            throw new EncryptionException('Corrupt 2FA identity.');
        }

        try {
            return $this->encrypter()->decrypt($blob);
        } catch (EncryptionException $shared) {
            try {
                $plain = service('encrypter')->decrypt($blob);
            } catch (EncryptionException) {
                throw $shared;
            }
        }

        $migrated = true;

        return $plain;
    }

    /** OpenSSL encrypter keyed from `sync.secret` (HKDF-style derivation, 32 bytes), the same on every node. */
    private function encrypter(): Encrypter
    {
        if ($this->shared === null) {
            $secret = Kernel::instance()->opt->secret;

            if ($secret === '') {
                throw new EncryptionException('sync.secret is not configured; 2FA identities cannot be protected.');
            }

            $config         = clone config(EncryptionConfig::class);
            $config->driver = 'OpenSSL';
            $config->key    = hash_hkdf('sha256', $secret, 32, self::KEY_INFO);

            $this->shared = service('encrypter', $config, false);
        }

        return $this->shared;
    }
}
