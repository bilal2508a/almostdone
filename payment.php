<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = requireLogin();

$bookingId = (int)($_GET['id'] ?? 0);
if (!$bookingId) {
    flash('error', 'Invalid booking.');
    redirect('/bookings.php');
}

// Fetch booking, verify ownership
$stmt = db()->prepare('SELECT b.*, p.title as property_title, p.city as property_city, p.area as property_area, p.address as property_address, (SELECT image_path FROM property_images WHERE property_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) as primary_image FROM bookings b JOIN properties p ON b.property_id = p.id WHERE b.id = ? AND b.tenant_id = ?');
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('error', 'Booking not found.');
    redirect('/bookings.php');
}

if ($booking['payment_status'] === 'paid') {
    flash('error', 'This booking is already paid.');
    redirect('/bookings.php');
}

// Coupon codes
$coupons = [
    'EARLY20' => 20,
    'STAY7' => 15,
    'FAMILY4' => 10,
    'WELCOME10' => 10,
];

$appliedCoupon = $_GET['coupon'] ?? '';
$discount = 0;
$discountAmount = 0;
$totalPrice = (float)$booking['total_amount'];
if ($appliedCoupon && isset($coupons[$appliedCoupon])) {
    $discount = $coupons[$appliedCoupon];
    $discountAmount = ($totalPrice * $discount) / 100;
    $totalPrice = $totalPrice - $discountAmount;
}

include __DIR__ . '/includes/header.php';
?>

<div style="background:linear-gradient(135deg,#0ea5e9,#14b8a6);padding:2.5rem 0;color:#fff;">
    <div class="container-app">
        <h1 style="font-size:2rem;font-weight:800;margin:0;letter-spacing:-0.02em;">Payment</h1>
        <p style="margin:0.5rem 0 0;opacity:0.95;">Complete your booking payment securely</p>
    </div>
</div>

