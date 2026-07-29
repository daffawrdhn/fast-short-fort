<div id="qrcode-modal" class="modal-overlay" role="dialog" aria-modal="true" aria-label="QR Code" style="display:none;">
  <div class="modal-content modal-sm">
    <div class="modal-header">
      <h3 class="modal-title">QR Code</h3>
      <button class="modal-close btn btn-icon" aria-label="Close QR code modal" onclick="closeQR()">&times;</button>
    </div>
    <div class="modal-body modal-body-centered">
      <div class="qr-display">
        <img id="qrcode-image" src="" alt="QR Code" class="qr-image">
      </div>
    </div>
    <div class="modal-footer">
      <div class="qr-download-actions">
        <a id="qrcode-download-png" href="#" class="btn btn-outline" download aria-label="Download QR code as PNG">Download PNG</a>
        <a id="qrcode-download-svg" href="#" class="btn btn-outline" download aria-label="Download QR code as SVG">Download SVG</a>
      </div>
      <button class="btn btn-ghost" onclick="closeQR()" aria-label="Close">Close</button>
    </div>
  </div>
</div>

<script>
function closeQR() {
  var modal = document.getElementById('qrcode-modal');
  if (modal) modal.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('qrcode-modal');
  if (modal) {
    modal.addEventListener('click', function(e) {
      if (e.target === modal) closeQR();
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && modal.style.display === 'flex') closeQR();
    });
  }
});
</script>
