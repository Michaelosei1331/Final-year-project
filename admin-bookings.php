<?php
session_start();
require_once "db.php";
// =====================================================
// 1. CHECK ADMIN LOGIN
// =====================================================
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: parent-dashboard.php");
    exit;
}
// =====================================================
// 2. UPDATE BOOKING STATUS
// =====================================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (
        isset($_POST["booking_id"]) &&
        isset($_POST["action"])
    ) {
        $booking_id = intval($_POST["booking_id"]);
        $action = $_POST["action"];
        $allowed_statuses = [
            "approved",
            "rejected",
            "cancelled"
        ];
        if (in_array($action, $allowed_statuses)) {
            // =================================================
            // GET BOOKING INFORMATION
            // =================================================
            $booking_stmt = $conn->prepare(
                "
        SELECT
            bookings.user_id,
            bookings.school_id,
            schools.school_name
        FROM bookings
        INNER JOIN schools
            ON bookings.school_id = schools.id
        WHERE bookings.id = ?
        "
            );
            $booking_stmt->bind_param(
                "i",
                $booking_id
            );
            $booking_stmt->execute();
            $booking_result =
                $booking_stmt->get_result();
            $booking_data =
                $booking_result->fetch_assoc();
            $booking_stmt->close();
            // =================================================
            // MAKE SURE BOOKING EXISTS
            // =================================================
            if ($booking_data) {
                $user_id =
                    $booking_data["user_id"];
                $school_name =
                    $booking_data["school_name"];
                // =============================================
                // UPDATE BOOKING STATUS
                // =============================================
                $stmt = $conn->prepare(
                    "
            UPDATE bookings
            SET status = ?
            WHERE id = ?
            "
                );
                $stmt->bind_param(
                    "si",
                    $action,
                    $booking_id
                );
                $stmt->execute();
                $stmt->close();
                // =============================================
                // CREATE NOTIFICATION
                // =============================================
                if ($action === "approved") {
                    $title =
                        "Booking Approved";
                    $message =
                        "Your booking for " .
                        $school_name .
                        " has been approved.";
                } elseif ($action === "rejected") {
                    $title =
                        "Booking Rejected";
                    $message =
                        "Your booking for " .
                        $school_name .
                        " has been rejected.";
                } elseif ($action === "cancelled") {
                    $title =
                        "Booking Cancelled";
                    $message =
                        "Your booking for " .
                        $school_name .
                        " has been cancelled.";
                }
                // =============================================
                // INSERT NOTIFICATION
                // =============================================
                $notification_stmt =
                    $conn->prepare(
                        "
                INSERT INTO notifications
                (
                    user_id,
                    title,
                    message
                )
                VALUES (?, ?, ?)
                "
                    );
                $notification_stmt->bind_param(
                    "iss",
                    $user_id,
                    $title,
                    $message
                );
                $notification_stmt->execute();
                $notification_stmt->close();
                // =============================================
                // REDIRECT
                // =============================================
                header(
                    "Location: admin-bookings.php?updated=1"
                );
                exit;
            }
        }
    }
}
// =====================================================
// 3. GET FILTER
// =====================================================
$filter = $_GET["status"] ?? "all";
$allowed_filters = [
    "all",
    "pending",
    "approved",
    "rejected",
    "cancelled"
];
if (!in_array($filter, $allowed_filters)) {
    $filter = "all";
}
// =====================================================
// 4. BUILD BOOKING QUERY
// =====================================================
$sql = "
    SELECT
        bookings.id,
        bookings.child_name,
        bookings.child_age,
        bookings.preferred_class,
        bookings.booking_date,
        bookings.message,
        bookings.status,
        bookings.created_at,
        users.full_name,
        users.email,
        users.phone,
        schools.school_name,
        schools.location
    FROM bookings
    INNER JOIN users
        ON bookings.user_id = users.id
    INNER JOIN schools
        ON bookings.school_id = schools.id
";
if ($filter !== "all") {
    $sql .= "
        WHERE bookings.status = ?
    ";
}
$sql .= "
    ORDER BY bookings.created_at DESC