<section style="padding:2.5rem 0;">
    <div class="container-app">
        <div class="row g-4">
            <!-- Booking Summary -->
            <div class="col-lg-5">
                <div class="card" style="border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);overflow:hidden;position:sticky;top:90px;">
                    <?php if (!empty($booking['primary_image'])): ?>
                        <img src="<?php echo e(image_url($booking['primary_image'])); ?>" alt="" style="width:100%;height:200px;object-fit:cover;">
                    <?php else: ?>
                        <div style="width:100%;height:200px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:3rem;"><i class="bi bi-house"></i></div>
                    <?php endif; ?>
                    <div style="padding:1.5rem;">
                        <h4 style="color:#0f172a;font-weight:700;margin:0 0 0.5rem;"><?php echo e($booking['property_title']); ?></h4>
                        <p style="color:#64748b;font-size:0.9rem;margin:0 0 1rem;"><i class="bi bi-geo-alt"></i> <?php echo e($booking['property_city'] . ', ' . $booking['property_area']); ?></p>
                        <?php if (!empty($booking['booking_for']) && $booking['booking_for'] === 'other' && !empty($booking['guest_name'])): ?>
                        <div style="margin-bottom:0.75rem;">
                            <span style="display:inline-flex;align-items:center;gap:5px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:8px;padding:5px 11px;font-size:0.82rem;font-weight:600;">
                                <i class="bi bi-person-badge"></i> Booked for: <?php echo e($booking['guest_name']); ?><?php echo !empty($booking['guest_phone']) ? ' &middot; ' . e($booking['guest_phone']) : ''; ?>
                            </span>
                        </div>
                        <?php endif; ?>

                        <div style="background:#f8fafc;border-radius:12px;padding:1rem;margin-bottom:1rem;">
                            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;">
                                <span style="color:#64748b;"><i class="bi bi-calendar"></i> Check-in</span>
                                <strong style="color:#0f172a;"><?php echo formatDate($booking['start_date']); ?></strong>
                            </div>
                            <div style="display:flex;justify-content:space-between;padding:0.4rem 0;">
                                <span style="color:#64748b;"><i class="bi bi-calendar-check"></i> Check-out</span>
                                <strong style="color:#0f172a;"><?php echo formatDate($booking['end_date']); ?></strong>
                            </div>
                        </div>

                        <hr style="border-color:#e2e8f0;margin:1rem 0;">

                        <div style="display:flex;justify-content:space-between;padding:0.4rem 0;">
                            <span style="color:#64748b;">Booking Amount</span>
                            <strong style="color:#0f172a;"><?php echo format_price($booking['total_amount']); ?></strong>
                        </div>
                        <?php if ($discount > 0): ?>
                        <div style="display:flex;justify-content:space-between;padding:0.4rem 0;">
                            <span style="color:#10b981;"><i class="bi bi-tag"></i> Discount (<?php echo e($appliedCoupon); ?> -<?php echo $discount; ?>%)</span>
                            <strong style="color:#10b981;">- <?php echo format_price($discountAmount); ?></strong>
                        </div>
                        <?php endif; ?>
                        <hr style="border-color:#e2e8f0;margin:0.75rem 0;">
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span style="color:#0f172a;font-weight:700;font-size:1.1rem;">Total</span>
                            <strong style="color:#0f172a;font-size:1.5rem;font-weight:800;"><?php echo format_price($totalPrice); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <div class="col-lg-7">
                <form method="POST" action="<?php echo url('/api/process-payment.php'); ?>" id="paymentForm">
                    <input type="hidden" name="booking_id" value="<?php echo (int)$booking['id']; ?>">
                    <input type="hidden" name="total_price" value="<?php echo $totalPrice; ?>">
                    <input type="hidden" name="coupon_code" value="<?php echo e($appliedCoupon); ?>">

                    <!-- Coupon -->
                    <div class="card" style="border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);padding:1.5rem;margin-bottom:1.5rem;">
                        <h5 style="color:#0f172a;font-weight:700;margin:0 0 1rem;"><i class="bi bi-ticket-perforated" style="color:#14b8a6;"></i> Apply Coupon</h5>
                        <div style="display:flex;gap:0.5rem;">
                            <input type="text" id="couponInput" value="<?php echo e($appliedCoupon); ?>" placeholder="Enter coupon code" class="form-control" style="border-radius:10px;flex:1;">
                            <a href="#" onclick="applyCoupon(event)" class="btn btn-ghost" style="border-radius:10px;"><i class="bi bi-check-lg"></i> Apply</a>
                        </div>
                        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.75rem;">
                            <?php foreach ($coupons as $code => $pct): ?>
                                <a href="<?php echo url('/payment.php?id=' . (int)$booking['id'] . '&coupon=' . $code); ?>" class="badge badge-info" style="text-decoration:none;padding:0.4rem 0.75rem;cursor:pointer;"><?php echo e($code); ?> (<?php echo $pct; ?>% off)</a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="card" style="border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);padding:1.5rem;margin-bottom:1.5rem;">
                        <h5 style="color:#0f172a;font-weight:700;margin:0 0 1rem;"><i class="bi bi-credit-card" style="color:#0ea5e9;"></i> Payment Method</h5>
                        <div style="display:flex;flex-direction:column;gap:0.75rem;">
                            <label class="payment-option" data-method="card" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                <input type="radio" name="payment_method" value="card" checked style="accent-color:#0ea5e9;">
                                <div style="width:40px;height:40px;border-radius:10px;background:#0ea5e9;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                                    <i class="bi bi-credit-card-2-front"></i>
                                </div>
                                <div>
                                    <strong style="color:#0f172a;">Credit / Debit Card</strong>
                                    <div style="color:#64748b;font-size:0.85rem;">Visa, Mastercard, UnionPay</div>
                                </div>
                            </label>
                            <label class="payment-option" data-method="wallet" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                <input type="radio" name="payment_method" value="wallet" style="accent-color:#0ea5e9;">
                                <div style="width:40px;height:40px;border-radius:10px;background:#14b8a6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                                <div>
                                    <strong style="color:#0f172a;">Mobile Wallet</strong>
                                    <div style="color:#64748b;font-size:0.85rem;">JazzCash, EasyPaisa, SadaPay</div>
                                </div>
                            </label>
                            <label class="payment-option" data-method="bank" style="display:flex;align-items:center;gap:0.75rem;padding:1rem;border:2px solid #e2e8f0;border-radius:12px;cursor:pointer;transition:all 0.2s;">
                                <input type="radio" name="payment_method" value="bank" style="accent-color:#0ea5e9;">
                                <div style="width:40px;height:40px;border-radius:10px;background:#64748b;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.25rem;">
                                    <i class="bi bi-bank"></i>
                                </div>
                                <div>
                                    <strong style="color:#0f172a;">Bank Transfer</strong>
                                    <div style="color:#64748b;font-size:0.85rem;">Direct bank deposit</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Card Details (shown for card method) -->
                    <div class="card payment-details" id="cardDetails" style="border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);padding:1.5rem;margin-bottom:1.5rem;">
                        <h5 style="color:#0f172a;font-weight:700;margin:0 0 1rem;"><i class="bi bi-credit-card-fill" style="color:#0ea5e9;"></i> Card Details</h5>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Card Number</label>
                            <input type="text" name="card_number" id="cardNumber" placeholder="1234 5678 9012 3456" class="form-control" style="border-radius:10px;" maxlength="19" inputmode="numeric" pattern="[0-9 ]{19}">
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Expiry</label>
                                <input type="text" name="card_expiry" id="cardExpiry" placeholder="MM/YY" class="form-control" style="border-radius:10px;" maxlength="5" inputmode="numeric" pattern="(0[1-9]|1[0-2])/[0-9]{2}">
                            </div>
                            <div class="col-6">
                                <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">CVV</label>
                                <input type="text" name="card_cvv" id="cardCvv" placeholder="123" class="form-control" style="border-radius:10px;" maxlength="4" inputmode="numeric" pattern="[0-9]{3,4}">
                            </div>
                        </div>
                        <small style="color:#64748b;display:block;margin-top:0.75rem;"><i class="bi bi-shield-check"></i> Your payment information is encrypted and secure.</small>
                    </div>

                    <!-- Wallet Details (shown for wallet method) -->
                    <div class="card payment-details" id="walletDetails" style="display:none;border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);padding:1.5rem;margin-bottom:1.5rem;">
                        <h5 style="color:#0f172a;font-weight:700;margin:0 0 1rem;"><i class="bi bi-wallet-fill" style="color:#14b8a6;"></i> Mobile Wallet Details</h5>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Wallet Provider</label>
                            <select name="wallet_provider" class="form-control" style="border-radius:10px;">
                                <option value="">Select provider</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">EasyPaisa</option>
                                <option value="sadapay">SadaPay</option>
                                <option value="nayapay">NayaPay</option>
                            </select>
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Mobile Number</label>
                            <input type="tel" name="wallet_number" id="walletNumber" placeholder="03XXXXXXXXX" class="form-control" style="border-radius:10px;" maxlength="11" inputmode="numeric" pattern="03[0-9]{9}">
                        </div>
                        <div>
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">CNIC (last 6 digits)</label>
                            <input type="text" name="wallet_cnic" id="walletCnic" placeholder="XXXXXX" class="form-control" style="border-radius:10px;" maxlength="6" inputmode="numeric" pattern="[0-9]{6}">
                        </div>
                        <small style="color:#64748b;display:block;margin-top:0.75rem;"><i class="bi bi-shield-check"></i> You will receive a confirmation SMS on this number.</small>
                    </div>

                    <!-- Bank Transfer Details (shown for bank method) -->
                    <div class="card payment-details" id="bankDetails" style="display:none;border:none;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.06);padding:1.5rem;margin-bottom:1.5rem;">
                        <h5 style="color:#0f172a;font-weight:700;margin:0 0 1rem;"><i class="bi bi-bank2" style="color:#64748b;"></i> Bank Transfer Details</h5>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Select Bank</label>
                            <select name="bank_name" class="form-control" style="border-radius:10px;">
                                <option value="">Select bank</option>
                                <option value="hbl">Habib Bank Limited (HBL)</option>
                                <option value="ubl">United Bank Limited (UBL)</option>
                                <option value="mcb">MCB Bank</option>
                                <option value="meezan">Meezan Bank</option>
                                <option value="alfalah">Bank Alfalah</option>
                                <option value="standard">Standard Chartered</option>
                            </select>
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Account Title</label>
                            <input type="text" name="bank_account_title" placeholder="Account holder name" class="form-control" style="border-radius:10px;" maxlength="50">
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Account Number</label>
                            <input type="text" name="bank_account_number" id="bankAccount" placeholder="Account number" class="form-control" style="border-radius:10px;" maxlength="20" inputmode="numeric" pattern="[0-9]{8,20}">
                        </div>
                        <div>
                            <label style="font-size:0.85rem;font-weight:600;color:#64748b;margin-bottom:0.25rem;display:block;">Transaction Reference / Slip Number</label>
                            <input type="text" name="bank_reference" id="bankRef" placeholder="Transfer reference number" class="form-control" style="border-radius:10px;" maxlength="15" inputmode="numeric" pattern="[A-Za-z0-9]{5,15}">
                        </div>
                        <small style="color:#64748b;display:block;margin-top:0.75rem;"><i class="bi bi-info-circle"></i> Upload your deposit slip in the next step for verification.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="border-radius:12px;padding:1rem;font-size:1.1rem;font-weight:700;">
                        <i class="bi bi-lock-fill"></i> Pay <?php echo format_price($totalPrice); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
