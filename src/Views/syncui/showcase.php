<?php
/** @var Closure $asset */
use AdGo\Sync\Libraries\Icon;
$this->extend('AdGo\Sync\Views\syncui\_layout');
?>
<?= $this->section('pageHead') ?>
<link rel="stylesheet" href="<?= esc($asset('quill/2.0.3/quill.snow.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<h1><?= Icon::svg('image') ?> <?= lang('Sync.syncUi.showcase') ?></h1>
<p><?= lang('Sync.syncUi.showcaseIntro') ?></p>

<div class="widget-grid">

  <div class="card">
    <h2><?= lang('Sync.syncUi.wysiwyg') ?></h2>
    <div id="quillEditor" style="height:180px;background:var(--panel)"></div>
  </div>

  <div class="card">
    <h2><?= lang('Sync.syncUi.signature') ?></h2>
    <canvas id="sigPad" width="460" height="180" style="border:1px solid var(--border);border-radius:var(--radius);width:100%;height:180px;touch-action:none;background:#fff"></canvas>
    <p><button type="button" id="sigClear"><?= Icon::svg('x') ?> <?= lang('Sync.syncUi.clear') ?></button>
    <a class="btn ghost" id="sigDownload" download="signature.png"><?= Icon::svg('check') ?> <?= lang('Sync.syncUi.download') ?></a></p>
  </div>

  <div class="card">
    <h2><?= lang('Sync.syncUi.imageEditor') ?></h2>
    <input type="file" id="imgInput" accept="image/*">
    <div style="margin-top:8px">
      <cropper-canvas id="cropperCanvas" background style="display:block;width:100%;height:220px">
        <cropper-image id="cropperImage" alt="" rotatable scalable translatable></cropper-image>
        <cropper-shade hidden></cropper-shade>
        <cropper-handle action="select" plain></cropper-handle>
        <cropper-selection id="cropperSelection" initial-coverage="0.7" movable resizable>
          <cropper-grid role="grid" bordered covered></cropper-grid>
          <cropper-crosshair centered></cropper-crosshair>
          <cropper-handle action="move" theme-color="rgba(255,255,255,.35)"></cropper-handle>
          <cropper-handle action="n-resize"></cropper-handle>
          <cropper-handle action="e-resize"></cropper-handle>
          <cropper-handle action="s-resize"></cropper-handle>
          <cropper-handle action="w-resize"></cropper-handle>
          <cropper-handle action="ne-resize"></cropper-handle>
          <cropper-handle action="nw-resize"></cropper-handle>
          <cropper-handle action="se-resize"></cropper-handle>
          <cropper-handle action="sw-resize"></cropper-handle>
        </cropper-selection>
      </cropper-canvas>
      <p>
        <label><?= lang('Sync.syncUi.brightness') ?> <input type="range" id="imgBrightness" min="50" max="150" value="100" style="width:120px"></label>
        <label><?= lang('Sync.syncUi.contrast') ?> <input type="range" id="imgContrast" min="50" max="150" value="100" style="width:120px"></label>
      </p>
      <p>
        <button type="button" id="imgRotate"><?= Icon::svg('arrow-left') ?> <?= lang('Sync.syncUi.rotate') ?></button>
        <button type="button" id="imgExport"><?= Icon::svg('check') ?> <?= lang('Sync.syncUi.export') ?></button>
      </p>
      <img id="imgResult" alt="" style="max-width:100%;border-radius:var(--radius);display:none">
    </div>
  </div>

  <div class="card">
    <h2><?= lang('Sync.syncUi.pdfPreview') ?></h2>
    <input type="file" id="pdfInput" accept="application/pdf">
    <p id="pdfNav" style="display:none">
      <button type="button" id="pdfPrev"><?= Icon::svg('arrow-left') ?></button>
      <span id="pdfPageLabel"></span>
      <button type="button" id="pdfNext"><?= Icon::svg('arrow-left') ?> <span style="display:inline-block;transform:scaleX(-1)"><?= Icon::svg('arrow-left') ?></span></button>
    </p>
    <canvas id="pdfCanvas" style="max-width:100%;border:1px solid var(--border);border-radius:var(--radius)"></canvas>
  </div>

  <div class="card">
    <h2><?= lang('Sync.syncUi.scanner') ?></h2>
    <p>
      <button type="button" id="qrStart"><?= Icon::svg('scan-line') ?> <?= lang('Sync.syncUi.scanStart') ?></button>
      <button type="button" id="qrStop" disabled><?= Icon::svg('x') ?> <?= lang('Sync.syncUi.scanStop') ?></button>
    </p>
    <div id="qrReader" style="max-width:320px"></div>
    <input type="text" id="qrResult" readonly placeholder="<?= esc(lang('Sync.syncUi.scanResult')) ?>" style="margin-top:8px">
  </div>

  <div class="card">
    <h2><?= lang('Sync.syncUi.tts') ?></h2>
    <textarea id="ttsText" rows="3"><?= esc(lang('Sync.syncUi.ttsSample')) ?></textarea>
    <p>
      <select id="ttsVoice" style="width:auto;display:inline-block"></select>
      <button type="button" id="ttsSpeak"><?= Icon::svg('volume-2') ?> <?= lang('Sync.syncUi.ttsSpeak') ?></button>
      <button type="button" id="ttsStop"><?= Icon::svg('x') ?> <?= lang('Sync.syncUi.ttsStop') ?></button>
    </p>
  </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= esc($asset('quill/2.0.3/quill.js')) ?>"></script>
