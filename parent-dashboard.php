<?php
session_start();
require_once "db.php";
// =====================================================
// 1. CHECK LOGIN
// =====================================================
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Send institutions and admins to their own dashboards
$role_home = [
    "institution" => "institution-dashboard.php",
    "admin"       => "admin-dashboard.php",
];
$current_role = $_SESSION["role"] ?? "parent";
if (isset($role_home[$current_role])) {
    header("Location: " . $role_home[$current_role]);
    exit;
}
// =====================================================
// 2. GET USER INFORMATION
// =====================================================
$user_id = intval($_SESSION["user_id"]);
// Get parent information
$user_stmt = $conn->prepare(
    "
    SELECT
        id,
        full_name,
        email,
        phone
    FROM users
    WHERE id = ?
    "
);
$user_stmt->bind_param(
    "i",
    $user_id
);
$user_stmt->execute();
$user_result =
    $user_stmt->get_result();
$user = $user_result->fetch_assoc();
$user_stmt->close();
if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}
// =====================================================
// 3. GET BOOKING STATISTICS
// =====================================================
// Total bookings
$stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE user_id = ?
    "
);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$result =
    $stmt->get_result();
$total_bookings =
    $result->fetch_assoc()["total"];
$stmt->close();
// Pending
$stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE user_id = ?
    AND status = 'pending'
    "
);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$result =
    $stmt->get_result();
$pending_bookings =
    $result->fetch_assoc()["total"];
$stmt->close();
// Approved
$stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE user_id = ?
    AND status = 'approved'
    "
);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$result =
    $stmt->get_result();
$approved_bookings =
    $result->fetch_assoc()["total"];
$stmt->close();
// Rejected
$stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE user_id = ?
    AND status = 'rejected'
    "
);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$result =
    $stmt->get_result();
$rejected_bookings =
    $result->fetch_assoc()["total"];
$stmt->close();
// Cancelled
$stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE user_id = ?
    AND status = 'cancelled'
    "
);
$stmt->bind_param(
    "i",
    $user_id
);
$stmt->execute();
$result =
    $stmt->get_result();
$cancelled_bookings =
    $result->fetch_assoc()["total"];
$stmt->close();
// =====================================================
// 4. GET UNREAD NOTIFICATIONS
// =====================================================
$notification_stmt = $conn->prepare(
    "
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE user_id = ?
    AND is_read = FALSE
    "
);
$notification_stmt->bind_param(
    "i",
    $user_id
);
$notification_stmt->execute();
$notification_result =
    $notification_stmt->get_result();
$unread_notifications =
    $notification_result
        ->fetch_assoc()["total"];
$notification_stmt->close();
// =====================================================
// 5. GET RECENT BOOKINGS
// =====================================================
$booking_stmt = $conn->prepare(
    "
    SELECT
        bookings.id,
        bookings.child_name,
        bookings.child_age,
        bookings.preferred_class,
        bookings.booking_date,
        bookings.status,
        bookings.created_at,
        schools.school_name,
        schools.location
    FROM bookings
    INNER JOIN schools
        ON bookings.school_id = schools.id
    WHERE bookings.user_id = ?
    ORDER BY bookings.created_at DESC
    LIMIT 5
    "
);
$booking_stmt->bind_param(
    "i",
    $user_id
);
$booking_stmt->execute();
$recent_bookings =
    $booking_stmt->get_result();

// =====================================================
// 5B. VERIFIED SCHOOLS (only admin-approved schools are shown)
// =====================================================
$verified_count = 0;
$count_result = $conn->query(
    "SELECT COUNT(*) AS total FROM schools WHERE approval_status = 'approved' AND status = 'active'"
);
if ($count_result) {
    $verified_count = (int) $count_result->fetch_assoc()["total"];
}

$verified_schools = $conn->query(
    "SELECT id, school_name, location, description
     FROM schools
     WHERE approval_status = 'approved' AND status = 'active'
     ORDER BY COALESCE(reviewed_at, created_at) DESC
     LIMIT 6"
);

// =====================================================
// 6. EXTRA VALUES FOR THE NEW UI
// =====================================================

$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 17 ? "Good afternoon" : "Good evening");

