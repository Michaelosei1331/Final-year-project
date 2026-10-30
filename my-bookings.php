<?php
session_start();
require_once "db.php";
// =====================================================
// 1. CHECK IF USER IS LOGGED IN
// =====================================================
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
// =====================================================
// 2. GET LOGGED-IN USER ID
// =====================================================
$user_id = intval($_SESSION["user_id"]);
// =====================================================
// 3. GET THIS PARENT'S BOOKINGS
// =====================================================
$sql = "
    SELECT
        bookings.*,
        schools.school_name,
        schools.location,
        schools.phone,
        schools.email,
        schools.image
    FROM bookings
    INNER JOIN schools
        ON bookings.school_id = schools.id
    WHERE bookings.user_id = ?
    ORDER BY bookings.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$bookings = $stmt->get_result();
// =====================================================
// 4. COUNT DIFFERENT BOOKING STATUSES
// =====================================================
$count_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(status = 'pending') AS pending,
        SUM(status = 'approved') AS approved,
        SUM(status = 'rejected') AS rejected,
        SUM(status = 'cancelled') AS cancelled
    FROM bookings
    WHERE user_id = ?
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param(
    "i",
    $user_id
);
$count_stmt->execute();
$count_result =
    $count_stmt->get_result();
$counts =
    $count_result->fetch_assoc();

// =====================================================
// 5. UNREAD NOTIFICATIONS (for the nav badge)
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
    $notification_stmt->bind_param("i", $user_id);
    $notification_stmt->execute();
    $unread_notifications = $notification_stmt->get_result()->fetch_assoc()["total"];
    $notification_stmt->close();
}

