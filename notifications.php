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
$user_id = intval($_SESSION["user_id"]);
$role = $_SESSION["role"] ?? "parent";
$is_admin = ($role === "admin");
$is_institution = ($role === "institution");

// =====================================================
// 2. CSRF (for the mark-all form)
// =====================================================
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

// =====================================================
// 3. MARK ONE NOTIFICATION AS READ
// =====================================================
if (isset($_GET["read"]) && ctype_digit($_GET["read"])) {
    $nid = intval($_GET["read"]);
    $stmt = $conn->prepare(
        "UPDATE notifications SET is_read = TRUE
         WHERE id = ? AND user_id = ?"
    );
    $stmt->bind_param("ii", $nid, $user_id);
    $stmt->execute();
    $stmt->close();

    // Preserve filter when redirecting back
    $filter = $_GET["filter"] ?? "all";
    header("Location: notifications.php?filter=" . urlencode($filter));
    exit;
}

// =====================================================
// 4. MARK ALL NOTIFICATIONS AS READ
// =====================================================
if (isset($_GET["mark_all"]) && $_GET["mark_all"] === "1") {
    $stmt = $conn->prepare(
        "UPDATE notifications SET is_read = TRUE WHERE user_id = ?"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: notifications.php");
    exit;
}

// =====================================================
// 5. FILTER (all / unread)
// =====================================================
$filter = $_GET["filter"] ?? "all";
$where = "user_id = ?";
if ($filter === "unread") {
    $where .= " AND is_read = FALSE";
}

// =====================================================
// 6. GET NOTIFICATIONS
// =====================================================
$stmt = $conn->prepare(
    "SELECT id, title, message, is_read, created_at
     FROM notifications
     WHERE $where
     ORDER BY created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

// =====================================================
// 7. UNREAD COUNT (for badge)
// =====================================================
$count_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id = ? AND is_read = FALSE"
);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = (int) $count_stmt->get_result()->fetch_assoc()["total"];
$count_stmt->close();

// Total count (for "All" tab)
$total_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?"
);
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_count = (int) $total_stmt->get_result()->fetch_assoc()["total"];
$total_stmt->close();

