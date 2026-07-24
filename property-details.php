<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = currentUser();

$id = (int)($_GET['id'] ?? 0);
$property = get_property_by_id($id);

if (!$property) {
    flash('error', 'Property not found.');
    redirect('/properties.php');
}

$images = get_property_images($id);
$reviews = get_reviews($id);
$ratingData = get_avg_rating($id);
$avgRating = $ratingData['avg_rating'] ? round($ratingData['avg_rating'], 1) : 0;
$reviewCount = $ratingData['count'] ?? 0;

// Fetch confirmed bookings for this property to show unavailable dates
$bookedDatesStmt = db()->prepare("SELECT start_date, end_date FROM bookings WHERE property_id = ? AND status = 'confirmed' AND payment_status = 'paid' ORDER BY start_date ASC");
$bookedDatesStmt->execute([$id]);
$bookedRanges = $bookedDatesStmt->fetchAll();

$bookingState = 'book';
$bookingMessage = '';
if ($user) {
    if ($property['owner_id'] == $user['id']) {
        $bookingState = 'own';
        $bookingMessage = 'This is your property';
    } elseif (has_user_booked_property($user['id'], $property['id'])) {
        $bookingState = 'booked';
        $bookingMessage = 'You already booked this property';
    }
} else {
    $bookingState = 'login';
}

$inWishlist = $user ? is_in_wishlist($user['id'], $property['id']) : false;

