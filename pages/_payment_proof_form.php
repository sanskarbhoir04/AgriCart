<?php
// Included twice (QR panel + UPI panel) inside pages/payment.php.
// Uses classes, not ids, so it's safe to repeat in the same page.
?>
<form class="proof-form" onsubmit="return false;">
    <?= csrf_field() ?>
    <label class="proof-label">Transaction / UTR reference number</label>
    <input type="text" class="proof-txn" name="transaction_id" maxlength="100" placeholder="e.g. 402812345678" autocomplete="off">
    <label class="proof-label">Payment screenshot (optional, JPG/PNG/WEBP, max 3MB)</label>
    <input type="file" class="proof-file" name="screenshot" accept="image/jpeg,image/png,image/webp">
    <div class="proof-error" style="display:none;"></div>
    <button type="button" class="btn-success" onclick="submitPaymentProof(event)">Submit Payment Details</button>
</form>
