<?php
session_start();
require_once "db.php";
// =====================================================
// CHECK IF USER IS LOGGED IN
// =====================================================
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
// =====================================================
// SEARCH AND SCHOOL-FEE FILTERS
// =====================================================
$search = trim($_GET["search"] ?? "");
$min_fee = (isset($_GET["min_fee"]) && $_GET["min_fee"] !== "") ? max(0, (float) $_GET["min_fee"]) : null;
$max_fee = (isset($_GET["max_fee"]) && $_GET["max_fee"] !== "") ? max(0, (float) $_GET["max_fee"]) : null;

// Fee inputs are converted to numbers; search text is parameterized.
$where = ["status = 'active'", "approval_status = 'approved'"];
if ($min_fee !== null) $where[] = "school_fees >= " . (float) $min_fee;
if ($max_fee !== null) $where[] = "school_fees <= " . (float) $max_fee;
if ($search !== "") $where[] = "(school_name LIKE ? OR location LIKE ? OR school_type LIKE ?)";
$sql = "SELECT * FROM schools WHERE " . implode(" AND ", $where) . " ORDER BY school_name ASC";
if ($search !== "") {
    $search_value = "%" . $search . "%";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_value, $search_value, $search_value);
    $stmt->execute();
    $schools_result = $stmt->get_result();
} else {
    $schools_result = $conn->query($sql);
}
$school_list = [];
if ($schools_result) {
    while ($row = $schools_result->fetch_assoc()) $school_list[] = $row;
}

// =====================================================
// UNREAD NOTIFICATIONS (for the nav badge)
// =====================================================

$unread_notifications = 0;

$notification_stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = ?
    AND is_read = FALSE
    "
);

if ($notification_stmt) {
    $uid = intval($_SESSION["user_id"]);
    $notification_stmt->bind_param("i", $uid);
    $notification_stmt->execute();
    $unread_notifications = $notification_stmt->get_result()->fetch_assoc()["total"];
    $notification_stmt->close();
}

