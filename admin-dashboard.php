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
// 2. CHECK IF USER IS ADMIN
// =====================================================

if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {

    header("Location: parent-dashboard.php");
    exit;
}


// =====================================================
// 3. GET TOTAL SCHOOLS
// =====================================================

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM schools WHERE approval_status = 'approved'"
);

$total_schools =
    $result->fetch_assoc()["total"];


// =====================================================
// 4. GET ACTIVE SCHOOLS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM schools
    WHERE status = 'active'
    AND approval_status = 'approved'
    "
);

$active_schools =
    $result->fetch_assoc()["total"];


// =====================================================
// 4B. SCHOOLS WAITING FOR VERIFICATION
// =====================================================

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM schools WHERE approval_status = 'pending'"
);

$pending_schools = $result ? (int) $result->fetch_assoc()["total"] : 0;


// =====================================================
// 5. GET TOTAL PARENTS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'parent'
    "
);

$total_parents =
    $result->fetch_assoc()["total"];


// =====================================================
// 6. GET TOTAL BOOKINGS
// =====================================================

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings"
);

$total_bookings =
    $result->fetch_assoc()["total"];


// =====================================================
// 7. GET PENDING BOOKINGS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'pending'
    "
);

$pending_bookings =
    $result->fetch_assoc()["total"];


// =====================================================
// 8. GET APPROVED BOOKINGS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'approved'
    "
);

$approved_bookings =
    $result->fetch_assoc()["total"];


// =====================================================
// 9. GET REJECTED BOOKINGS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'rejected'
    "
);

$rejected_bookings =
    $result->fetch_assoc()["total"];


// =====================================================
// 10. GET CANCELLED BOOKINGS
// =====================================================

$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'cancelled'
    "
);

$cancelled_bookings =
    $result->fetch_assoc()["total"];


// =====================================================
// 11. GET RECENT BOOKINGS
// =====================================================

$recent_sql = "
    SELECT

        bookings.id,
        bookings.child_name,
        bookings.child_age,
        bookings.preferred_class,
        bookings.status,
        bookings.booking_date,
        bookings.created_at,

        users.full_name,
        users.email,

        schools.school_name,
        schools.location

    FROM bookings

    INNER JOIN users
        ON bookings.user_id = users.id

    INNER JOIN schools
        ON bookings.school_id = schools.id

    ORDER BY bookings.created_at DESC

    LIMIT 8
";

$recent_bookings =
    $conn->query($recent_sql);


// =====================================================
// 12. GET RECENT PARENTS
// =====================================================

$recent_parents_sql = "
    SELECT
        id,
        full_name,
        email,
        phone,
        created_at

    FROM users

    WHERE role = 'parent'

    ORDER BY created_at DESC

    LIMIT 5
";

$recent_parents =
    $conn->query($recent_parents_sql);

// =====================================================
// 13. EXTRA VALUES FOR THE NEW UI (greeting + breakdown)
// =====================================================

$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 17 ? "Good afternoon" : "Good evening");

$status_total = max(
    1,
    $pending_bookings + $approved_bookings +
        $rejected_bookings + $cancelled_bookings
);