$status_notes = [
    "pending"   => "Waiting for the school's decision.",
    "approved"  => "Approved. You can view the school's information.",
    "rejected"  => "The school couldn't accept this request.",
    "cancelled" => "This booking was cancelled.",
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | SchoolConnect</title>

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
            --slate: #94a3b8;
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
        button:focus-visible {
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

        /* ---------- LAYOUT + ENTRANCE ---------- */
        .container {
            width: 90%;
            max-width: 1000px;
            margin: 32px auto 60px;
        }

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

        /* ---------- HEADER ---------- */
        .page-header {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(135deg, var(--ink) 0%, #0b3a86 100%);
            color: #fff;
            border-radius: 22px;
            padding: 34px 40px;
            margin-bottom: 22px;
            box-shadow: 0 18px 40px -18px rgba(11, 27, 58, .55);
        }

        .page-header::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -90px;
            width: 260px;
            height: 260px;
            border-radius: 50%;
            border: 42px solid rgba(245, 179, 1, .16);
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

        .page-header>* {
            position: relative;
            z-index: 1;
        }

        .page-header h1 {
            font-family: var(--display);
            font-size: clamp(25px, 4vw, 34px);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #c3d3f0;
            font-size: 15px;
            max-width: 52ch;
        }

        /* ---------- STATS ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }

        .stat {
            background: #fff;
            border: 1px solid var(--line);
            border-bottom: 4px solid var(--c, var(--blue));
            border-radius: 16px;
            padding: 18px 20px;
            transition: transform .25s, box-shadow .25s;
        }

        .stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 30px -16px rgba(11, 27, 58, .25);
        }

        .stat-icon {
            font-size: 22px;
            margin-bottom: 10px;
            display: inline-block;
            transition: transform .3s;
        }

        .stat:hover .stat-icon {
            transform: rotate(-8deg) scale(1.15);
        }

        .stat-number {
            font-family: var(--display);
            font-size: 34px;
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }

        .s-total {
            --c: var(--blue);
        }

        .s-pending {
            --c: var(--sun);
        }

        .s-approved {
            --c: var(--green);
        }

        .s-rejected {
            --c: var(--red);
        }

        /* ---------- FILTER CHIPS ---------- */
        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filters button {
            font-family: var(--body);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            color: var(--muted);
            background: #fff;
            border: 1px solid var(--line);
            padding: 8px 16px;
            border-radius: 99px;
            transition: background .2s, color .2s, border-color .2s, transform .2s;
        }

        .filters button:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .filters button.on {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }

        /* ---------- BOOKING CARD ---------- */
        .booking-card {
            --c: var(--slate);
            position: relative;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 24px 26px 24px 30px;
            margin-bottom: 18px;
            overflow: hidden;
            animation: rise .65s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(.4s + var(--r, 0) * 80ms);
            transition: transform .3s, box-shadow .3s;
        }

        .booking-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
            background: var(--c);
        }

        .booking-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 22px 38px -24px rgba(11, 27, 58, .4);
        }

        .booking-card.pending {
            --c: var(--sun);
        }

        .booking-card.approved {
            --c: var(--green);
        }

        .booking-card.rejected {
            --c: var(--red);
        }

        .booking-card.cancelled {
            --c: var(--slate);
        }

        .booking-top {
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 20px;
        }

        .school-image {
            width: 110px;
            height: 90px;
            flex-shrink: 0;
            border-radius: 14px;
            overflow: hidden;
            background: var(--sky);
        }

        .school-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .5s;
        }

        .booking-card:hover .school-image img {
            transform: scale(1.1);
        }

        .placeholder {
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 36px;
            background: linear-gradient(135deg, var(--sky), #cfe1fa);
        }

        .school-info {
            flex: 1;
            min-width: 0;
        }

        .school-info h2 {
            font-family: var(--display);
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: 1.2;
            margin-bottom: 4px;
        }

        .location {
            color: var(--muted);
            font-size: 14px;
        }

        .booking-id {
            color: var(--muted);
            font-size: 12px;
            margin: 2px 0 10px;
        }

        .status-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .status-note {
            color: var(--muted);
            font-size: 13px;
        }

        /* ---------- STATUS ---------- */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pending::before {
            animation: blink 1.6s ease-in-out infinite;
        }

        .status-approved {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-cancelled {
            background: #e2e8f0;
            color: #475569;
        }

        @keyframes blink {
            50% {
                opacity: .25;
            }
        }

        /* ---------- DETAILS ---------- */
        .booking-details {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            background: var(--chalk);
            border-radius: 14px;
            padding: 16px 18px;
        }

        .detail-label {
            display: block;
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 700;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .booking-message {
            margin-top: 16px;
            padding: 14px 18px;
            border-radius: 14px;
            background: #fffaf0;
            border: 1px solid #fde9b0;
        }

        .booking-message h4 {
            font-family: var(--display);
            font-size: 15px;
            margin-bottom: 4px;
        }

        .booking-message p {
            color: #475569;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* ---------- ACTIONS ---------- */
        .booking-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .button {
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 18px;
            border-radius: 12px;
            transition: transform .2s, background .2s, color .2s, box-shadow .2s;
        }

        .button:hover {
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

        .school-button {
            background: var(--ink);
            color: #fff;
        }

        .school-button:hover {
            background: var(--blue);
            box-shadow: 0 10px 20px -10px rgba(7, 86, 184, .8);
        }

        /* ---------- EMPTY ---------- */
        .empty,
        .no-match {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 70px 20px;
            text-align: center;
        }

        .no-match {
            display: none;
            padding: 40px 20px;
            color: var(--muted);
            font-size: 14px;
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

        .empty h2 {
            font-family: var(--display);
            font-size: 22px;
            margin-bottom: 6px;
        }

        .empty p {
            color: var(--muted);
            font-size: 14px;
            max-width: 42ch;
            margin: 0 auto 22px;
        }

        .find-school {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            transition: transform .2s, background .2s;
        }

        .find-school:hover {
            background: var(--blue);
            transform: translateY(-2px);
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
        @media (max-width: 800px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }

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

            .container {
                width: 94%;
                margin-top: 20px;
            }

            .page-header {
                padding: 26px 24px;
            }

            .booking-card {
                padding: 20px 18px 20px 24px;
            }

            .booking-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .school-image {
                width: 100%;
                height: 140px;
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

    <!-- NAVIGATION -->
    <nav class="navbar">
        <div class="logo">🎓 School<span>Connect</span></div>

        <div class="nav-links">
            <a href="parent-dashboard.php">🏠 Dashboard</a>
            <a href="schools.php">🏫 Schools</a>
            <a href="my-bookings.php" class="active">📅 My Bookings</a>

            <a href="notifications.php">
                🔔 Notifications
                <?php if ($unread_notifications > 0): ?>
                    <span class="notification-badge"><?= $unread_notifications > 99 ? "99+" : (int) $unread_notifications ?></span>
                <?php endif; ?>
            </a>

            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">

        <!-- PAGE TITLE -->
        <div class="page-header rise" style="--i:0">
            <h1>My bookings</h1>
            <p>View and track all your school booking requests.</p>
        </div>

        <!-- STATISTICS -->
        <div class="stats">
            <div class="stat s-total rise" style="--i:1">
                <div class="stat-icon">📋</div>
                <div class="stat-number" data-count="<?= (int) ($counts["total"] ?? 0) ?>"><?= (int) ($counts["total"] ?? 0) ?></div>
                <div class="stat-label">Total bookings</div>
            </div>

            <div class="stat s-pending rise" style="--i:2">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" data-count="<?= (int) ($counts["pending"] ?? 0) ?>"><?= (int) ($counts["pending"] ?? 0) ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat s-approved rise" style="--i:3">
                <div class="stat-icon">✅</div>
                <div class="stat-number" data-count="<?= (int) ($counts["approved"] ?? 0) ?>"><?= (int) ($counts["approved"] ?? 0) ?></div>
                <div class="stat-label">Approved</div>
            </div>

            <div class="stat s-rejected rise" style="--i:4">
                <div class="stat-icon">❌</div>
                <div class="stat-number" data-count="<?= (int) ($counts["rejected"] ?? 0) ?>"><?= (int) ($counts["rejected"] ?? 0) ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- BOOKINGS -->
        <?php if ($bookings && $bookings->num_rows > 0): ?>

            <div class="filters rise" style="--i:5" id="filters">
                <button type="button" class="on" data-filter="all">All</button>
                <button type="button" data-filter="pending">⏳ Pending</button>
                <button type="button" data-filter="approved">✅ Approved</button>
                <button type="button" data-filter="rejected">❌ Rejected</button>
                <button type="button" data-filter="cancelled">🚫 Cancelled</button>
            </div>

            <div id="bookingList">
                <?php $card_no = 0; ?>
                <?php while ($booking = $bookings->fetch_assoc()): ?>

                    <?php $status = $booking["status"]; ?>

                    <div class="booking-card <?= htmlspecialchars($status) ?>" data-status="<?= htmlspecialchars($status) ?>" style="--r:<?= min($card_no++, 6) ?>">

                        <!-- SCHOOL INFORMATION -->
                        <div class="booking-top">

                            <div class="school-image">
                                <?php if (!empty($booking["image"])): ?>
                                    <img src="uploads/<?= htmlspecialchars($booking["image"]) ?>" alt="<?= htmlspecialchars($booking["school_name"]) ?>" loading="lazy">
                                <?php else: ?>
                                    <div class="placeholder">🏫</div>
                                <?php endif; ?>
                            </div>

                            <div class="school-info">
                                <h2><?= htmlspecialchars($booking["school_name"]) ?></h2>
                                <div class="location">📍 <?= htmlspecialchars($booking["location"]) ?></div>
                                <div class="booking-id">Booking ID: #<?= (int) $booking["id"] ?></div>

                                <div class="status-row">
                                    <span class="status status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                                    <?php if (isset($status_notes[$status])): ?>
                                        <span class="status-note"><?= htmlspecialchars($status_notes[$status]) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>

                        <!-- BOOKING DETAILS -->
                        <div class="booking-details">

                            <div class="detail">
                                <span class="detail-label">Child</span>
                                <span class="detail-value"><?= htmlspecialchars($booking["child_name"]) ?></span>
                            </div>

                            <div class="detail">
                                <span class="detail-label">Age</span>
                                <span class="detail-value">
                                    <?php
                                    if (!empty($booking["child_age"])) {
                                        echo htmlspecialchars($booking["child_age"]) . " years";
                                    } else {
                                        echo "Not specified";
                                    }
                                    ?>
                                </span>
                            </div>

                            <div class="detail">
                                <span class="detail-label">Preferred class</span>
                                <span class="detail-value">
                                    <?= !empty($booking["preferred_class"]) ? htmlspecialchars($booking["preferred_class"]) : "Not specified" ?>
                                </span>
                            </div>

                            <div class="detail">
                                <span class="detail-label">Booking date</span>
                                <span class="detail-value">
                                    <?php
                                    if (!empty($booking["booking_date"])) {
                                        echo date("d M Y", strtotime($booking["booking_date"]));
                                    } else {
                                        echo "Not specified";
                                    }
                                    ?>
                                </span>
                            </div>

                        </div>

                        <!-- MESSAGE -->
                        <?php if (!empty($booking["message"])): ?>
                            <div class="booking-message">
                                <h4>📝 Your message</h4>
                                <p><?= nl2br(htmlspecialchars($booking["message"])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- ACTIONS -->
                        <div class="booking-actions">
                            <a href="school-details.php?id=<?= (int) $booking["school_id"] ?>" class="button view-button">👁️ View school</a>

                            <?php if ($status === "approved"): ?>
                                <a href="school-details.php?id=<?= (int) $booking["school_id"] ?>" class="button school-button">🏫 School information</a>
                            <?php endif; ?>
                        </div>

                    </div>

                <?php endwhile; ?>
            </div>

            <div class="no-match" id="noMatch">You have no bookings with this status.</div>

        <?php else: ?>

            <div class="empty rise" style="--i:5">
                <div class="empty-icon">📭</div>
                <h2>You have no bookings yet</h2>
                <p>Find a school and submit your first booking request. Updates will show up here.</p>
                <a href="schools.php" class="find-school">🏫 Find a school</a>
            </div>

        <?php endif; ?>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <script>
        (function() {
            // Count-up for the stat numbers (plain PHP value shows if JS is off)
            if (!window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
                document.querySelectorAll("[data-count]").forEach(function(el) {
                    var target = parseInt(el.dataset.count, 10) || 0;
                    if (target === 0) return;

                    var duration = 1100,
                        start = null;
                    el.textContent = "0";

                    function tick(t) {
                        if (start === null) start = t;
                        var p = Math.min((t - start) / duration, 1);
                        el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
                        if (p < 1) requestAnimationFrame(tick);
                    }
                    setTimeout(function() {
                        requestAnimationFrame(tick);
                    }, 250);
                });
            }

            // Filter the cards already on the page by status
            var filters = document.getElementById("filters");
            if (!filters) return;

            var cards = document.querySelectorAll("#bookingList .booking-card");
            var noMatch = document.getElementById("noMatch");

            filters.addEventListener("click", function(e) {
                var btn = e.target.closest("button[data-filter]");
                if (!btn) return;

                filters.querySelectorAll("button").forEach(function(b) {
                    b.classList.remove("on");
                });
                btn.classList.add("on");

                var wanted = btn.dataset.filter,
                    shown = 0;

                cards.forEach(function(card) {
                    var hit = wanted === "all" || card.dataset.status === wanted;
                    card.style.display = hit ? "" : "none";
                    if (hit) shown++;
                });

                noMatch.style.display = shown === 0 ? "block" : "none";
            });
        })();
    </script>

</body>

</html>