// =====================================================
// 8. TIME-AGO HELPER
// =====================================================
function time_ago($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . " min ago";
    if ($diff < 86400) return floor($diff / 3600) . " hr ago";
    if ($diff < 172800) return "Yesterday";
    if ($diff < 604800) return floor($diff / 86400) . " days ago";
    return date("d M Y", strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | SchoolConnect</title>

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
            flex-wrap: wrap;
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

        .nav-badge {
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
            max-width: 860px;
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
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
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .bell {
            display: inline-block;
            transform-origin: 50% 0;
            animation: ring 1.4s ease .8s 1 both;
        }

        @keyframes ring {

            0%,
            100% {
                transform: rotate(0);
            }

            15% {
                transform: rotate(16deg);
            }

            30% {
                transform: rotate(-14deg);
            }

            45% {
                transform: rotate(10deg);
            }

            60% {
                transform: rotate(-8deg);
            }

            75% {
                transform: rotate(4deg);
            }
        }

        .notification-count {
            font-family: var(--body);
            font-size: 13px;
            font-weight: 700;
            background: var(--sun);
            color: var(--ink);
            padding: 4px 12px;
            border-radius: 99px;
        }

        .page-header p {
            color: #c3d3f0;
            font-size: 15px;
        }

        .mark-read {
            flex-shrink: 0;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .25);
            padding: 11px 18px;
            border-radius: 12px;
            transition: background .2s, color .2s, transform .2s;
        }

        .mark-read:hover {
            background: var(--sun);
            color: var(--ink);
            border-color: var(--sun);
            transform: translateY(-2px);
        }

        /* ---------- FILTERS ---------- */
        .filter-row {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            color: var(--muted);
            border: 1px solid var(--line);
            background: #fff;
            transition: all .2s;
        }

        .filter-pill:hover {
            border-color: var(--blue);
            color: var(--blue);
        }

        .filter-pill.active {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        /* ---------- LIST ---------- */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification {
            --c: var(--blue);
            --t: var(--sky);
            position: relative;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 18px 20px 18px 22px;
            animation: rise .6s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(.3s + var(--r, 0) * 70ms);
            transition: transform .25s, box-shadow .25s;
            text-decoration: none;
            color: inherit;
        }

        .notification:hover {
            transform: translateX(4px);
            box-shadow: 0 14px 28px -18px rgba(11, 27, 58, .35);
        }

        .notification.approved {
            --c: var(--green);
            --t: #dcfce7;
        }

        .notification.rejected {
            --c: var(--red);
            --t: #fee2e2;
        }

        .notification.cancelled {
            --c: var(--slate);
            --t: #e2e8f0;
        }

        .notification.application {
            --c: #7c3aed;
            --t: #ede9fe;
        }

        .notification.unread {
            border-color: color-mix(in srgb, var(--c) 35%, white);
            background: linear-gradient(90deg, color-mix(in srgb, var(--t) 55%, white), #fff 45%);
        }

        .notification.unread::before {
            content: "";
            position: absolute;
            left: 0;
            top: 14px;
            bottom: 14px;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: var(--c);
        }

        .notification-icon {
            width: 46px;
            height: 46px;
            flex-shrink: 0;
            border-radius: 14px;
            display: grid;
            place-items: center;
            font-size: 22px;
            background: var(--t);
            transition: transform .3s;
        }

        .notification:hover .notification-icon {
            transform: rotate(-8deg) scale(1.08);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-family: var(--display);
            font-size: 17px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 4px;
        }

        .unread-label {
            font-family: var(--body);
            font-size: 11px;
            font-weight: 700;
            background: var(--c);
            color: #fff;
            padding: 2px 9px;
            border-radius: 99px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 color-mix(in srgb, var(--c) 45%, transparent);
            }

            70% {
                box-shadow: 0 0 0 8px transparent;
            }

            100% {
                box-shadow: 0 0 0 0 transparent;
            }
        }

        .notification-message {
            color: #475569;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .notification-date {
            color: var(--muted);
            font-size: 12px;
            margin-top: 8px;
        }

        /* ---------- EMPTY ---------- */
        .empty {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
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
                flex-direction: column;
                align-items: flex-start;
            }

            .notification {
                padding: 16px;
                gap: 12px;
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

    <!-- NAVIGATION (role-aware) -->
    <nav class="navbar">
        <div class="logo">
            🎓 School<span>Connect</span><?= $is_admin ? " Admin" : "" ?>
        </div>

        <div class="nav-links">
            <?php if ($is_admin): ?>
                <a href="admin-dashboard.php">📊 Dashboard</a>
                <a href="admin-schools.php">🏫 Schools</a>
                <a href="admin-bookings.php">📅 Bookings</a>
                <a href="admin-parents.php">👨‍👩‍👧 Parents</a>
            <?php elseif ($is_institution): ?>
                <a href="institution-dashboard.php">🏠 Dashboard</a>
                <a href="submit-school.php">➕ List a school</a>
            <?php else: ?>
                <a href="parent-dashboard.php">🏠 Dashboard</a>
                <a href="schools.php">🏫 Schools</a>
                <a href="my-bookings.php">📅 My Bookings</a>
            <?php endif; ?>

            <a href="notifications.php" class="active">
                🔔 Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="nav-badge"><?= $unread_count > 99 ? "99+" : (int) $unread_count ?></span>
                <?php endif; ?>
            </a>

            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">

        <!-- PAGE HEADER -->
        <div class="page-header rise" style="--i:0">
            <div>
                <h1>
                    <span class="bell">🔔</span> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-count"><?= (int) $unread_count ?> new</span>
                    <?php endif; ?>
                </h1>
                <p>
                    <?php if ($is_admin): ?>
                        Updates about booking activity on the platform.
                    <?php elseif ($is_institution): ?>
                        Updates about your school listings and admission applications.
                    <?php else: ?>
                        Stay updated about your school bookings.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?mark_all=1" class="mark-read">✓ Mark all as read</a>
            <?php endif; ?>
        </div>

        <!-- FILTER PILLS -->
        <div class="filter-row rise" style="--i:1">
            <a href="notifications.php?filter=all"
                class="filter-pill <?= $filter !== 'unread' ? 'active' : '' ?>">
                All (<?= (int) $total_count ?>)
            </a>
            <a href="notifications.php?filter=unread"
                class="filter-pill <?= $filter === 'unread' ? 'active' : '' ?>">
                Unread (<?= (int) $unread_count ?>)
            </a>
        </div>

        <!-- NOTIFICATIONS -->
        <?php if ($notifications && $notifications->num_rows > 0): ?>

            <div class="notification-list">
                <?php $row_no = 0; ?>
                <?php while ($notification = $notifications->fetch_assoc()): ?>

                    <?php
                    $title = strtolower($notification["title"]);

                    if (strpos($title, "approved") !== false) {
                        $type = "approved";
                        $icon = "✅";
                    } elseif (strpos($title, "rejected") !== false) {
                        $type = "rejected";
                        $icon = "❌";
                    } elseif (strpos($title, "cancelled") !== false) {
                        $type = "cancelled";
                        $icon = "🚫";
                    } elseif (strpos($title, "application") !== false || strpos($title, "admission") !== false) {
                        $type = "application";
                        $icon = "📨";
                    } else {
                        $type = "general";
                        $icon = "🔔";
                    }
                    ?>

                    <a class="notification <?= $type ?> <?= !$notification["is_read"] ? "unread" : "" ?>"
                        style="--r:<?= min($row_no++, 10) ?>"
                        href="notifications.php?read=<?= (int) $notification["id"] ?>&filter=<?= urlencode($filter) ?>">

                        <div class="notification-icon"><?= $icon ?></div>

                        <div class="notification-content">
                            <div class="notification-title">
                                <?= htmlspecialchars($notification["title"]) ?>
                                <?php if (!$notification["is_read"]): ?>
                                    <span class="unread-label">New</span>
                                <?php endif; ?>
                            </div>

                            <div class="notification-message">
                                <?= nl2br(htmlspecialchars($notification["message"])) ?>
                            </div>

                            <div class="notification-date"
                                title="<?= date("d M Y, h:i A", strtotime($notification["created_at"])) ?>">
                                🕒 <?= time_ago($notification["created_at"]) ?>
                                &nbsp;•&nbsp; <?= date("d M Y, h:i A", strtotime($notification["created_at"])) ?>
                            </div>
                        </div>

                    </a>

                <?php endwhile; ?>
            </div>

        <?php else: ?>

            <div class="empty rise" style="--i:2">
                <div class="empty-icon">🔔</div>
                <h2>No notifications<?= $filter === 'unread' ? " unread" : "" ?></h2>
                <p>
                    <?php if ($is_institution): ?>
                        Updates about your school listings and admission requests will appear here.
                    <?php else: ?>
                        You will see updates about your bookings here.
                    <?php endif; ?>
                </p>
            </div>

        <?php endif; ?>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

</body>

</html>