<script src="<?= esc($asset('signature_pad/5.1.4/signature_pad.umd.min.js')) ?>"></script>
<script src="<?= esc($asset('cropperjs/2.3.1/cropper.min.js')) ?>"></script>
<script src="<?= esc($asset('html5-qrcode/2.3.8/html5-qrcode.min.js')) ?>"></script>
<script type="module">
import * as pdfjsLib from '<?= esc($asset('pdfjs/6.4.299/pdf.min.mjs')) ?>';
pdfjsLib.GlobalWorkerOptions.workerSrc = '<?= esc($asset('pdfjs/6.4.299/pdf.worker.min.mjs')) ?>';
window.__syncUiPdfjs = pdfjsLib;
</script>
<script>
jQuery(function ($) {
  'use strict';

  // WYSIWYG — Quill
  if (window.Quill) { new Quill('#quillEditor', { theme: 'snow' }); }

  // Mouse/touch signature
  var sigCanvas = document.getElementById('sigPad');
  if (sigCanvas && window.SignaturePad) {
    var pad = new SignaturePad(sigCanvas);
    document.getElementById('sigClear').addEventListener('click', function () { pad.clear(); });
    document.getElementById('sigDownload').addEventListener('click', function (e) {
      if (pad.isEmpty()) { e.preventDefault(); return; }
      this.href = pad.toDataURL('image/png');
    });
  }

  // Image editor — Cropper.js v2 (web components) + brightness/contrast preview
  var cropperImage = document.getElementById('cropperImage');
  var cropperSelection = document.getElementById('cropperSelection');
  document.getElementById('imgInput').addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (!file) { return; }
    var reader = new FileReader();
    reader.onload = function () { cropperImage.src = reader.result; };
    reader.readAsDataURL(file);
  });
  var applyFilter = function () {
    var b = document.getElementById('imgBrightness').value;
    var c = document.getElementById('imgContrast').value;
    cropperImage.style.filter = 'brightness(' + (b / 100) + ') contrast(' + (c / 100) + ')';
  };
  document.getElementById('imgBrightness').addEventListener('input', applyFilter);
  document.getElementById('imgContrast').addEventListener('input', applyFilter);
  document.getElementById('imgRotate').addEventListener('click', function () { cropperImage.$rotate('90deg'); });
  document.getElementById('imgExport').addEventListener('click', function () {
    var b = document.getElementById('imgBrightness').value / 100;
    var c = document.getElementById('imgContrast').value / 100;
    cropperSelection.$toCanvas({
      beforeDraw: function (context) { context.filter = 'brightness(' + b + ') contrast(' + c + ')'; },
    }).then(function (canvas) {
      var img = document.getElementById('imgResult');
      img.src = canvas.toDataURL('image/png');
      img.style.display = 'block';
    });
  });

  // PDF preview
  var pdfState = { doc: null, page: 1 };
  var renderPdfPage = function () {
    pdfState.doc.getPage(pdfState.page).then(function (page) {
      var canvas = document.getElementById('pdfCanvas');
      var viewport = page.getViewport({ scale: 1.1 });
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport });
      document.getElementById('pdfPageLabel').textContent = pdfState.page + ' / ' + pdfState.doc.numPages;
    });
  };
  document.getElementById('pdfInput').addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    if (!file || !window.__syncUiPdfjs) { return; }
    file.arrayBuffer().then(function (buf) {
      return window.__syncUiPdfjs.getDocument({ data: buf }).promise;
    }).then(function (doc) {
      pdfState.doc = doc;
      pdfState.page = 1;
      document.getElementById('pdfNav').style.display = '';
      renderPdfPage();
    });
  });
  document.getElementById('pdfPrev').addEventListener('click', function () {
    if (pdfState.doc && pdfState.page > 1) { pdfState.page--; renderPdfPage(); }
  });
  document.getElementById('pdfNext').addEventListener('click', function () {
    if (pdfState.doc && pdfState.page < pdfState.doc.numPages) { pdfState.page++; renderPdfPage(); }
  });

  // Barcode / QR scan via camera
  var qrCode = null;
  document.getElementById('qrStart').addEventListener('click', function () {
    if (!window.Html5Qrcode) { return; }
    qrCode = new Html5Qrcode('qrReader');
    var self = this;
    qrCode.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, function (decodedText) {
      document.getElementById('qrResult').value = decodedText;
      Toastify({ text: decodedText, duration: 4000, gravity: 'top', position: 'right' }).showToast();
    }, function () {}).then(function () {
      self.disabled = true;
      document.getElementById('qrStop').disabled = false;
    }).catch(function (err) {
      Toastify({ text: String(err), duration: 5000, gravity: 'top', position: 'right', style: { background: '#b42318' } }).showToast();
    });
  });
  document.getElementById('qrStop').addEventListener('click', function () {
    if (qrCode) {
      qrCode.stop().then(function () {
        document.getElementById('qrStart').disabled = false;
        document.getElementById('qrStop').disabled = true;
      });
    }
  });

  // Text-to-speech — native Web Speech API, nothing vendored
  var voiceSelect = document.getElementById('ttsVoice');
  var loadVoices = function () {
    voiceSelect.innerHTML = '';
    window.speechSynthesis.getVoices().forEach(function (v, i) {
      var opt = document.createElement('option');
      opt.value = i;
      opt.textContent = v.name + ' (' + v.lang + ')';
      voiceSelect.appendChild(opt);
    });
  };
  if ('speechSynthesis' in window) {
    loadVoices();
    window.speechSynthesis.onvoiceschanged = loadVoices;
    document.getElementById('ttsSpeak').addEventListener('click', function () {
      var text = document.getElementById('ttsText').value;
      if (!text) { return; }
      var utter = new SpeechSynthesisUtterance(text);
      var voices = window.speechSynthesis.getVoices();
      if (voices[voiceSelect.value]) { utter.voice = voices[voiceSelect.value]; }
      window.speechSynthesis.cancel();
      window.speechSynthesis.speak(utter);
    });
    document.getElementById('ttsStop').addEventListener('click', function () { window.speechSynthesis.cancel(); });
  }
});
</script>
<?= $this->endSection() ?>