$name_parts = explode(" ", trim($user["full_name"]));
$first_name = $name_parts[0];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard | SchoolConnect</title>

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

        a:focus-visible {
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
            max-width: 1200px;
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

        /* ---------- WELCOME ---------- */
        .welcome {
            position: relative;
            overflow: hidden;
            background:
                linear-gradient(rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(90deg, rgba(255, 255, 255, .05) 1px, transparent 1px) 0 0 / 34px 34px,
                linear-gradient(135deg, var(--ink) 0%, #0b3a86 100%);
            color: #fff;
            border-radius: 22px;
            padding: 38px 40px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            box-shadow: 0 18px 40px -18px rgba(11, 27, 58, .55);
        }

        .welcome::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -90px;
            width: 280px;
            height: 280px;
            border-radius: 50%;
            border: 44px solid rgba(245, 179, 1, .16);
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

        .welcome>* {
            position: relative;
            z-index: 1;
        }

        .welcome h1 {
            font-family: var(--display);
            font-size: clamp(26px, 4vw, 38px);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .wave {
            display: inline-block;
            transform-origin: 70% 70%;
            animation: wave 1.6s ease .7s 1 both;
        }

        @keyframes wave {

            0%,
            100% {
                transform: rotate(0);
            }

            20% {
                transform: rotate(16deg);
            }

            40% {
                transform: rotate(-10deg);
            }

            60% {
                transform: rotate(14deg);
            }

            80% {
                transform: rotate(-4deg);
            }
        }

        .welcome p {
            color: #c3d3f0;
            font-size: 15px;
            max-width: 50ch;
        }

        .welcome-email {
            display: inline-block;
            margin-top: 16px;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .18);
            padding: 5px 13px;
            border-radius: 99px;
            font-size: 13px;
            color: #dbe6fa;
            word-break: break-all;
        }

        .hero-cta {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--sun);
            color: var(--ink);
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 14px 20px;
            border-radius: 12px;
            transition: transform .2s, box-shadow .2s;
        }

        .hero-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -8px rgba(245, 179, 1, .8);
        }

        /* ---------- STATS ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 34px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--line);
            border-bottom: 4px solid var(--c, var(--blue));
            border-radius: 16px;
            padding: 18px 20px;
            transition: transform .25s, box-shadow .25s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 30px -16px rgba(11, 27, 58, .25);
        }

        .stat-icon {
            font-size: 22px;
            margin-bottom: 10px;
            display: inline-block;
            transition: transform .3s;
        }

        .stat-card:hover .stat-icon {
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

        .c-total {
            --c: var(--blue);
        }

        .c-pending {
            --c: var(--sun);
        }

        .c-approved {
            --c: var(--green);
        }

        .c-rejected {
            --c: var(--red);
        }

        .c-unread {
            --c: #7c3aed;
        }

        /* ---------- SECTION TITLE ---------- */
        .section-title {
            margin-bottom: 16px;
        }

        .section-title h2 {
            font-family: var(--display);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .section-title p {
            color: var(--muted);
            font-size: 14px;
        }

        /* ---------- QUICK ACTIONS ---------- */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 34px;
        }

        .quick-card {
            position: relative;
            background: #fff;
            border: 1px solid var(--line);
            padding: 24px;
            border-radius: 18px;
            text-decoration: none;
            color: var(--ink);
            overflow: hidden;
            transition: transform .25s, box-shadow .25s, color .25s, border-color .25s;
        }

        .quick-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background: var(--ink);
            transform: translateY(100%);
            transition: transform .35s cubic-bezier(.2, .7, .2, 1);
        }

        .quick-card>* {
            position: relative;
        }

        .quick-card:hover {
            transform: translateY(-4px);
            color: #fff;
            border-color: var(--ink);
            box-shadow: 0 18px 30px -16px rgba(11, 27, 58, .5);
        }

        .quick-card:hover::before {
            transform: none;
        }

        .quick-card:hover p {
            color: #b7c6e6;
        }

        .quick-icon {
            font-size: 30px;
            margin-bottom: 12px;
        }

        .quick-card h3 {
            font-family: var(--display);
            font-size: 18px;
            margin-bottom: 4px;
        }

        .quick-card p {
            color: var(--muted);
            font-size: 14px;
            transition: color .25s;
        }

        /* ---------- BOOKINGS ---------- */
        .bookings-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-family: var(--display);
            font-size: 19px;
            font-weight: 700;
        }

        .view-all {
            color: var(--blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background .2s;
        }

        .view-all:hover {
            background: var(--sky);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 640px;
        }

        th {
            text-align: left;
            padding: 14px 24px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            background: var(--chalk);
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        td {
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
            font-size: 14px;
        }

        tbody tr {
            animation: rise .6s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(.45s + var(--r, 0) * 60ms);
            transition: background .2s;
        }

        tbody tr:hover {
            background: #f8faff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .school-name,
        .child-name {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 2px;
        }

        .location,
        .child-info {
            color: var(--muted);
            font-size: 13px;
        }

        .date-cell {
            font-weight: 500;
            white-space: nowrap;
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

        /* ---------- EMPTY ---------- */
        .empty {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 52px;
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
            font-size: 21px;
            margin-bottom: 6px;
        }

        .empty p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .primary-button {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            transition: transform .2s, background .2s;
        }

        .primary-button:hover {
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
        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 800px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .welcome {
                flex-direction: column;
                align-items: flex-start;
            }

            table,
            thead,
            tbody,
            tr,
            td {
                display: block;
                min-width: 0;
                width: 100%;
            }

            thead {
                display: none;
            }

            tbody tr {
                padding: 12px 4px;
                border-bottom: 8px solid var(--chalk);
            }

            td {
                border: none;
                padding: 8px 20px;
            }

            td[data-label]::before {
                content: attr(data-label);
                display: block;
                font-size: 12px;
                font-weight: 700;
                color: var(--muted);
                margin-bottom: 2px;
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

            .welcome {
                padding: 28px 24px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
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
            <a href="parent-dashboard.php" class="active">🏠 Dashboard</a>
            <a href="schools.php">🏫 Schools</a>
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

    <main class="container">

        <!-- WELCOME -->
        <section class="welcome rise" style="--i:0">
            <div>
                <h1><?= $greeting ?>, <?= htmlspecialchars($first_name) ?> <span class="wave">👋</span></h1>

                <p>
                    <?php if ($pending_bookings > 0): ?>
                        <?= (int) $pending_bookings ?> of your booking request<?= $pending_bookings == 1 ? " is" : "s are" ?> waiting for a decision.
                    <?php elseif ($total_bookings == 0): ?>
                        Find a school you like and send your first booking request.
                    <?php else: ?>
                        Manage your school bookings and keep track of your applications.
                    <?php endif; ?>
                </p>

                <div class="welcome-email">✉️ <?= htmlspecialchars($user["email"]) ?></div>
            </div>

            <a class="hero-cta" href="schools.php">🏫 Find a school</a>
        </section>

        <!-- STATISTICS -->
        <section class="stats">
            <div class="stat-card c-total rise" style="--i:1">
                <div class="stat-icon">📅</div>
                <div class="stat-number" data-count="<?= (int) $total_bookings ?>"><?= (int) $total_bookings ?></div>
                <div class="stat-label">Total bookings</div>
            </div>

            <div class="stat-card c-pending rise" style="--i:2">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" data-count="<?= (int) $pending_bookings ?>"><?= (int) $pending_bookings ?></div>
                <div class="stat-label">Pending</div>
            </div>

            <div class="stat-card c-approved rise" style="--i:3">
                <div class="stat-icon">✅</div>
                <div class="stat-number" data-count="<?= (int) $approved_bookings ?>"><?= (int) $approved_bookings ?></div>
                <div class="stat-label">Approved</div>
            </div>

            <div class="stat-card c-rejected rise" style="--i:4">
                <div class="stat-icon">❌</div>
                <div class="stat-number" data-count="<?= (int) $rejected_bookings ?>"><?= (int) $rejected_bookings ?></div>
                <div class="stat-label">Rejected</div>
            </div>

            <div class="stat-card c-unread rise" style="--i:5">
                <div class="stat-icon">🔔</div>
                <div class="stat-number" data-count="<?= (int) $unread_notifications ?>"><?= (int) $unread_notifications ?></div>
                <div class="stat-label">Unread notifications</div>
            </div>
        </section>

        <!-- QUICK ACTIONS -->
        <div class="section-title rise" style="--i:6">
            <h2>Quick actions</h2>
            <p>Jump to the most important parts of your account.</p>
        </div>

        <section class="quick-actions rise" style="--i:7">
            <a href="schools.php" class="quick-card">
                <div class="quick-icon">🏫</div>
                <h3>Find a school</h3>
                <p>Browse available schools and submit a booking request.</p>
            </a>

            <a href="my-bookings.php" class="quick-card">
                <div class="quick-icon">📅</div>
                <h3>My bookings</h3>
                <p>View and monitor all your school booking requests.</p>
            </a>

            <a href="notifications.php" class="quick-card">
                <div class="quick-icon">🔔</div>
                <h3>Notifications</h3>
                <p>View updates about your booking applications.</p>
            </a>
        </section>

        <!-- VERIFIED SCHOOLS -->
        <div class="bookings-card rise" style="--i:8; margin-bottom: 24px;">
            <div class="card-header">
                <h2>✅ Verified schools (<?= $verified_count ?>)</h2>
                <a href="schools.php" class="view-all">Browse all →</a>
            </div>

            <?php if ($verified_schools && $verified_schools->num_rows > 0): ?>
                <section class="quick-actions" style="padding: 0 24px 24px;">
                    <?php while ($school = $verified_schools->fetch_assoc()): ?>
                        <a href="schools.php?id=<?= (int) $school["id"] ?>" class="quick-card">
                            <div class="quick-icon">🏫</div>
                            <h3><?= htmlspecialchars($school["school_name"]) ?></h3>
                            <p>📍 <?= htmlspecialchars($school["location"]) ?></p>
                            <?php if (!empty($school["description"])): ?>
                                <p><?= htmlspecialchars(mb_strimwidth($school["description"], 0, 90, "…")) ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                </section>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">🏫</div>
                    <h3>No verified schools yet</h3>
                    <p>Schools appear here once an admin has confirmed they exist.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- RECENT BOOKINGS -->
        <div class="bookings-card rise" style="--i:8">

            <div class="card-header">
                <h2>📋 Recent bookings</h2>
                <a href="my-bookings.php" class="view-all">View all →</a>
            </div>

            <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>School</th>
                                <th>Child</th>
                                <th>Booking date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $row_no = 0; ?>
                            <?php while ($booking = $recent_bookings->fetch_assoc()): ?>

                                <tr style="--r:<?= $row_no++ ?>">

                                    <td data-label="School">
                                        <div class="school-name"><?= htmlspecialchars($booking["school_name"]) ?></div>
                                        <div class="location">📍 <?= htmlspecialchars($booking["location"]) ?></div>
                                    </td>

                                    <td data-label="Child">
                                        <div class="child-name"><?= htmlspecialchars($booking["child_name"]) ?></div>
                                        <div class="child-info">
                                            <?php
                                            if (!empty($booking["child_age"])) {
                                                echo "Age: " . htmlspecialchars($booking["child_age"]);
                                            }
                                            if (!empty($booking["preferred_class"])) {
                                                echo " • " . htmlspecialchars($booking["preferred_class"]);
                                            }
                                            ?>
                                        </div>
                                    </td>

                                    <td data-label="Booking date" class="date-cell">
                                        <?php
                                        if (!empty($booking["booking_date"])) {
                                            echo date("d M Y", strtotime($booking["booking_date"]));
                                        } else {
                                            echo "Not specified";
                                        }
                                        ?>
                                    </td>

                                    <td data-label="Status">
                                        <span class="status status-<?= htmlspecialchars($booking["status"]) ?>">
                                            <?= htmlspecialchars($booking["status"]) ?>
                                        </span>
                                    </td>

                                </tr>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">📅</div>
                    <h3>No bookings yet</h3>
                    <p>You haven't submitted any school booking requests.</p>
                    <a href="schools.php" class="primary-button">🏫 Find a school</a>
                </div>

            <?php endif; ?>

        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <script>
        // Count-up for the stat numbers (plain PHP value shows if JS is off)
        (function() {
            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

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
        })();
    </script>

</body>

</html>