<?php
/**
 * One card's fields: the env variables of $items (EnvSettings::FIELDS shape) in a grid.
 * Each input is as wide as the longest value its validation accepts (EnvSettings::maxChars), so short fields
 * stay short. The label carries an icon chosen from the key.
 * @var array<string, array<string, mixed>> $items
 * @var callable $fspan grid span class for a field
 * @var string $gridClass grid class of the enclosing layout
 */
use AdGo\Sync\Libraries\EnvSettings;

/** Bootstrap Icons per setting: by exact key first, then by key prefix, then by type. */
$iconFor = static function (string $key, string $type): string {
    $byKey = [
        'sync.telegram.token'          => 'bi-telegram',
        'sync.telegram.chat_id'        => 'bi-chat-dots',
        'sync.telegram.webhook_secret' => 'bi-shield-lock',
        'sync.telegram.admin_url'      => 'bi-link-45deg',
        'sync.peerUrl'                 => 'bi-link-45deg',
        'sync.files.dir'               => 'bi-folder2',
        'sync.mail.to'                 => 'bi-envelope-at',
        'sync.mail.from'               => 'bi-envelope',
        'sync.mail.host'               => 'bi-hdd-network',
        'sync.mail.port'               => 'bi-ethernet',
        'sync.mail.crypto'             => 'bi-lock',
        'sync.mail.user'               => 'bi-person',
        'sync.mail.pass'               => 'bi-key',
        'app.baseURL'                  => 'bi-globe',
        'app.tickToken'                => 'bi-key',
    ];
    if (isset($byKey[$key])) {
        return $byKey[$key];
    }

    return match (true) {
        str_starts_with($key, 'database.') => 'bi-database',
        str_starts_with($key, 'sync.')     => 'bi-sliders',
        str_starts_with($key, 'app.')      => 'bi-gear',
        $type === 'secret'                 => 'bi-key',
        $type === 'bool'                   => 'bi-toggle-on',
        $type === 'choice'                 => 'bi-list-ul',
        $type === 'int'                    => 'bi-123',
        default                            => 'bi-pencil',
    };
};
?>
<div class="<?= esc($gridClass ?? 'fgrid4') ?>">
<?php foreach ($items as $key => $f):
    $id    = str_replace('.', '__', $key);
    $span  = $fspan($f, $key);
    $icon  = $iconFor($key, (string) $f['type']);
    $chars = EnvSettings::maxChars($f);
    $size  = 'width:' . min($chars, 40) . 'ch;max-width:100%';
?>
  <div class="fg <?= $span ?><?= $key === 'sync.files.dir' ? ' with-browse' : '' ?>">
    <label title="<?= esc($key) ?>"><i class="bi <?= $icon ?>"></i> <?= esc($f['label']) ?></label>
    <?php if ($f['type'] === 'bool'): ?>
      <select name="<?= $id ?>" style="width:12ch">
        <option value="true"  <?= $f['value'] === 'true'  ? 'selected' : '' ?>>true</option>
        <option value="false" <?= $f['value'] === 'false' ? 'selected' : '' ?>>false</option>
      </select>
    <?php elseif ($f['type'] === 'choice'): ?>
      <select name="<?= $id ?>" style="width:<?= max(16, min(24, (int) max(array_map('strlen', $f['options'] ?: [''])))+ 4) ?>ch;max-width:100%">
        <?php foreach ($f['options'] as $o): ?>
        <option value="<?= esc($o) ?>" <?= $f['value'] === $o ? 'selected' : '' ?>><?= ($o === '' ? '(none)' : esc($o)) ?></option>
        <?php endforeach ?>
      </select>
    <?php elseif ($f['type'] === 'secret'): ?>
      <input type="password" name="<?= $id ?>" style="<?= $size ?>" maxlength="<?= $chars ?>"
             placeholder="<?= $f['set'] ? 'set — leave blank to keep' : 'not set' ?>"
             autocomplete="off">
    <?php elseif ($f['type'] === 'int'): ?>
      <input type="number" name="<?= $id ?>" style="width:<?= $chars + 4 ?>ch;max-width:100%"
             value="<?= esc($f['value']) ?>"
             min="<?= (int) ($f['min'] ?? 0) ?>" max="<?= (int) ($f['max'] ?? 9999) ?>">
    <?php else: ?>
      <input type="text" name="<?= $id ?>" value="<?= esc($f['value']) ?>" style="<?= $f['type'] === 'path' ? 'width:70%' : $size ?>" maxlength="<?= $chars ?>"
             <?= in_array($f['type'], ['url', 'baseurl'], true) ? 'inputmode="url"' : '' ?>>
    <?php endif ?>
    <?php if ($key === 'sync.files.dir'): ?>
      <button type="button" class="btn-sm btn-outline gen-btn" id="browseDir" title="Pick a folder inside the synchronised directory" aria-label="Pick a folder"><i class="bi bi-folder2-open"></i></button>
      <div id="dirModal" class="dir-modal" hidden>
        <div class="dir-modal-box" role="dialog" aria-modal="true" aria-labelledby="dirModalTitle">
          <div class="dir-modal-head">
            <strong id="dirModalTitle"><i class="bi bi-folder2-open"></i> Pick the synchronised folder</strong>
            <button type="button" class="gen-btn btn-outline" id="dirClose" aria-label="Close"><i class="bi bi-x-lg"></i></button>
          </div>
          <div id="dirTree"></div>
          <div class="dir-modal-foot">
            <span class="hint" id="dirPicked">Select a folder in the tree.</span>
            <button type="button" class="btn-sm btn-outline" id="dirCancel">Cancel</button>
            <button type="button" class="btn-sm" id="dirUse" disabled>Use this folder</button>
          </div>
        </div>
      </div>
    <?php endif ?>
    <?php if ($f['hint'] !== ''): ?><span class="hint"><?= esc($f['hint']) ?></span><?php endif ?>
  </div>
<?php endforeach ?>
</div>