$amenities = [];
if (!empty($property['has_wifi'])) $amenities[] = ['bi-wifi', 'WiFi'];
if (!empty($property['has_ac'])) $amenities[] = ['bi-snow', 'Air Conditioning'];
if (!empty($property['has_parking'])) $amenities[] = ['bi-car-front', 'Parking'];
if (!empty($property['has_generator'])) $amenities[] = ['bi-lightning-charge', 'Backup Generator'];
if (!empty($property['is_furnished'])) $amenities[] = ['bi-house-heart', 'Furnished'];
if (!empty($property['has_kitchen'])) $amenities[] = ['bi-cup-hot', 'Kitchen'];
if (!empty($property['has_swimming_pool'])) $amenities[] = ['bi-water', 'Swimming Pool'];
if (!empty($property['has_gym'])) $amenities[] = ['bi-bicycle', 'Gym'];
if (!empty($property['has_security'])) $amenities[] = ['bi-shield-check', 'Security'];
if (!empty($property['has_elevator'])) $amenities[] = ['bi-elevator', 'Elevator'];
if (!empty($property['has_garden'])) $amenities[] = ['bi-tree', 'Garden'];
if (!empty($property['has_heating'])) $amenities[] = ['bi-thermometer-sun', 'Heating'];
if (!empty($property['has_cctv'])) $amenities[] = ['bi-camera-video', 'CCTV'];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-app py-4">
    <!-- Breadcrumb -->
    <nav class="mb-4" style="font-size:0.875rem;">
        <a href="<?php echo url('/index.php'); ?>" style="color:var(--slate-500);text-decoration:none;">Home</a>
        <span style="color:var(--slate-300);"> / </span>
        <a href="<?php echo url('/properties.php'); ?>" style="color:var(--slate-500);text-decoration:none;">Properties</a>
        <span style="color:var(--slate-300);"> / </span>
        <span style="color:var(--slate-900);font-weight:600;"><?php echo e($property['title']); ?></span>
    </nav>

    <!-- Image Gallery -->
    <?php if (!empty($images)): ?>
    <div class="card-premium mb-4" style="overflow:hidden;border:none;box-shadow:var(--shadow-lg);">
        <div class="row g-0">
            <div class="col-lg-7">
                <div style="position:relative;height:480px;background:var(--slate-100);">
                    <img src="<?php echo e(image_url($images[0]['image_path'])); ?>" alt="<?php echo e($property['title']); ?>" id="mainGalleryImg" style="width:100%;height:100%;object-fit:cover;">
                    <?php if (!empty($property['featured'])): ?>
                        <span class="badge badge-featured"><i class="bi bi-star-fill"></i> Featured</span>
                    <?php endif; ?>
                    <span class="badge badge-type"><?php echo e(get_property_type_label($property['property_type'])); ?></span>
                </div>
            </div>
            <?php if (count($images) > 1): ?>
            <div class="col-lg-5">
                <div class="d-flex flex-column gap-2 p-3" style="height:480px;overflow-y:auto;">
                    <?php foreach ($images as $idx => $img): ?>
                        <div class="gallery-thumb <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="changeMainImage(this, '<?php echo e(image_url($img['image_path'])); ?>')" style="flex-shrink:0;height:145px;border-radius:var(--radius-sm);overflow:hidden;cursor:pointer;width:100%;">
                            <img src="<?php echo e(image_url($img['image_path'])); ?>" alt="Thumbnail <?php echo $idx + 1; ?>" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card-premium mb-4 d-flex align-items-center justify-content-center" style="height:300px;background:var(--slate-100);">
        <div class="text-center" style="color:var(--slate-400);">
            <i class="bi bi-image" style="font-size:3rem;"></i>
            <p class="mt-2">No images available</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- Header -->
            <div class="card-premium mb-4" style="padding:1.75rem;">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h1 style="font-size:1.75rem;font-weight:800;color:var(--slate-900);margin-bottom:0.5rem;letter-spacing:-0.02em;"><?php echo e($property['title']); ?></h1>
                        <p class="mb-0" style="color:var(--slate-500);font-size:0.95rem;">
                            <i class="bi bi-geo-alt" style="color:var(--primary-500);"></i>
                            <?php echo e($property['address'] . ', ' . $property['city']); ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <?php if ($property['price_period'] === 'both'): ?>
                            <div style="font-size:1.5rem;font-weight:800;color:var(--slate-900);">
                                <?php echo format_price($property['price']); ?><span style="font-size:0.85rem;font-weight:500;color:var(--slate-500);">/month</span>
                            </div>
                            <?php if ($property['price_per_day'] !== null): ?>
                                <div style="font-size:1.1rem;font-weight:700;color:var(--accent-600);">
                                    <?php echo format_price($property['price_per_day']); ?><span style="font-size:0.8rem;font-weight:500;color:var(--slate-500);">/day</span>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div style="font-size:1.5rem;font-weight:800;color:var(--slate-900);">
                                <?php echo format_price($property['price']); ?>
                                <span style="font-size:0.85rem;font-weight:500;color:var(--slate-500);">/<?php echo $property['price_period'] === 'per_day' ? 'day' : 'month'; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($avgRating > 0): ?>
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star-fill" style="color:<?php echo $i <= round($avgRating) ? 'var(--warning-500)' : 'var(--slate-200)'; ?>;font-size:1rem;"></i>
                        <?php endfor; ?>
                    </div>
                    <span style="font-weight:700;color:var(--slate-900);"><?php echo e(number_format($avgRating, 1)); ?></span>
                    <span style="color:var(--slate-500);font-size:0.875rem;">(<?php echo (int)$reviewCount; ?> review<?php echo $reviewCount === 1 ? '' : 's'; ?>)</span>
                </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-4 pt-3" style="border-top:1px solid var(--slate-100);">
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:var(--radius-sm);background:var(--primary-50);color:var(--primary-600);font-size:1.1rem;">
                            <i class="bi bi-house-door"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo e(get_property_type_label($property['property_type'])); ?></div>
                            <div style="font-size:0.75rem;color:var(--slate-500);">Type</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:var(--radius-sm);background:var(--accent-50);color:var(--accent-600);font-size:1.1rem;">
                            <i class="bi bi-door-open"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo (int)$property['bedrooms']; ?></div>
                            <div style="font-size:0.75rem;color:var(--slate-500);">Bedrooms</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:var(--radius-sm);background:var(--primary-50);color:var(--primary-600);font-size:1.1rem;">
                            <i class="bi bi-droplet"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo (int)$property['bathrooms']; ?></div>
                            <div style="font-size:0.75rem;color:var(--slate-500);">Bathrooms</div>
                        </div>
                    </div>
                    <?php if (!empty($property['area_sqft'])): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:var(--radius-sm);background:var(--warning-50);color:var(--warning-600);font-size:1.1rem;">
                            <i class="bi bi-rulers"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo (int)$property['area_sqft']; ?></div>
                            <div style="font-size:0.75rem;color:var(--slate-500);">Sq Ft</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Amenities -->
            <?php if (!empty($amenities)): ?>
            <div class="card-premium mb-4" style="padding:1.75rem;">
                <h3 style="font-size:1.25rem;font-weight:700;color:var(--slate-900);margin-bottom:1rem;letter-spacing:-0.01em;">
                    <i class="bi bi-check2-circle" style="color:var(--accent-600);"></i> Amenities
                </h3>
                <div class="row g-3">
                    <?php foreach ($amenities as $a): ?>
                    <div class="col-6 col-md-4">
                        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem 1rem;border-radius:var(--radius-sm);background:var(--slate-50);border:1px solid var(--slate-200);">
                            <i class="bi <?php echo $a[0]; ?>" style="font-size:1.25rem;color:var(--primary-600);"></i>
                            <span style="font-weight:600;color:var(--slate-900);font-size:0.9rem;"><?php echo e($a[1]); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Description -->
            <div class="card-premium mb-4" style="padding:1.75rem;">
                <h3 style="font-size:1.25rem;font-weight:700;color:var(--slate-900);margin-bottom:1rem;letter-spacing:-0.01em;">
                    <i class="bi bi-text-left" style="color:var(--primary-600);"></i> Description
                </h3>
                <p style="color:var(--slate-600);line-height:1.7;font-size:0.95rem;"><?php echo nl2br(e($property['description'])); ?></p>
            </div>

            <!-- Map & Nearby Places -->
            <div class="card-premium mb-4" style="padding:1.75rem;">
                <h3 style="font-size:1.25rem;font-weight:700;color:var(--slate-900);margin-bottom:1rem;letter-spacing:-0.01em;">
                    <i class="bi bi-map" style="color:var(--primary-600);"></i> Location & Nearby
                </h3>
                <div style="border:1px solid var(--slate-200);border-radius:var(--radius);overflow:hidden;">
                    <div id="mapFilterBar" class="map-filter-bar"></div>
                    <div id="propertyMap" style="height:410px;"></div>
                </div>
            </div>

            <!-- Reviews -->
            <div class="card-premium mb-4" style="padding:1.75rem;">
                <h3 style="font-size:1.25rem;font-weight:700;color:var(--slate-900);margin-bottom:1rem;letter-spacing:-0.01em;">
                    <i class="bi bi-chat-square-text" style="color:var(--warning-500);"></i> Reviews (<?php echo (int)$reviewCount; ?>)
                </h3>

                <?php if ($avgRating > 0): ?>
                <div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:var(--slate-50);border-radius:var(--radius);">
                    <span style="font-size:2.5rem;font-weight:800;color:var(--slate-900);"><?php echo e(number_format($avgRating, 1)); ?></span>
                    <div>
                        <div class="d-flex align-items-center gap-1">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star-fill" style="color:<?php echo $i <= round($avgRating) ? 'var(--warning-500)' : 'var(--slate-200)'; ?>;"></i>
                            <?php endfor; ?>
                        </div>
                        <div style="color:var(--slate-500);font-size:0.85rem;margin-top:0.25rem;">
                            Based on <?php echo (int)$reviewCount; ?> review<?php echo $reviewCount === 1 ? '' : 's'; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($user && $user['role'] === 'tenant'): ?>
                <form action="<?php echo url('/api/add-review.php'); ?>" method="POST" class="mb-4 p-3" style="background:var(--primary-50);border-radius:var(--radius);border:1px solid var(--primary-200);">
                    <input type="hidden" name="property_id" value="<?php echo (int)$property['id']; ?>">
                    <h4 style="font-size:1rem;font-weight:700;color:var(--slate-900);margin-bottom:0.75rem;">Write a Review</h4>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span style="font-size:0.875rem;color:var(--slate-500);margin-right:0.25rem;">Rating:</span>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" <?php echo $i === 5 ? 'checked' : ''; ?> style="display:none;" required>
                            <label for="star<?php echo $i; ?>" class="review-star-label" style="cursor:pointer;font-size:1.5rem;color:var(--slate-200);transition:color 0.15s;" onmouseover="hoverStars(<?php echo $i; ?>)" onmouseout="resetStars()">
                                <i class="bi bi-star-fill"></i>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <textarea name="comment" placeholder="Share your experience..." rows="3" required class="form-control-mh" style="margin-bottom:0.75rem;"></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-send"></i> Submit Review
                    </button>
                </form>
                <?php elseif (!$user): ?>
                <div class="alert alert-info mb-4" style="background:var(--primary-50);border:1px solid var(--primary-200);border-radius:var(--radius);padding:1rem;color:var(--primary-700);font-size:0.9rem;">
                    <i class="bi bi-info-circle"></i>
                    <a href="<?php echo url('/login.php'); ?>" style="color:var(--primary-700);font-weight:600;">Login</a> to write a review.
                </div>
                <?php endif; ?>

                <?php if (empty($reviews)): ?>
                    <p style="color:var(--slate-500);font-style:italic;">No reviews yet. Be the first to review!</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($reviews as $review): ?>
                        <div class="p-3" style="border:1px solid var(--slate-200);border-radius:var(--radius);background:#fff;">
                            <div class="d-flex align-items-start gap-3 mb-2">
                                <div class="d-flex align-items-center justify-content-center" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary-600),var(--accent-500));color:#fff;font-weight:700;flex-shrink:0;">
                                    <?php echo e(strtoupper(substr($review['user_name'], 0, 1))); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo e($review['user_name']); ?></div>
                                    <div class="d-flex align-items-center gap-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star-fill" style="color:<?php echo $i <= $review['rating'] ? 'var(--warning-500)' : 'var(--slate-200)'; ?>;font-size:0.75rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span style="color:var(--slate-400);font-size:0.8rem;white-space:nowrap;"><?php echo e(formatDate($review['created_at'])); ?></span>
                            </div>
                            <p style="color:var(--slate-600);font-size:0.9rem;margin:0;padding-left:52px;"><?php echo e($review['comment']); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card-premium" style="padding:1.75rem;position:sticky;top:90px;">
                <h3 style="font-size:1.1rem;font-weight:700;color:var(--slate-900);margin-bottom:1rem;letter-spacing:-0.01em;">
                    <i class="bi bi-person-circle" style="color:var(--primary-600);"></i> Contact Owner
                </h3>

                <div class="d-flex align-items-center gap-3 mb-3 p-3" style="background:var(--slate-50);border-radius:var(--radius);">
                    <div class="d-flex align-items-center justify-content-center" style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--primary-600),var(--accent-500));color:#fff;font-weight:700;font-size:1.25rem;flex-shrink:0;box-shadow:0 4px 12px -2px rgba(26,82,245,0.3);">
                        <?php echo e(strtoupper(substr($property['owner_name'], 0, 1))); ?>
                    </div>
                    <div>
                        <div style="font-weight:700;color:var(--slate-900);font-size:0.95rem;"><?php echo e($property['owner_name']); ?></div>
                        <div style="font-size:0.8rem;color:var(--slate-500);">Property Owner</div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2 mb-3">
                    <?php if (!empty($property['owner_phone'])): ?>
                    <div class="d-flex align-items-center gap-2" style="color:var(--slate-600);font-size:0.9rem;">
                        <i class="bi bi-phone" style="color:var(--accent-600);"></i>
                        <?php echo e($property['owner_phone']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex align-items-center gap-2" style="color:var(--slate-600);font-size:0.9rem;">
                        <i class="bi bi-envelope" style="color:var(--accent-600);"></i>
                        <?php echo e($property['owner_email']); ?>
                    </div>
                </div>

                <?php if ($bookingState === 'own' || $bookingState === 'booked'): ?>
                    <div class="alert alert-success text-center mb-3" style="font-weight:600;padding:0.75rem;">
                        <i class="bi bi-check-circle"></i> <?php echo e($bookingMessage); ?>
                    </div>
                <?php elseif ($bookingState === 'login'): ?>
                    <a href="<?php echo url('/login.php'); ?>" class="btn btn-primary btn-block mb-2">
                        <i class="bi bi-box-arrow-in-right"></i> Login to Book
                    </a>
                <?php else: ?>
                    <?php if (!empty($bookedRanges)): ?>
                    <div style="margin-bottom:0.75rem;padding:0.75rem 1rem;background:var(--warning-50);border:1px solid var(--warning-200);border-radius:10px;">
                        <div style="font-weight:700;color:var(--warning-700);font-size:0.85rem;margin-bottom:0.4rem;">
                            <i class="bi bi-calendar-x"></i> Currently Booked Dates
                        </div>
                        <?php foreach ($bookedRanges as $range): ?>
                            <div style="font-size:0.8rem;color:var(--slate-600);padding:0.15rem 0;">
                                <?php echo formatDate($range['start_date']); ?> — <?php echo formatDate($range['end_date']); ?>
                            </div>
                        <?php endforeach; ?>
                        <div style="font-size:0.78rem;color:var(--slate-500);margin-top:0.4rem;font-style:italic;">You can book for dates outside these ranges.</div>
                    </div>
                    <?php endif; ?>
                    <a href="<?php echo url('/booking.php?property_id=' . (int)$property['id']); ?>" class="btn btn-primary btn-block mb-2">
                        <i class="bi bi-calendar-check"></i> Book Now
                    </a>
                <?php endif; ?>

                <?php if ($user && $property['owner_id'] != $user['id']): ?>
                <button onclick="toggleWishlist(event, <?php echo (int)$property['id']; ?>)" class="btn <?php echo $inWishlist ? 'btn-accent' : 'btn-ghost'; ?> btn-block" id="wishlistBtn">
                    <i class="bi <?php echo $inWishlist ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                    <span id="wishlistText"><?php echo $inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function changeMainImage(thumb, src) {
    document.getElementById('mainGalleryImg').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(function(t) { t.classList.remove('active'); });
    thumb.classList.add('active');
}