";
if ($filter !== "all") {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "s",
        $filter
    );
    $stmt->execute();
    $bookings = $stmt->get_result();
} else {
    $bookings = $conn->query($sql);
}
// =====================================================
// 5. GET BOOKING COUNTS
// =====================================================
$result = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings"
);
$total_bookings =
    $result->fetch_assoc()["total"];
$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'pending'
    "
);
$pending_bookings =
    $result->fetch_assoc()["total"];
$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'approved'
    "
);
$approved_bookings =
    $result->fetch_assoc()["total"];
$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'rejected'
    "
);
$rejected_bookings =
    $result->fetch_assoc()["total"];
$result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'cancelled'
    "
);
$cancelled_bookings =
    $result->fetch_assoc()["total"];

$counts = [
    "all"       => $total_bookings,
    "pending"   => $pending_bookings,
    "approved"  => $approved_bookings,
    "rejected"  => $rejected_bookings,
    "cancelled" => $cancelled_bookings,
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | SchoolConnect</title>

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

        /* ---------- LAYOUT + ENTRANCE ---------- */
        .container {
            width: 92%;
            max-width: 1280px;
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

        /* ---------- SUCCESS TOAST ---------- */
        .success-message {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #a7e6bd;
            border-left: 5px solid var(--green);
            padding: 14px 18px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 22px;
            animation: toast 4.6s ease both;
        }

        @keyframes toast {
            0% {
                opacity: 0;
                transform: translateY(-12px);
            }

            8% {
                opacity: 1;
                transform: none;
            }

            88% {
                opacity: 1;
                transform: none;
                max-height: 80px;
            }

            100% {
                opacity: 0;
                transform: translateY(-8px);
                max-height: 0;
                padding: 0 18px;
                margin-bottom: 0;
                border-width: 0;
            }
        }

        /* ---------- STATS ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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

        .s-cancelled {
            --c: var(--slate);
        }

        /* ---------- FILTERS ---------- */
        .filter-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 16px 20px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-title {
            font-family: var(--display);
            font-weight: 700;
            font-size: 17px;
        }

        .filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filters a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--muted);
            background: var(--chalk);
            border: 1px solid var(--line);
            padding: 8px 14px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 500;
            transition: background .2s, color .2s, border-color .2s, transform .2s;
        }

        .filters a:hover {
            border-color: var(--blue);
            color: var(--blue);
            transform: translateY(-2px);
        }

        .filters a.active {
            background: var(--ink);
            border-color: var(--ink);
            color: #fff;
        }

        .filters em {
            font-style: normal;
            font-size: 12px;
            font-weight: 700;
            background: rgba(11, 27, 58, .08);
            padding: 1px 8px;
            border-radius: 99px;
        }

        .filters a.active em {
            background: rgba(255, 255, 255, .18);
        }

        /* ---------- TABLE CARD ---------- */
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
            align-items: baseline;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .card-header h2 {
            font-family: var(--display);
            font-size: 19px;
            font-weight: 700;
        }

        .card-header p {
            color: var(--muted);
            font-size: 13px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th {
            text-align: left;
            padding: 14px 18px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            background: var(--chalk);
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        td {
            padding: 18px;
            border-bottom: 1px solid var(--line);
            vertical-align: top;
            font-size: 14px;
        }

        tbody tr {
            animation: rise .6s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(.35s + var(--r, 0) * 45ms);
            transition: background .2s;
        }

        tbody tr:hover {
            background: #f8faff;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .parent-name,
        .school-name,
        .child-name {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 3px;
        }

        .contact,
        .location,
        .child-info,
        .created-date {
            color: var(--muted);
            font-size: 13px;
        }

        .booking-date {
            font-weight: 700;
            margin-bottom: 3px;
            white-space: nowrap;
        }

        .message-box {
            max-width: 240px;
            color: #475569;
            font-size: 13px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
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

        /* ---------- ACTIONS ---------- */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-form {
            display: inline;
        }

        .btn {
            border: none;
            cursor: pointer;
            font-family: var(--body);
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            transition: transform .2s, box-shadow .2s, background .2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn:active {
            transform: scale(.96);
        }

        .btn-approve {
            background: var(--green);
            color: #fff;
        }

        .btn-approve:hover {
            box-shadow: 0 8px 16px -8px rgba(22, 163, 74, .8);
        }

        .btn-reject {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-reject:hover {
            background: var(--red);
            color: #fff;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #334155;
        }

        .btn-cancel:hover {
            background: #334155;
            color: #fff;
        }

        .no-actions {
            color: var(--slate);
            font-size: 13px;
        }

        /* ---------- EMPTY ---------- */
        .empty {
            padding: 70px 20px;
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

        .empty h2 {
            font-family: var(--display);
            font-size: 21px;
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
        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 800px) {

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
                padding: 14px 6px;
                border-bottom: 8px solid var(--chalk);
            }

            td {
                border: none;
                padding: 8px 16px;
            }

            td::before {
                content: attr(data-label);
                display: block;
                font-size: 12px;
                font-weight: 700;
                color: var(--muted);
                margin-bottom: 2px;
            }

            .message-box {
                max-width: none;
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
        <div class="logo">🎓 School<span>Connect</span> Admin</div>

        <div class="nav-links">
            <a href="admin-dashboard.php">📊 Dashboard</a>
            <a href="admin-schools.php">🏫 Schools</a>
            <a href="admin-bookings.php" class="active">📅 Bookings</a>
            <a href="admin-parents.php">👨‍👩‍👧 Parents</a>
            <a href="notifications.php">🔔 Notifications</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">

        <!-- PAGE HEADER -->
        <div class="page-header rise" style="--i:0">
            <h1>Booking management</h1>
            <p>Review and manage school booking requests submitted by parents.</p>
        </div>

        <!-- SUCCESS MESSAGE -->
        <?php if (isset($_GET["updated"])): ?>
            <div class="success-message" role="status">✅ Booking status updated. The parent has been notified.</div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <section class="stats">
            <div class="stat s-total rise" style="--i:1">
                <div class="stat-icon">📅</div>
                <div class="stat-number" data-count="<?= (int) $total_bookings ?>"><?= (int) $total_bookings ?></div>
                <div class="stat-label">Total bookings</div>
            </div>
            <div class="stat s-pending rise" style="--i:2">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" data-count="<?= (int) $pending_bookings ?>"><?= (int) $pending_bookings ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat s-approved rise" style="--i:3">
                <div class="stat-icon">✅</div>
                <div class="stat-number" data-count="<?= (int) $approved_bookings ?>"><?= (int) $approved_bookings ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat s-rejected rise" style="--i:4">
                <div class="stat-icon">❌</div>
                <div class="stat-number" data-count="<?= (int) $rejected_bookings ?>"><?= (int) $rejected_bookings ?></div>
                <div class="stat-label">Rejected</div>
            </div>
            <div class="stat s-cancelled rise" style="--i:5">
                <div class="stat-icon">🚫</div>
                <div class="stat-number" data-count="<?= (int) $cancelled_bookings ?>"><?= (int) $cancelled_bookings ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
        </section>

        <!-- FILTER -->
        <div class="filter-card rise" style="--i:6">
            <div class="filter-title">Filter bookings</div>

            <div class="filters">
                <a href="admin-bookings.php" class="<?= $filter === "all" ? "active" : "" ?>">All <em><?= (int) $counts["all"] ?></em></a>
                <a href="admin-bookings.php?status=pending" class="<?= $filter === "pending" ? "active" : "" ?>">⏳ Pending <em><?= (int) $counts["pending"] ?></em></a>
                <a href="admin-bookings.php?status=approved" class="<?= $filter === "approved" ? "active" : "" ?>">✅ Approved <em><?= (int) $counts["approved"] ?></em></a>
                <a href="admin-bookings.php?status=rejected" class="<?= $filter === "rejected" ? "active" : "" ?>">❌ Rejected <em><?= (int) $counts["rejected"] ?></em></a>
                <a href="admin-bookings.php?status=cancelled" class="<?= $filter === "cancelled" ? "active" : "" ?>">🚫 Cancelled <em><?= (int) $counts["cancelled"] ?></em></a>
            </div>
        </div>

        <!-- BOOKINGS -->
        <div class="bookings-card rise" style="--i:7">

            <div class="card-header">
                <h2>Booking requests</h2>
                <p>
                    <?php
                    if ($filter === "all") {
                        echo "Showing all bookings.";
                    } else {
                        echo "Showing " . htmlspecialchars($filter) . " bookings.";
                    }
                    ?>
                </p>
            </div>

            <?php if ($bookings && $bookings->num_rows > 0): ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Parent</th>
                                <th>School</th>
                                <th>Child</th>
                                <th>Booking date</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $row_no = 0; ?>
                            <?php while ($booking = $bookings->fetch_assoc()): ?>

                                <tr style="--r:<?= min($row_no++, 12) ?>">

                                    <td data-label="Parent">
                                        <div class="parent-name"><?= htmlspecialchars($booking["full_name"]) ?></div>
                                        <div class="contact">
                                            ✉️ <?= htmlspecialchars($booking["email"]) ?>
                                            <?php if (!empty($booking["phone"])): ?>
                                                <br>📞 <?= htmlspecialchars($booking["phone"]) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td data-label="School">
                                        <div class="school-name"><?= htmlspecialchars($booking["school_name"]) ?></div>
                                        <div class="location">📍 <?= htmlspecialchars($booking["location"]) ?></div>
                                    </td>

                                    <td data-label="Child">
                                        <div class="child-name"><?= htmlspecialchars($booking["child_name"]) ?></div>
                                        <div class="child-info">
                                            <?php if (!empty($booking["child_age"])): ?>
                                                Age: <?= htmlspecialchars($booking["child_age"]) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($booking["preferred_class"])): ?>
                                                Class: <?= htmlspecialchars($booking["preferred_class"]) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td data-label="Booking date">
                                        <div class="booking-date">
                                            <?php
                                            if (!empty($booking["booking_date"])) {
                                                echo date("d M Y", strtotime($booking["booking_date"]));
                                            } else {
                                                echo "Not specified";
                                            }
                                            ?>
                                        </div>
                                        <div class="created-date">Created: <?= date("d M Y", strtotime($booking["created_at"])) ?></div>
                                    </td>

                                    <td data-label="Message">
                                        <div class="message-box" title="<?= htmlspecialchars($booking["message"] ?? "") ?>">
                                            <?php
                                            if (!empty($booking["message"])) {
                                                echo htmlspecialchars($booking["message"]);
                                            } else {
                                                echo "No message provided.";
                                            }
                                            ?>
                                        </div>
                                    </td>

                                    <td data-label="Status">
                                        <span class="status status-<?= htmlspecialchars($booking["status"]) ?>">
                                            <?= htmlspecialchars($booking["status"]) ?>
                                        </span>
                                    </td>

                                    <td data-label="Actions">
                                        <div class="actions">

                                            <?php if ($booking["status"] === "pending"): ?>

                                                <!-- APPROVE -->
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="booking_id" value="<?= (int) $booking["id"] ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this booking?');">✓ Approve</button>
                                                </form>

                                                <!-- REJECT -->
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="booking_id" value="<?= (int) $booking["id"] ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this booking?');">✕ Reject</button>
                                                </form>

                                            <?php elseif ($booking["status"] === "approved"): ?>

                                                <!-- CANCEL -->
                                                <form method="POST" class="action-form">
                                                    <input type="hidden" name="booking_id" value="<?= (int) $booking["id"] ?>">
                                                    <input type="hidden" name="action" value="cancelled">
                                                    <button type="submit" class="btn btn-cancel" onclick="return confirm('Cancel this booking?');">Cancel booking</button>
                                                </form>

                                            <?php else: ?>

                                                <span class="no-actions">No actions</span>

                                            <?php endif; ?>

                                        </div>
                                    </td>

                                </tr>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">📭</div>
                    <h2>No bookings found</h2>
                    <p>There are no bookings matching the selected filter. Try choosing "All".</p>
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

            // Drop ?updated=1 so the message doesn't return on refresh
            if (location.search.indexOf("updated=1") !== -1 && window.history.replaceState) {
                var url = location.search.replace(/([?&])updated=1&?/, "$1").replace(/[?&]$/, "");
                history.replaceState(null, "", location.pathname + url);
            }
        })();
    </script>

</body>

</html>