$pct = function ($n) use ($status_total) {
    return round(($n / $status_total) * 100, 1);
};

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SchoolConnect</title>

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
            background: rgba(11, 27, 58, 0.94);
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
            letter-spacing: -0.01em;
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

        /* ---------- LAYOUT ---------- */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 32px auto 60px;
        }

        /* ---------- ENTRANCE (one staggered load sequence) ---------- */
        .rise {
            animation: rise .7s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(var(--i, 0) * 90ms);
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

        /* ---------- HERO ---------- */
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
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #c3d3f0;
            font-size: 15px;
            max-width: 46ch;
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

        .pulse {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--ink);
            box-shadow: 0 0 0 0 rgba(11, 27, 58, .5);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            70% {
                box-shadow: 0 0 0 10px rgba(11, 27, 58, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(11, 27, 58, 0);
            }
        }

        /* ---------- STATS ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat {
            background: #fff;
            padding: 22px;
            border-radius: 18px;
            border: 1px solid var(--line);
            transition: transform .25s, box-shadow .25s;
        }

        .stat:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 30px -16px rgba(11, 27, 58, .25);
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 13px;
            display: grid;
            place-items: center;
            font-size: 22px;
            background: var(--sky);
            margin-bottom: 18px;
            transition: transform .3s;
        }

        .stat:hover .stat-icon {
            transform: rotate(-8deg) scale(1.08);
        }

        .stat-number {
            font-family: var(--display);
            font-size: 38px;
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }

        .stat-sub {
            margin-top: 10px;
            font-size: 12px;
            font-weight: 700;
            color: var(--green);
        }

        .stat.warn {
            background: #fffaf0;
            border-color: #fde9b0;
        }

        .stat.warn .stat-icon {
            background: #fdeaa8;
        }

        /* ---------- BREAKDOWN ---------- */
        .breakdown {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px 24px;
            margin-bottom: 22px;
        }

        .breakdown h2,
        .card-header h2 {
            font-family: var(--display);
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .bar {
            display: flex;
            height: 14px;
            border-radius: 99px;
            overflow: hidden;
            background: var(--chalk);
            margin: 16px 0 18px;
            gap: 3px;
        }

        .bar span {
            display: block;
            height: 100%;
            width: var(--w);
            animation: grow 1.1s cubic-bezier(.2, .7, .2, 1) .5s both;
        }

        @keyframes grow {
            from {
                width: 0;
            }
        }

        .legend {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .legend div {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--muted);
        }

        .legend b {
            font-family: var(--display);
            font-size: 20px;
            color: var(--ink);
        }

        .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .c-pending {
            background: var(--sun);
        }

        .c-approved {
            background: var(--green);
        }

        .c-rejected {
            background: var(--red);
        }

        .c-cancelled {
            background: var(--slate);
        }

        /* ---------- CARDS ---------- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 22px;
            align-items: start;
        }

        .card {
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

        .card-header a {
            color: var(--blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background .2s;
        }

        .card-header a:hover {
            background: var(--sky);
        }

        .booking-list,
        .parent-list {
            padding: 6px 12px;
        }

        .booking,
        .parent {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px 12px;
            border-radius: 12px;
            transition: background .2s;
        }

        .booking {
            justify-content: space-between;
        }

        .booking:hover,
        .parent:hover {
            background: var(--chalk);
        }

        .booking-info {
            flex: 1;
            min-width: 0;
        }

        .booking-school {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 3px;
        }

        .booking-parent,
        .booking-child {
            color: var(--muted);
            font-size: 13px;
        }

        .booking-date {
            color: var(--muted);
            font-size: 12px;
            margin-top: 3px;
        }

        /* ---------- STATUS ---------- */
        .status {
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
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

        /* ---------- PARENTS ---------- */
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-family: var(--display);
            font-weight: 700;
            flex-shrink: 0;
            background: var(--sky);
            color: var(--blue);
        }

        .av-1 {
            background: #fdeaa8;
            color: #7a5600;
        }

        .av-2 {
            background: #d6f5e0;
            color: #166534;
        }

        .av-3 {
            background: #fde0e0;
            color: #9b1c1c;
        }

        .av-4 {
            background: #e6defc;
            color: #5b2fc1;
        }

        .parent-name {
            font-weight: 700;
            font-size: 15px;
        }

        .parent-email {
            color: var(--muted);
            font-size: 13px;
            word-break: break-all;
        }

        /* ---------- QUICK ACTIONS ---------- */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 22px;
        }

        .action {
            position: relative;
            background: #fff;
            border: 1px solid var(--line);
            padding: 22px;
            border-radius: 18px;
            text-decoration: none;
            color: var(--ink);
            overflow: hidden;
            transition: transform .25s, box-shadow .25s, color .25s, border-color .25s;
        }

        .action::before {
            content: "";
            position: absolute;
            inset: 0;
            background: var(--ink);
            transform: translateY(100%);
            transition: transform .35s cubic-bezier(.2, .7, .2, 1);
        }

        .action>* {
            position: relative;
        }

        .action:hover {
            transform: translateY(-4px);
            color: #fff;
            border-color: var(--ink);
            box-shadow: 0 18px 30px -16px rgba(11, 27, 58, .5);
        }

        .action:hover::before {
            transform: none;
        }

        .action:hover p {
            color: #b7c6e6;
        }

        .action-icon {
            font-size: 28px;
            margin-bottom: 12px;
        }

        .action h3 {
            font-family: var(--display);
            font-size: 17px;
            margin-bottom: 4px;
        }

        .action p {
            color: var(--muted);
            font-size: 13px;
            transition: color .25s;
        }

        .empty {
            padding: 45px 20px;
            text-align: center;
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
        @media (max-width: 950px) {

            .stats,
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .welcome {
                flex-direction: column;
                align-items: flex-start;
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

            .stats,
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .legend {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
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
        <div class="logo">🎓 School<span>Connect</span> Admin</div>

        <div class="nav-links">
            <a href="admin-dashboard.php" class="active">📊 Dashboard</a>
            <a href="admin-schools.php">🏫 Schools</a>
            <a href="admin-bookings.php">📅 Bookings</a>
            <a href="admin-parents.php">👨‍👩‍👧 Parents</a>
            <a href="notifications.php">🔔 Notifications</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">

        <!-- WELCOME -->
        <section class="welcome rise" style="--i:0">
            <div>
                <h1><?= $greeting ?>, Administrator 👋</h1>
                <p>
                    <?php if ($pending_bookings > 0): ?>
                        <?= (int) $pending_bookings ?> booking request<?= $pending_bookings == 1 ? "" : "s" ?> waiting for your review.
                    <?php else: ?>
                        You're all caught up. There are no pending booking requests.
                    <?php endif; ?>
                    <?php if ($pending_schools > 0): ?>
                        <br><?= $pending_schools ?> school<?= $pending_schools == 1 ? "" : "s" ?> waiting to be verified.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($pending_bookings > 0): ?>
                <a class="hero-cta" href="admin-bookings.php">
                    <span class="pulse"></span> Review pending bookings
                </a>
            <?php endif; ?>

            <?php if ($pending_schools > 0): ?>
                <a class="hero-cta" href="admin-schools.php">
                    <span class="pulse"></span> Verify new schools
                </a>
            <?php endif; ?>
        </section>

        <!-- MAIN STATISTICS -->
        <section class="stats">

            <div class="stat rise" style="--i:1">
                <div class="stat-icon">🏫</div>
                <div class="stat-number" data-count="<?= (int) $total_schools ?>"><?= (int) $total_schools ?></div>
                <div class="stat-label">Total schools</div>
                <div class="stat-sub"><?= (int) $active_schools ?> active<?php if ($pending_schools > 0): ?> · <a href="admin-schools.php" style="color:#b45309">⏳ <?= $pending_schools ?> awaiting verification</a><?php endif; ?></div>
            </div>

            <div class="stat rise" style="--i:2">
                <div class="stat-icon">👨‍👩‍👧</div>
                <div class="stat-number" data-count="<?= (int) $total_parents ?>"><?= (int) $total_parents ?></div>
                <div class="stat-label">Registered parents</div>
            </div>

            <div class="stat rise" style="--i:3">
                <div class="stat-icon">📅</div>
                <div class="stat-number" data-count="<?= (int) $total_bookings ?>"><?= (int) $total_bookings ?></div>
                <div class="stat-label">Total bookings</div>
            </div>

            <div class="stat warn rise" style="--i:4">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" data-count="<?= (int) $pending_bookings ?>"><?= (int) $pending_bookings ?></div>
                <div class="stat-label">Pending bookings</div>
            </div>

        </section>

        <!-- BOOKING BREAKDOWN -->
        <section class="breakdown rise" style="--i:5">
            <h2>Booking breakdown</h2>

            <div class="bar" role="img" aria-label="Share of bookings by status">
                <span class="c-pending" style="--w:<?= $pct($pending_bookings) ?>%"></span>
                <span class="c-approved" style="--w:<?= $pct($approved_bookings) ?>%"></span>
                <span class="c-rejected" style="--w:<?= $pct($rejected_bookings) ?>%"></span>
                <span class="c-cancelled" style="--w:<?= $pct($cancelled_bookings) ?>%"></span>
            </div>

            <div class="legend">
                <div><i class="dot c-pending"></i><b data-count="<?= (int) $pending_bookings ?>"><?= (int) $pending_bookings ?></b> Pending</div>
                <div><i class="dot c-approved"></i><b data-count="<?= (int) $approved_bookings ?>"><?= (int) $approved_bookings ?></b> Approved</div>
                <div><i class="dot c-rejected"></i><b data-count="<?= (int) $rejected_bookings ?>"><?= (int) $rejected_bookings ?></b> Rejected</div>
                <div><i class="dot c-cancelled"></i><b data-count="<?= (int) $cancelled_bookings ?>"><?= (int) $cancelled_bookings ?></b> Cancelled</div>
            </div>
        </section>

        <!-- DASHBOARD GRID -->
        <section class="dashboard-grid">

            <!-- RECENT BOOKINGS -->
            <div class="card">
                <div class="card-header">
                    <h2>📅 Recent bookings</h2>
                    <a href="admin-bookings.php">View all →</a>
                </div>

                <div class="booking-list">
                    <?php if ($recent_bookings && $recent_bookings->num_rows > 0): ?>
                        <?php while ($booking = $recent_bookings->fetch_assoc()): ?>

                            <div class="booking">
                                <div class="booking-info">
                                    <div class="booking-school">🏫 <?= htmlspecialchars($booking["school_name"]) ?></div>
                                    <div class="booking-parent">Parent: <?= htmlspecialchars($booking["full_name"]) ?></div>
                                    <div class="booking-child">
                                        Child: <?= htmlspecialchars($booking["child_name"]) ?>
                                        <?php if (!empty($booking["preferred_class"])): ?>
                                            • <?= htmlspecialchars($booking["preferred_class"]) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($booking["booking_date"])): ?>
                                        <div class="booking-date">📆 <?= htmlspecialchars(date("j M Y", strtotime($booking["booking_date"]))) ?></div>
                                    <?php endif; ?>
                                </div>

                                <span class="status status-<?= htmlspecialchars($booking["status"]) ?>">
                                    <?= htmlspecialchars($booking["status"]) ?>
                                </span>
                            </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty">📭<br><br>No bookings yet. New requests will appear here.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RECENT PARENTS -->
            <div class="card">
                <div class="card-header">
                    <h2>👨‍👩‍👧 Recent parents</h2>
                    <a href="admin-parents.php">View all →</a>
                </div>

                <div class="parent-list">
                    <?php if ($recent_parents && $recent_parents->num_rows > 0): ?>
                        <?php while ($parent = $recent_parents->fetch_assoc()): ?>

                            <div class="parent">
                                <div class="avatar av-<?= ord(strtoupper(substr($parent["full_name"], 0, 1))) % 5 ?>">
                                    <?= htmlspecialchars(strtoupper(substr($parent["full_name"], 0, 1))) ?>
                                </div>
                                <div>
                                    <div class="parent-name"><?= htmlspecialchars($parent["full_name"]) ?></div>
                                    <div class="parent-email"><?= htmlspecialchars($parent["email"]) ?></div>
                                </div>
                            </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty">No parents registered yet.</div>
                    <?php endif; ?>
                </div>
            </div>

        </section>

        <!-- QUICK ACTIONS -->
        <section class="quick-actions">

            <a href="admin-schools.php" class="action">
                <div class="action-icon">🏫</div>
                <h3>Manage schools</h3>
                <p>Add, edit or remove schools.</p>
            </a>

            <a href="admin-bookings.php" class="action">
                <div class="action-icon">📅</div>
                <h3>Manage bookings</h3>
                <p>Review and update booking requests.</p>
            </a>

            <a href="admin-parents.php" class="action">
                <div class="action-icon">👨‍👩‍👧</div>
                <h3>Manage parents</h3>
                <p>View registered parents.</p>
            </a>

            <a href="admin-schools.php" class="action">
                <div class="action-icon">➕</div>
                <h3>Add school</h3>
                <p>Add a new school to the platform.</p>
            </a>

        </section>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <script>
        // Count-up for the numbers (falls back to the plain PHP value if JS is off)
        (function() {
            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

            document.querySelectorAll("[data-count]").forEach(function(el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                if (target === 0) return;

                var duration = 1200,
                    start = null;
                el.textContent = "0";

                function tick(t) {
                    if (start === null) start = t;
                    var p = Math.min((t - start) / duration, 1);
                    var eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased);
                    if (p < 1) requestAnimationFrame(tick);
                }
                setTimeout(function() {
                    requestAnimationFrame(tick);
                }, 300);
            });
        })();
    </script>

</body>

</html>