function hoverStars(n) {
    for (var i = 1; i <= 5; i++) {
        var label = document.querySelector('label[for="star' + i + '"]');
        if (label) label.style.color = i <= n ? 'var(--warning-500)' : 'var(--slate-200)';
    }
}

function resetStars() {
    var checked = document.querySelector('input[name="rating"]:checked');
    var checkedVal = checked ? parseInt(checked.value) : 5;
    for (var i = 1; i <= 5; i++) {
        var label = document.querySelector('label[for="star' + i + '"]');
        if (label) label.style.color = i <= checkedVal ? 'var(--warning-500)' : 'var(--slate-200)';
    }
}

document.querySelectorAll('input[name="rating"]').forEach(function(input) {
    input.addEventListener('change', function() { resetStars(); });
});

function toggleWishlist(event, propertyId) {
    event.preventDefault();
    fetch('<?php echo url("/api/wishlist.php"); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'property_id=' + propertyId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var btn = document.getElementById('wishlistBtn');
            var text = document.getElementById('wishlistText');
            var icon = btn.querySelector('i');
            if (data.action === 'added') {
                btn.classList.remove('btn-ghost');
                btn.classList.add('btn-accent');
                icon.className = 'bi bi-heart-fill';
                text.textContent = 'Remove from Wishlist';
            } else {
                btn.classList.remove('btn-accent');
                btn.classList.add('btn-ghost');
                icon.className = 'bi bi-heart';
                text.textContent = 'Add to Wishlist';
            }
        } else if (data.message === 'Not logged in') {
            window.location.href = '<?php echo url("/login.php"); ?>';
        }
    })
    .catch(function() {});
}
</script>