$school_total = count($school_list);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a School | SchoolConnect</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0b1b3a;
            --blue: #0756b8;
            --sky: #e8f1fd;
            --chalk: #f4f6fb;
            --sun: #f5b301;
            --muted: #62708a;
            --line: #e5eaf3;
            --green: #16a34a;
            --red: #dc2626;
            --display: "Bricolage Grotesque", "Segoe UI", Arial, sans-serif;
            --body: "DM Sans", "Segoe UI", Arial, sans-serif;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--body);
            background: var(--chalk);
            color: var(--ink);
            line-height: 1.5;
        }

        a:focus-visible,
        button:focus-visible,
        input:focus-visible {
            outline: 3px solid var(--sun);
            outline-offset: 2px;
        }

        /* ---------- NAVBAR ---------- */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(11, 27, 58, .94);
            backdrop-filter: blur(10px);
            color: #fff;
            min-height: 68px;
            padding: 0 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            font-family: var(--display);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .logo span {
            color: var(--sun);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-links a {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #cbd7ee;
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
            transition: color .2s, background .2s;
        }

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, .09);
        }

        .nav-links .active {
            color: #fff;
            background: rgba(255, 255, 255, .12);
        }

        .nav-links .active::after {
            content: "";
            position: absolute;
            left: 13px;
            right: 13px;
            bottom: 3px;
            height: 2px;
            border-radius: 2px;
            background: var(--sun);
        }

        .nav-links .logout {
            margin-left: 8px;
            color: #fff;
            background: var(--red);
        }

        .nav-links .logout:hover {
            background: #b91c1c;
        }

        .notification-badge {
            background: var(--sun);
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            border-radius: 99px;
            display: inline-grid;
            place-items: center;
        }

        /* ---------- ENTRANCE ---------- */
        .rise {
            animation: rise .7s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(var(--i, 0) * 80ms);
        }


        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* ---------- HERO + SEARCH ---------- */
        .hero-wrap {
            width: 90%;
            max-width: 1200px;
            margin: 32px auto 30px;
        }

        .hero {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(135deg, var(--ink) 0%, #0b3a86 100%);
            color: #fff;
            border-radius: 22px;
            padding: 44px 40px 40px;
            box-shadow: 0 18px 40px -18px rgba(11, 27, 58, .55);
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -90px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            border: 46px solid rgba(245, 179, 1, .16);
            animation: drift 9s ease-in-out infinite alternate;
        }

        @keyframes drift {
            from {
                transform: translate(0, 0) scale(1);
            }

            to {
                transform: translate(-24px, 18px) scale(1.06);
            }
        }

        .hero>* {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-family: var(--display);
            font-size: clamp(28px, 4.5vw, 42px);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .hero p {
            color: #c3d3f0;
            font-size: 16px;
            max-width: 48ch;
            margin-bottom: 26px;
        }

        .search-box {
            display: flex;
            gap: 8px;
            max-width: 660px;
            background: #fff;
            border-radius: 16px;
            padding: 7px;
            box-shadow: 0 14px 30px -14px rgba(0, 0, 0, .5);
            transition: box-shadow .25s;
        }

        .search-box:focus-within {
            box-shadow: 0 0 0 4px rgba(245, 179, 1, .55), 0 14px 30px -14px rgba(0, 0, 0, .5);
        }

        .search-box input {
            flex: 1;
            min-width: 0;
            border: none;
            background: none;
            outline: none;
            font-family: var(--body);
            font-size: 15px;
            color: var(--ink);
            padding: 10px 14px;
        }

        .search-box button {
            border: none;
            cursor: pointer;
            font-family: var(--body);
            background: var(--sun);
            color: var(--ink);
            font-weight: 700;
            font-size: 14px;
            padding: 0 22px;
            border-radius: 11px;
            white-space: nowrap;
            transition: transform .2s, background .2s;
        }

        .search-box button:hover {
            background: #ffc61f;
            transform: translateY(-1px);
        }

        .search-box button:active {
            transform: scale(.97);
        }

        /* ---------- MAP AND FEE FILTERS ---------- */
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            align-items: center;
            width: 100%;
            padding: 4px 6px 5px;
        }

        .filter-row label {
            color: #dce8fb;
            font-size: 13px;
            font-weight: 700;
        }

        .filter-row input {
            width: 140px;
            min-width: 0;
            padding: 10px 12px;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            font: 14px var(--body);
            color: var(--ink);
            background: #fff;
        }

        .filter-row .filter-hint {
            flex-basis: 100%;
            color: #c3d3f0;
            font-size: 12px;
        }

        /* ---------- MORE VISIBLE SEARCH ---------- */
        .search-form {
            max-width: 780px;
        }

        .search-label {
            display: block;
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 10px;
        }

        .search-box {
            align-items: center;
            max-width: none;
            padding: 8px;
            border-radius: 18px;
            box-shadow: 0 18px 36px -14px rgba(0, 0, 0, .55), 0 0 0 3px rgba(255, 255, 255, .28);
        }

        .search-icon {
            padding: 0 4px 0 14px;
            font-size: 20px;
        }

        .search-box input {
            font-size: 17px;
            font-weight: 500;
            padding: 16px 8px;
        }

        .search-box input::placeholder {
            color: #7b8aa5;
            font-weight: 400;
        }

        .search-box button {
            align-self: stretch;
            padding: 0 32px;
            font-size: 16px;
            border-radius: 12px;
        }

        .filter-row {
            margin-top: 16px;
            padding: 0;
        }

        .filter-row label {
            color: #fff;
            font-size: 14px;
        }

        .filter-row input {
            width: 150px;
            background: #fff;
            border: 1px solid rgba(255, 255, 255, .5);
        }

        .filter-to {
            color: #c3d3f0;
            font-size: 13px;
        }

        @media (max-width:650px) {
            .search-icon {
                display: none;
            }

            .search-box input {
                padding: 14px 12px;
            }

            .filter-row input {
                flex: 1;
                width: auto;
            }
        }

        .map-section {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 18px;
            margin: 0 0 28px;
            box-shadow: 0 8px 24px -20px rgba(11, 27, 58, .35);
        }

        .map-heading {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .map-heading h2 {
            font: 700 20px var(--display);
        }

        .map-heading p {
            color: var(--muted);
            font-size: 13px;
        }

        #schoolMap {
            width: 100%;
            height: 390px;
            border-radius: 14px;
            z-index: 1;
            background: #e8f1fd;
        }

        .leaflet-popup-content {
            font-family: var(--body);
            line-height: 1.5;
            min-width: 170px;
        }

        .map-popup-title {
            color: var(--ink);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .map-popup-link {
            display: inline-block;
            margin-top: 7px;
            color: var(--blue);
            font-weight: 700;
            text-decoration: none;
        }

        @media (max-width:650px) {
            .filter-row input {
                flex: 1;
                width: 100%;
            }

            #schoolMap {
                height: 320px;
            }
        }

        /* ---------- MAIN ---------- ---------- */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto 60px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-family: var(--display);
            font-size: 24px;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .school-count {
            background: var(--sky);
            color: var(--blue);
            font-size: 13px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 99px;
        }

        .clear-search {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background .2s, color .2s;
        }

        .clear-search:hover {
            background: #fee2e2;
            color: var(--red);
        }

        /* ---------- GRID + CARD ---------- */
        .school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 22px;
        }

        .school-card {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            overflow: hidden;
            animation: rise .65s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(.25s + var(--r, 0) * 70ms);
            transition: transform .3s, box-shadow .3s;
        }

        .school-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 24px 40px -22px rgba(11, 27, 58, .4);
        }

        .school-image {
            position: relative;
            height: 180px;
            overflow: hidden;
            background: var(--sky);
        }

        .school-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .6s cubic-bezier(.2, .7, .2, 1);
        }

        .school-card:hover .school-image img {
            transform: scale(1.08);
        }

        .placeholder {
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 60px;
            background: linear-gradient(135deg, var(--sky), #cfe1fa);
            transition: transform .4s;
        }

        .school-card:hover .placeholder {
            transform: scale(1.12) rotate(-4deg);
        }

        .active-badge {
            position: absolute;
            top: 14px;
            right: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .95);
            color: #166534;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 11px;
            border-radius: 99px;
            box-shadow: 0 6px 14px -6px rgba(0, 0, 0, .3);
        }

        .active-badge::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: blink 1.8s ease-in-out infinite;
        }

        @keyframes blink {
            50% {
                opacity: .3;
            }
        }

        .school-content {
            padding: 20px 22px 22px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .school-name {
            font-family: var(--display);
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .school-type {
            align-self: flex-start;
            background: var(--chalk);
            color: var(--blue);
            border: 1px solid var(--line);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 11px;
            border-radius: 99px;
            margin-bottom: 14px;
        }

        .school-info {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 12px;
            overflow-wrap: anywhere;
        }

        .school-description {
            color: #475569;
            font-size: 14px;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .card-buttons {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .card-buttons a {
            flex: 1;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 11px 12px;
            border-radius: 12px;
            transition: transform .2s, background .2s, color .2s, box-shadow .2s;
        }

        .card-buttons a:hover {
            transform: translateY(-2px);
        }

        .view-button {
            background: var(--chalk);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .view-button:hover {
            background: var(--sky);
            color: var(--blue);
        }

        .book-button {
            background: var(--ink);
            color: #fff;
        }

        .book-button:hover {
            background: var(--blue);
            box-shadow: 0 10px 20px -10px rgba(7, 86, 184, .8);
        }

        /* ---------- EMPTY ---------- */
        .empty {
            grid-column: 1 / -1;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 70px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 54px;
            margin-bottom: 12px;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            50% {
                transform: translateY(-8px);
            }
        }

        .empty h3 {
            font-family: var(--display);
            font-size: 22px;
            margin-bottom: 6px;
        }

        .empty p {
            color: var(--muted);
            font-size: 14px;
        }

        /* ---------- FOOTER ---------- */
        .footer {
            background: var(--ink);
            text-align: center;
            padding: 26px;
        }

        .footer p {
            color: #8fa1c4;
            font-size: 13px;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 650px) {
            .navbar {
                padding: 14px 5%;
                flex-direction: column;
                gap: 10px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero-wrap,
            .container {
                width: 94%;
            }

            .hero-wrap {
                margin-top: 20px;
            }

            .hero {
                padding: 30px 22px;
            }

            .search-box {
                flex-direction: column;
            }

            .search-box button {
                padding: 12px;
            }

            .school-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="logo">🎓 School<span>Connect</span></div>

        <div class="nav-links">
            <a href="parent-dashboard.php">🏠 Dashboard</a>
            <a href="schools.php" class="active">🏫 Schools</a>
            <a href="my-bookings.php">📅 My Bookings</a>

            <a href="notifications.php">
                🔔 Notifications
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge"><?= $unread_notifications > 99 ? "99+" : (int) $unread_notifications ?></span>
                <?php endif; ?>
            </a>

            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <!-- HERO + SEARCH -->
    <div class="hero-wrap">
        <section class="hero rise" style="--i:0">
            <h1>Find the right school</h1>
            <p>Explore available basic schools and find the best option for your child.</p>

            <form method="GET" class="search-form" role="search">
                <label class="search-label" for="search">Search schools by name or location</label>

                <div class="search-box">
                    <span class="search-icon" aria-hidden="true">🔍</span>
                    <input
                        id="search"
                        type="search"
                        name="search"
                        placeholder="Type a school name or location, e.g. East Legon"
                        aria-label="Search by school name, location, or school type"
                        autocomplete="off"
                        value="<?= htmlspecialchars($search) ?>">
                    <button type="submit">Search</button>
                </div>

                <div class="filter-row">
                    <label for="min_fee">Fees (GH₵):</label>
                    <input id="min_fee" type="number" name="min_fee" min="0" step="0.01" placeholder="Min e.g. 500" value="<?= htmlspecialchars($_GET["min_fee"] ?? "") ?>">
                    <span class="filter-to">to</span>
                    <input id="max_fee" type="number" name="max_fee" min="0" step="0.01" placeholder="Max e.g. 3000" value="<?= htmlspecialchars($_GET["max_fee"] ?? "") ?>">
                    <span class="filter-hint">Leave a fee field empty if you do not want to limit it.</span>
                </div>
            </form>
        </section>
    </div>

    <!-- MAIN CONTENT -->
    <main class="container">

        <div class="section-header rise" style="--i:1">
            <h2><?= ($search !== "" || $min_fee !== null || $max_fee !== null) ? "Matching schools" : "Available schools" ?></h2>

            <div class="header-right">
                <?php if ($search !== "" || $min_fee !== null || $max_fee !== null): ?>
                    <a href="schools.php" class="clear-search">✕ Clear filters</a>
                <?php endif; ?>

                <div class="school-count"><?= (int) $school_total ?> school<?= $school_total == 1 ? "" : "s" ?> found</div>
            </div>
        </div>

        <!-- SCHOOL MAP -->
        <section class="map-section rise" style="--i:2" aria-label="Map of available schools">
            <div class="map-heading">
                <h2>Schools on the map</h2>
                <p>Select a marker to view school details or booking options.</p>
            </div>
            <div id="schoolMap" role="application" aria-label="Interactive map showing school locations"></div>
            <p id="mapNotice" style="margin-top:10px;color:#62708a;font-size:13px;"></p>
        </section>

        <!-- SCHOOL GRID -->
        <div class="school-grid">

            <?php if (count($school_list) > 0): ?>
                <?php $card_no = 0; ?>
                <?php foreach ($school_list as $school): ?>

                    <div class="school-card" style="--r:<?= min($card_no++, 8) ?>">

                        <!-- IMAGE -->
                        <div class="school-image">
                            <?php if (!empty($school["image"])): ?>
                                <img
                                    src="<?= htmlspecialchars(strpos($school["image"], "uploads/") === 0 ? $school["image"] : "uploads/" . $school["image"]) ?>"
                                    alt="<?= htmlspecialchars($school["school_name"]) ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <div class="placeholder">🏫</div>
                            <?php endif; ?>

                            <span class="active-badge">Active</span>
                        </div>

                        <!-- CONTENT -->
                        <div class="school-content">

                            <h3 class="school-name"><?= htmlspecialchars($school["school_name"]) ?></h3>

                            <span class="school-type"><?= htmlspecialchars($school["school_type"] ?? "School") ?></span>
                            <?php if (isset($school["school_fees"]) && $school["school_fees"] !== ""): ?>
                                <div class="school-info"><strong>School fees:</strong> GH₵<?= number_format((float) $school["school_fees"], 2) ?></div>
                            <?php endif; ?>

                            <div class="school-info">
                                📍 <?= htmlspecialchars($school["location"]) ?>
                                <?php if (!empty($school["phone"])): ?>
                                    <br>📞 <?= htmlspecialchars($school["phone"]) ?>
                                <?php endif; ?>
                                <?php if (!empty($school["email"])): ?>
                                    <br>✉️ <?= htmlspecialchars($school["email"]) ?>
                                <?php endif; ?>
                            </div>

                            <div class="school-description">
                                <?php
                                if (!empty($school["description"])) {
                                    echo htmlspecialchars($school["description"]);
                                } else {
                                    echo "No description available.";
                                }
                                ?>
                            </div>

                            <div class="card-buttons">
                                <a href="school-details.php?id=<?= (int) $school["id"] ?>" class="view-button">View details</a>
                                <a href="booking.php?school_id=<?= (int) $school["id"] ?>" class="book-button">Book school</a>
                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>
            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">🏫</div>
                    <h3>No schools found</h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            We couldn't find a school matching your search. Try a different name, location or type.
                        <?php else: ?>
                            There are currently no active schools available. Please check back soon.
                        <?php endif; ?>
                    </p>
                </div>

            <?php endif; ?>

        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const schoolLocations = <?= json_encode(array_map(function ($school) {
                                    return [
                                        "id" => (int)($school["id"] ?? 0),
                                        "name" => (string)($school["school_name"] ?? ""),
                                        "location" => (string)($school["location"] ?? ""),
                                        "type" => (string)($school["school_type"] ?? ""),
                                        "fees" => isset($school["school_fees"]) && is_numeric($school["school_fees"]) ? (float)$school["school_fees"] : null,
                                        "lat" => isset($school["latitude"]) && is_numeric($school["latitude"]) ? (float)$school["latitude"] : null,
                                        "lng" => isset($school["longitude"]) && is_numeric($school["longitude"]) ? (float)$school["longitude"] : null
                                    ];
                                }, $school_list), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        const map = L.map("schoolMap").setView([5.6037, -0.1870], 11);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        const bounds = [];
        let markerCount = 0;
        const notice = document.getElementById("mapNotice");

        function escapeHtml(value) {
            return String(value ?? "").replace(/[&<>"']/g, function(c) {
                return {
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;"
                } [c];
            });
        }
        schoolLocations.forEach(function(school) {
            if (school.lat === null || school.lng === null || !Number.isFinite(school.lat) || !Number.isFinite(school.lng) || school.lat < -90 || school.lat > 90 || school.lng < -180 || school.lng > 180) return;
            const marker = L.marker([school.lat, school.lng]).addTo(map);
            const feeText = school.fees === null ? "Fee not provided" : "Fees: GH₵" + school.fees.toLocaleString("en-GH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const popup = '<div class="map-popup-title">' + escapeHtml(school.name) + '</div>' +
                '<div>' + escapeHtml(school.location) + '</div><div>' + escapeHtml(school.type) + '</div>' +
                '<div>' + escapeHtml(feeText) + '</div>' +
                '<a class="map-popup-link" href="school-details.php?id=' + encodeURIComponent(school.id) + '">View details</a>' +
                ' &middot; <a class="map-popup-link" href="booking.php?school_id=' + encodeURIComponent(school.id) + '">Book school</a>';
            marker.bindPopup(popup);
            bounds.push([school.lat, school.lng]);
            markerCount++;
        });
        if (bounds.length === 1) map.setView(bounds[0], 14);
        else if (bounds.length > 1) map.fitBounds(bounds, {
            padding: [30, 30]
        });
        notice.textContent = markerCount ?
            markerCount + " school location(s) shown. Schools without saved coordinates still appear in the list below." :
            "No map coordinates are saved for these results yet. Ask an admin to add each school's map location.";
        window.addEventListener("load", function() {
            map.invalidateSize();
        });
    </script>
</body>

</html>