function applyCoupon(e) {
    e.preventDefault();
    var code = document.getElementById('couponInput').value.trim().toUpperCase();
    if (!code) return;
    window.location.href = '<?php echo url("/payment.php?id=" . (int)$booking['id']); ?>&coupon=' + encodeURIComponent(code);
}
var detailSections = {
    card: document.getElementById('cardDetails'),
    wallet: document.getElementById('walletDetails'),
    bank: document.getElementById('bankDetails')
};
var optionLabels = document.querySelectorAll('.payment-option');

function highlightOption(method) {
    optionLabels.forEach(function(label) {
        if (label.dataset.method === method) {
            label.style.borderColor = '#0ea5e9';
            label.style.background = '#f0f9ff';
        } else {
            label.style.borderColor = '#e2e8f0';
            label.style.background = 'transparent';
        }
    });
}

function showDetails(method) {
    Object.keys(detailSections).forEach(function(key) {
        if (detailSections[key]) detailSections[key].style.display = key === method ? 'block' : 'none';
    });
}

document.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        highlightOption(this.value);
        showDetails(this.value);
    });
});

highlightOption('card');
showDetails('card');

var cardNumber = document.getElementById('cardNumber');
if (cardNumber) {
    cardNumber.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').slice(0, 16);
        this.value = v.replace(/(.{4})/g, '$1 ').trim();
    });
}

var cardExpiry = document.getElementById('cardExpiry');
if (cardExpiry) {
    cardExpiry.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').slice(0, 4);
        if (v.length >= 3) {
            this.value = v.slice(0, 2) + '/' + v.slice(2);
        } else {
            this.value = v;
        }
    });
}

var cardCvv = document.getElementById('cardCvv');
if (cardCvv) {
    cardCvv.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 4);
    });
}

var walletNumber = document.getElementById('walletNumber');
if (walletNumber) {
    walletNumber.addEventListener('input', function() {
        var v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length >= 1 && v[0] !== '0') v = '';
        if (v.length >= 2 && v[1] !== '3') v = v[0];
        this.value = v;
    });
}

var walletCnic = document.getElementById('walletCnic');
if (walletCnic) {
    walletCnic.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
}

var bankAccount = document.getElementById('bankAccount');
if (bankAccount) {
    bankAccount.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 20);
    });
}

var bankRef = document.getElementById('bankRef');
if (bankRef) {
    bankRef.addEventListener('input', function() {
        this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 15);
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