<!-- Leaflet Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
/* Map container */
#propertyMap { cursor: grab; }
#propertyMap:active { cursor: grabbing; }

/* Category filter pills */
.map-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 10px 14px;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    border-radius: var(--radius) var(--radius) 0 0;
}
.map-filter-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 11px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid transparent;
    transition: all 0.18s;
    user-select: none;
    background: #f1f5f9;
    color: #64748b;
}
.map-filter-pill.active {
    color: #fff;
    border-color: transparent;
}
.map-filter-pill:hover { filter: brightness(0.93); }
.map-pill-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Leaflet popup override */
.leaflet-popup-content-wrapper {
    border-radius: 10px !important;
    box-shadow: 0 4px 20px rgba(0,0,0,0.18) !important;
    padding: 0 !important;
    overflow: hidden;
}
.leaflet-popup-content {
    margin: 0 !important;
    font-family: inherit !important;
    width: auto !important;
    min-width: 180px;
}
.leaflet-popup-tip-container { display: none; }
.map-popup {
    padding: 12px 14px;
    font-size: 13px;
}
.map-popup-title {
    font-weight: 700;
    color: #1e293b;
    font-size: 13.5px;
    margin-bottom: 3px;
    line-height: 1.3;
}
.map-popup-cat {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    color: #fff;
    display: inline-block;
}

