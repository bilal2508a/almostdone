<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$title = trim($input['title'] ?? '');
$type = trim($input['type'] ?? '');
$city = trim($input['city'] ?? '');
$area = trim($input['area'] ?? '');
$bedrooms = trim($input['bedrooms'] ?? '');
$bathrooms = trim($input['bathrooms'] ?? '');
$areaSqft = trim($input['area_sqft'] ?? '');
$price = trim($input['price'] ?? '');
$pricePeriod = trim($input['price_period'] ?? '');
$amenities = $input['amenities'] ?? [];

$typeLabels = [
    'apartment' => 'apartment', 'house' => 'house', 'room' => 'room',
    'studio' => 'studio', 'villa' => 'villa'
];
$typeLabel = $typeLabels[$type] ?? strtolower(ucfirst($type));

$amenityStr = !empty($amenities) ? implode(', ', $amenities) : 'essential amenities';
$bedStr = $bedrooms ? $bedrooms . ' bedroom' . ($bedrooms != 1 ? 's' : '') : '';
$bathStr = $bathrooms ? $bathrooms . ' bathroom' . ($bathrooms != 1 ? 's' : '') : '';
$roomStr = array_filter([$bedStr, $bathStr]);
$roomStr = !empty($roomStr) ? implode(' and ', $roomStr) : 'comfortable living spaces';
$areaStr = $area ? ' in ' . $area : '';
$cityStr = $city ? ' in ' . $city : '';
$sizeStr = $areaSqft ? ' Spanning ' . $areaSqft . ' sqft,' : '';
$priceStr = '';
if ($price && is_numeric($price)) {
    $priceNum = number_format((int)$price);
    if ($pricePeriod === 'per_day') {
        $priceStr = ' Available at Rs ' . $priceNum . ' per day';
    } elseif ($pricePeriod === 'both') {
        $priceStr = ' Priced at Rs ' . $priceNum . ' per month';
    } else {
        $priceStr = ' Priced at Rs ' . $priceNum . ' per month';
    }
}
$locStr = '';
if ($area && $city) {
    $locStr = ' located in ' . $area . ', ' . $city;
} elseif ($city) {
    $locStr = ' located in ' . $city;
} elseif ($area) {
    $locStr = ' located in ' . $area;
}

$descriptions = [];

$descriptions[] = "Welcome to this beautiful $typeLabel$locStr.$sizeStr this property features $roomStr and comes with $amenityStr.$priceStr, offering a perfect blend of comfort and convenience. Ideal for families and professionals alike, it enjoys easy access to shopping, dining, and transport. Don't miss this opportunity to make it your new home.";

$descriptions[] = "Looking for the perfect place to live? This stunning $typeLabel$cityStr has everything you need.$sizeStr with $roomStr and $amenityStr, it provides a comfortable and modern living experience. The property is well-maintained and$areaStr close to all essential facilities.$priceStr. A great choice for anyone seeking quality living in a vibrant neighborhood.";

$descriptions[] = "This gorgeous $typeLabel$locStr is a rare find.$sizeStr it boasts $roomStr along with $amenityStr. The property features spacious rooms, natural light, and a welcoming atmosphere. Whether you're a family, a working professional, or a student, this home offers the perfect balance of tranquility and accessibility.$priceStr. Schedule a visit today!";

$descriptions[] = "Presenting an exceptional $typeLabel$cityStr offering $roomStr and $amenityStr.$sizeStr this property stands out for its quality construction, thoughtful layout, and prime location$areaStr.$priceStr. Enjoy a lifestyle of comfort with nearby parks, schools, and commercial areas. A truly wonderful place to call home.";

$descriptions[] = "Discover your dream home with this remarkable $typeLabel$locStr.$sizeStr offering $roomStr, the property is equipped with $amenityStr.$priceStr. Its strategic location ensures you're always connected to the best the city has to offer. Spacious, well-lit, and designed for modern living — this is where comfort meets convenience.";

echo json_encode(['descriptions' => $descriptions]);