/* SVG pin icons */
.map-pin-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.25));
    transition: filter 0.2s;
}
.map-pin-wrap:hover { filter: drop-shadow(0 5px 10px rgba(0,0,0,0.35)) brightness(1.08); }
.map-pin-circle {
    width: 34px; height: 34px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    display: flex; align-items: center; justify-content: center;
    border: 2.5px solid rgba(255,255,255,0.75);
}
.map-pin-circle svg, .map-pin-circle span {
    transform: rotate(45deg);
    display: block;
}
.map-pin-circle span { font-size: 15px; line-height: 1; }

/* Property pin */
.prop-pin-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.35));
}
.prop-pin-badge {
    background: #1e293b;
    color: #fff;
    border-radius: 8px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    border: 2px solid rgba(255,255,255,0.25);
}
.prop-pin-stem {
    width: 0; height: 0;
    border-left: 7px solid transparent;
    border-right: 7px solid transparent;
    border-top: 10px solid #1e293b;
}

/* Map legend */
#mapLegend {
    background: rgba(255,255,255,0.96);
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 11px;
    line-height: 1.6;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    min-width: 110px;
}
#mapLegend .leg-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    color: #334155;
}
#mapLegend .leg-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
</style>
<script>
(function() {
    var address  = <?php echo json_encode($property['address'] . ', ' . $property['city']); ?>;
    var city     = <?php echo json_encode($property['city']); ?>;
    var propName = <?php echo json_encode($property['title']); ?>;

    /* ── Category definitions ──────────────────────────────────────────── */
    var catInfo = {
        hospital:    { emoji:'🏥', color:'#ef4444', label:'Hospital',    icon:'h' },
        clinic:      { emoji:'🏥', color:'#f87171', label:'Clinic',      icon:'h' },
        hotel:       { emoji:'🏨', color:'#0ea5e9', label:'Hotel',       icon:'b' },
        restaurant:  { emoji:'🍽️', color:'#f59e0b', label:'Restaurant',  icon:'r' },
        school:      { emoji:'🏫', color:'#8b5cf6', label:'School',      icon:'s' },
        bank:        { emoji:'🏦', color:'#10b981', label:'Bank',        icon:'k' },
        supermarket: { emoji:'🛒', color:'#ec4899', label:'Supermarket', icon:'m' },
        pharmacy:    { emoji:'💊', color:'#e11d48', label:'Pharmacy',    icon:'p' },
        mosque:      { emoji:'🕌', color:'#64748b', label:'Mosque',      icon:'q' },
        fuel:        { emoji:'⛽', color:'#f97316', label:'Fuel',        icon:'f' },
        bus_station: { emoji:'🚌', color:'#3b82f6', label:'Bus Stop',    icon:'u' }
    };

    /* SVG paths for each icon type (compact, 24x24 viewBox) */
    var iconSvg = {
        h: '<path d="M12 2a5 5 0 100 10A5 5 0 0012 2zm1 6h-2V6H9V8H7v2h2v2h2v-2h2V8z" fill="#fff"/>',  /* hospital cross */
        b: '<path d="M3 8l9-6 9 6v12H3V8zm7 4h4v6h-4v-6z" fill="none" stroke="#fff" stroke-width="1.5"/>', /* hotel */
        r: '<path d="M8 4v4M12 4v4M16 4v4M7 8c0 3.3 2 5 5 5s5-1.7 5-5H7zm5 5v7" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>', /* fork/spoon */
        s: '<path d="M4 20V10l8-6 8 6v10M8 20v-6h8v6" fill="none" stroke="#fff" stroke-width="1.5"/>', /* school */
        k: '<rect x="5" y="6" width="14" height="12" rx="2" fill="none" stroke="#fff" stroke-width="1.5"/><path d="M5 10h14M9 10v8" stroke="#fff" stroke-width="1.5"/>', /* bank */
        m: '<path d="M6 2l2 5h8l2-5M6 7v13h12V7M10 11h4" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>', /* shopping cart */
        p: '<path d="M12 4v16M4 12h16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>', /* pharmacy cross */
        q: '<path d="M12 3L3 9v4h18V9L12 3zm0 0v6M8 13v7M16 13v7M4 20h16" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>', /* mosque */
        f: '<path d="M9 2v8l-3 4h12l-3-4V2M9 2h6M12 14v8" fill="none" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>', /* fuel */
        u: '<rect x="2" y="8" width="20" height="10" rx="3" fill="none" stroke="#fff" stroke-width="1.5"/><circle cx="7" cy="20" r="2" fill="#fff"/><circle cx="17" cy="20" r="2" fill="#fff"/><path d="M6 8V5M18 8V5" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>' /* bus */
    };

    /* ── Map init ─────────────────────────────────────────────────────── */
    var map = L.map('propertyMap', {
        zoomControl: false,
        scrollWheelZoom: true
    }).setView([31.5497, 74.3436], 14);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Legend control */
    var legendControl = L.control({ position: 'bottomleft' });
    legendControl.onAdd = function() {
        var div = L.DomUtil.create('div', '');
        div.id = 'mapLegend';
        div.innerHTML = '<div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;">Nearby Places</div>';
        return div;
    };
    legendControl.addTo(map);

    /* ── Icon factories ───────────────────────────────────────────────── */
    function makeNearbyIcon(cat, name) {
        var info = catInfo[cat];
        var svg  = iconSvg[info.icon] || iconSvg.h;
        var html = '<div class="map-pin-wrap">' +
            '<div class="map-pin-circle" style="background:' + info.color + ';">' +
            '<span><svg viewBox="0 0 24 24" width="17" height="17" xmlns="http://www.w3.org/2000/svg">' + svg + '</svg></span>' +
            '</div></div>';
        return L.divIcon({
            html: html,
            className: '',
            iconAnchor: [17, 34],
            popupAnchor: [0, -36]
        });
    }

    function makePropIcon(title) {
        var short = title.length > 28 ? title.slice(0, 26) + '…' : title;
        var html = '<div class="prop-pin-wrap">' +
            '<div class="prop-pin-badge">📍 ' + short + '</div>' +
            '<div class="prop-pin-stem"></div></div>';
        return L.divIcon({
            html: html,
            className: '',
            iconAnchor: [70, 42],
            popupAnchor: [0, -44]
        });
    }

    /* ── State ────────────────────────────────────────────────────────── */
    var allMarkers   = {};   /* cat → [L.Marker] */
    var activeFilter = null; /* null = all visible */
    var legendEl     = document.getElementById('mapLegend');

    /* ── Marker click → zoom in ───────────────────────────────────────── */
    function onMarkerClick(marker, cat, name) {
        var ll = marker.getLatLng();
        map.flyTo(ll, Math.max(map.getZoom(), 17), { animate: true, duration: 0.8 });
        var info = catInfo[cat];
        var popup = L.popup({ closeButton: true, maxWidth: 260, className: '' })
            .setLatLng(ll)
            .setContent(
                '<div class="map-popup">' +
                '<div class="map-popup-title">' + escHtml(name) + '</div>' +
                '<span class="map-popup-cat" style="background:' + info.color + ';">' + info.emoji + ' ' + info.label + '</span>' +
                '</div>'
            );
        popup.openOn(map);
    }

    function onPropClick(marker, title) {
        var ll = marker.getLatLng();
        map.flyTo(ll, 17, { animate: true, duration: 0.8 });
        var popup = L.popup({ closeButton: true, maxWidth: 280 })
            .setLatLng(ll)
            .setContent(
                '<div class="map-popup">' +
                '<div class="map-popup-title">📍 ' + escHtml(title) + '</div>' +
                '<span style="font-size:11.5px;color:#64748b;">This property</span>' +
                '</div>'
            );
        popup.openOn(map);
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Filter pills ─────────────────────────────────────────────────── */
    function buildFilterBar(presentCats) {
        var bar = document.getElementById('mapFilterBar');
        if (!bar) return;
        bar.innerHTML = '';

        /* "All" pill */
        var allPill = document.createElement('span');
        allPill.className = 'map-filter-pill active';
        allPill.style.background = '#1e293b';
        allPill.dataset.cat = '';
        allPill.innerHTML = '<span class="map-pill-dot" style="background:#fff;"></span> All';
        bar.appendChild(allPill);

        presentCats.forEach(function(cat) {
            var info = catInfo[cat];
            if (!info) return;
            var pill = document.createElement('span');
            pill.className = 'map-filter-pill';
            pill.dataset.cat = cat;
            pill.innerHTML = '<span class="map-pill-dot" style="background:' + info.color + ';"></span> ' + info.label;
            bar.appendChild(pill);
        });

        bar.addEventListener('click', function(e) {
            var pill = e.target.closest('.map-filter-pill');
            if (!pill) return;
            var cat = pill.dataset.cat;
            bar.querySelectorAll('.map-filter-pill').forEach(function(p) {
                p.classList.remove('active');
                p.style.background = '#f1f5f9';
                p.style.color = '#64748b';
            });
            pill.classList.add('active');
            if (cat === '') {
                pill.style.background = '#1e293b';
                pill.style.color = '#fff';
            } else {
                pill.style.background = catInfo[cat].color;
                pill.style.color = '#fff';
            }
            setFilter(cat === '' ? null : cat);
        });
    }

    function setFilter(cat) {
        activeFilter = cat;
        Object.keys(allMarkers).forEach(function(c) {
            allMarkers[c].forEach(function(m) {
                if (cat === null || c === cat) {
                    if (!map.hasLayer(m)) map.addLayer(m);
                } else {
                    if (map.hasLayer(m)) map.removeLayer(m);
                }
            });
        });
    }

    /* ── Geocode + init ───────────────────────────────────────────────── */
    function initMap(lat, lon) {
        map.flyTo([lat, lon], 15, { animate: false });
        var propMarker = L.marker([lat, lon], { icon: makePropIcon(propName), zIndexOffset: 1000 }).addTo(map);
        propMarker.on('click', function() { onPropClick(propMarker, propName); });
        loadNearby(lat, lon);
    }

    function loadNearby(lat, lon) {
        var r = 2000;
        var q = '[out:json][timeout:25];(' +
            'node["amenity"="hospital"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="clinic"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="doctors"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["tourism"="hotel"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="restaurant"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="school"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="bank"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["shop"="supermarket"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="pharmacy"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="place_of_worship"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="fuel"](around:' + r + ',' + lat + ',' + lon + ');' +
            'node["amenity"="bus_station"](around:' + r + ',' + lat + ',' + lon + ');' +
            ');out body;';

        var endpoints = [
            'https://overpass-api.de/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter'
        ];

        function tryEndpoint(i) {
            if (i >= endpoints.length) return;
            fetch(endpoints[i], {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'data=' + encodeURIComponent(q)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data || !data.elements || !data.elements.length) {
                    tryEndpoint(i + 1); return;
                }
                renderMarkers(data.elements, lat, lon);
            })
            .catch(function() { tryEndpoint(i + 1); });
        }
        tryEndpoint(0);
    }

    function getCategory(el) {
        var t = el.tags || {};
        if (t.amenity === 'hospital')                            return 'hospital';
        if (t.amenity === 'clinic' || t.amenity === 'doctors')  return 'clinic';
        if (t.tourism === 'hotel')                               return 'hotel';
        if (t.amenity === 'restaurant')                          return 'restaurant';
        if (t.amenity === 'school')                              return 'school';
        if (t.amenity === 'bank')                                return 'bank';
        if (t.shop === 'supermarket')                            return 'supermarket';
        if (t.amenity === 'pharmacy')                            return 'pharmacy';
        if (t.amenity === 'place_of_worship')                    return 'mosque';
        if (t.amenity === 'fuel')                                return 'fuel';
        if (t.amenity === 'bus_station')                         return 'bus_station';
        return null;
    }

    function renderMarkers(elements, propLat, propLon) {
        var seen = {};
        var presentCats = [];

        elements.forEach(function(el) {
            var cat = getCategory(el);
            if (!cat || !el.lat || !el.lon) return;
            if (!seen[cat]) { seen[cat] = 0; presentCats.push(cat); }
            if (seen[cat] >= 4) return;
            seen[cat]++;

            var name = (el.tags && el.tags.name) ? el.tags.name : catInfo[cat].label;
            var marker = L.marker([el.lat, el.lon], { icon: makeNearbyIcon(cat, name) }).addTo(map);

            /* Click → zoom + popup */
            (function(m, c, n) {
                m.on('click', function() { onMarkerClick(m, c, n); });
            })(marker, cat, name);

            if (!allMarkers[cat]) allMarkers[cat] = [];
            allMarkers[cat].push(marker);
        });

        /* Build legend */
        if (legendEl) {
            var rows = '';
            presentCats.forEach(function(cat) {
                var info = catInfo[cat];
                rows += '<div class="leg-row"><span class="leg-dot" style="background:' + info.color + ';"></span>' + info.emoji + ' ' + info.label + '</div>';
            });
            legendEl.innerHTML += rows;
        }

        buildFilterBar(presentCats);
    }

    /* Geocode: try full address → city only → Lahore fallback */
    function geocode(query, callback) {
        fetch('https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&q=' + encodeURIComponent(query))
            .then(function(r) { return r.json(); })
            .then(function(d) { callback(d && d.length ? d[0] : null); })
            .catch(function() { callback(null); });
    }

    geocode(address, function(result) {
        if (result) {
            initMap(parseFloat(result.lat), parseFloat(result.lon));
        } else {
            geocode(city + ', Pakistan', function(r2) {
                if (r2) {
                    initMap(parseFloat(r2.lat), parseFloat(r2.lon));
                } else {
                    initMap(31.5497, 74.3436);
                }
            });
        }
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
