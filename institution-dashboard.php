<?php
session_start();
require_once "db.php";

// ---------- Institutions only ----------
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "institution") {
    header("Location: login.php");
    exit;
}
$user_id = intval($_SESSION["user_id"]);
$name = $_SESSION["full_name"] ?? "Institution";

// ---------- Counts by approval status ----------
$counts = ["pending" => 0, "approved" => 0, "rejected" => 0];
$c = $conn->prepare("SELECT approval_status, COUNT(*) AS total FROM schools WHERE submitted_by = ? GROUP BY approval_status");
$c->bind_param("i", $user_id);
$c->execute();
$res = $c->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row["approval_status"]] = (int) $row["total"];
}
$c->close();

// ---------- This institution's schools ----------
$l = $conn->prepare(
    "SELECT id, school_name, location, school_type, approval_status, review_note, created_at
     FROM schools WHERE submitted_by = ? ORDER BY created_at DESC"
);
$l->bind_param("i", $user_id);
$l->execute();
$schools = $l->get_result();

// ---------- Unread notifications ----------
$unread_notifications = 0;
$n = $conn->prepare(
    "SELECT COUNT(*) AS total FROM notifications
     WHERE user_id = ? AND is_read = FALSE"
);
if ($n) {
    $n->bind_param("i", $user_id);
    $n->execute();
    $unread_notifications = (int) $n->get_result()->fetch_assoc()["total"];
    $n->close();
}

// ---------- Total admission applications ----------
$total_applications = 0;
$app_count_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM bookings b
     INNER JOIN schools s ON b.school_id = s.id
     WHERE s.submitted_by = ?"
);
if ($app_count_stmt) {
    $app_count_stmt->bind_param("i", $user_id);
    $app_count_stmt->execute();
    $total_applications = (int) $app_count_stmt->get_result()->fetch_assoc()["total"];
    $app_count_stmt->close();
}

$total_pending_applications = 0;
$app_pending_stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM bookings b
     INNER JOIN schools s ON b.school_id = s.id
     WHERE s.submitted_by = ? AND b.status = 'pending'"
);
if ($app_pending_stmt) {
    $app_pending_stmt->bind_param("i", $user_id);
    $app_pending_stmt->execute();
    $total_pending_applications = (int) $app_pending_stmt->get_result()->fetch_assoc()["total"];
    $app_pending_stmt->close();
}

// ---------- Recent notifications (latest 6) ----------
$recent_notifications = null;
$nlist = $conn->prepare(
    "SELECT id, title, message, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 6"
);
if ($nlist) {
    $nlist->bind_param("i", $user_id);
    $nlist->execute();
    $recent_notifications = $nlist->get_result();
}

// ---------- Recent admission applications (latest 6) ----------
$recent_applications = null;
$app_stmt = $conn->prepare(
    "SELECT b.id, b.child_name, b.child_age, b.preferred_class,
            b.booking_date, b.status, b.created_at,
            s.school_name, u.full_name AS parent_name, u.email AS parent_email
     FROM bookings b
     INNER JOIN schools s ON b.school_id = s.id
     INNER JOIN users u ON b.user_id = u.id
     WHERE s.submitted_by = ?
     ORDER BY b.created_at DESC
     LIMIT 6"
);
if ($app_stmt) {
    $app_stmt->bind_param("i", $user_id);
    $app_stmt->execute();
    $recent_applications = $app_stmt->get_result();
}

// ---------- UI greeting ----------
$hour = (int) date("G");
$greeting = $hour < 12 ? "Good morning" : ($hour < 17 ? "Good afternoon" : "Good evening");
$name_parts = explode(" ", trim($name));
$first_name = $name_parts[0] !== "" ? $name_parts[0] : "Institution";

function initials($full_name)
{
    $parts = preg_split('/\s+/', trim($full_name));
    $out = "";
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= strtoupper(substr($p, 0, 1));
    }
    return $out !== "" ? $out : "IN";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institution Dashboard | SchoolConnect</title>

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
            color: #fff;
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

        .c-apps {
            --c: #7c3aed;
        }

        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .card-header h2 {
            font-family: var(--display);
            font-size: 19px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .sub-row {
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
            transition: background .2s;
        }

        .sub-row:hover {
            background: #f8faff;
        }

        .sub-row:last-child {
            border-bottom: none;
        }

        .sub-row h3 {
            font-family: var(--display);
            font-size: 17px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .sub-row .meta {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }

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

        .notif-list {
            display: flex;
            flex-direction: column;
        }

        .notif-item {
            padding: 16px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            gap: 14px;
            align-items: flex-start;
            transition: background .2s;
        }

        .notif-item:last-child {
            border-bottom: none;
        }

        .notif-item:hover {
            background: #f8faff;
        }

        .notif-item.unread {
            background: #f0f6ff;
        }

        .notif-avatar {
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 18px;
            background: var(--sky);
            color: var(--blue);
        }

        .notif-body {
            flex: 1;
            min-width: 0;
        }

        .notif-body h4 {
            font-family: var(--display);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .notif-body p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.45;
            overflow-wrap: anywhere;
        }

        .notif-time {
            color: var(--slate);
            font-size: 12px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .dot-new {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--blue);
            display: inline-block;
        }

        .app-row {
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 0.9fr;
            gap: 14px;
            align-items: center;
            transition: background .2s;
        }

        .app-row:hover {
            background: #f8faff;
        }

        .app-row:last-child {
            border-bottom: none;
        }

        .app-school {
            font-family: var(--display);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .app-meta {
            color: var(--muted);
            font-size: 13px;
        }

        .app-parent {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--ink));
            color: #fff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 13px;
            flex-shrink: 0;
        }

        .avatar-name {
            font-weight: 700;
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .avatar-email {
            color: var(--muted);
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .empty {
            padding: 50px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 48px;
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
            font-size: 20px;
            margin-bottom: 6px;
        }

        .empty p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 18px;
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

        .school-edit-button {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid transparent;
        }

        .alert-warn {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .footer {
            background: var(--ink);
            text-align: center;
            padding: 26px;
        }

        .footer p {
            color: #8fa1c4;
            font-size: 13px;
        }

        @media (max-width: 1000px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
            }

            .app-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 800px) {
            .app-row {
                grid-template-columns: 1fr;
                gap: 10px;
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

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .card-header,
            .notif-item,
            .sub-row,
            .app-row {
                padding-left: 18px;
                padding-right: 18px;
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

    <nav class="navbar">
        <div class="logo">🎓 School<span>Connect</span></div>

        <div class="nav-links">
            <a href="institution-dashboard.php" class="active">🏠 Dashboard</a>
            <a href="submit-school.php">➕ List a school</a>
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

        <section class="welcome rise" style="--i:0">
            <div>
                <h1><?= $greeting ?>, <?= htmlspecialchars($first_name) ?> <span class="wave">👋</span></h1>
                <p>
                    <?php if ($total_pending_applications > 0): ?>
                        You have <strong><?= (int) $total_pending_applications ?></strong> new admission application<?= $total_pending_applications == 1 ? "" : "s" ?> waiting for your review.
                    <?php elseif ($unread_notifications > 0): ?>
                        You have <strong><?= (int) $unread_notifications ?></strong> unread notification<?= $unread_notifications == 1 ? "" : "s" ?>.
                    <?php else: ?>
                        Manage the schools you've listed, review admission applications and stay up to date.
                    <?php endif; ?>
                </p>
                <div class="welcome-email">🏫 Institution account · <?= htmlspecialchars($_SESSION["email"] ?? "Institution") ?></div>
            </div>

            <a class="hero-cta" href="submit-school.php">➕ List a new school</a>
        </section>

        <section class="stats">
            <div class="stat-card c-total rise" style="--i:1">
                <div class="stat-icon">🏫</div>
                <div class="stat-number" data-count="<?= array_sum($counts) ?>"><?= array_sum($counts) ?></div>
                <div class="stat-label">Total schools listed</div>
            </div>

            <div class="stat-card c-pending rise" style="--i:2">
                <div class="stat-icon">⏳</div>
                <div class="stat-number" data-count="<?= (int) $counts["pending"] ?>"><?= (int) $counts["pending"] ?></div>
                <div class="stat-label">Awaiting verification</div>
            </div>

            <div class="stat-card c-approved rise" style="--i:3">
                <div class="stat-icon">✅</div>
                <div class="stat-number" data-count="<?= (int) $counts["approved"] ?>"><?= (int) $counts["approved"] ?></div>
                <div class="stat-label">Live for parents</div>
            </div>

            <div class="stat-card c-rejected rise" style="--i:4">
                <div class="stat-icon">❌</div>
                <div class="stat-number" data-count="<?= (int) $counts["rejected"] ?>"><?= (int) $counts["rejected"] ?></div>
                <div class="stat-label">Rejected</div>
            </div>

            <div class="stat-card c-apps rise" style="--i:5">
                <div class="stat-icon">📨</div>
                <div class="stat-number" data-count="<?= (int) $total_applications ?>"><?= (int) $total_applications ?></div>
                <div class="stat-label">Admission applications</div>
            </div>
        </section>

        <div class="card rise" style="--i:6">
            <div class="card-header">
                <h2>🔔 Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge" style="background: var(--blue); color: #fff;">
                            <?= $unread_notifications > 99 ? "99+" : (int) $unread_notifications ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <a href="institution-notifications.php" class="view-all">View all →</a>
            </div>

            <?php if ($recent_notifications && $recent_notifications->num_rows > 0): ?>
                <div class="notif-list">
                    <?php while ($nrow = $recent_notifications->fetch_assoc()): ?>
                        <div class="notif-item <?= $nrow["is_read"] ? "" : "unread" ?>">
                            <div class="notif-avatar">🔔</div>
                            <div class="notif-body">
                                <h4>
                                    <?= htmlspecialchars($nrow["title"]) ?>
                                    <?php if (!$nrow["is_read"]): ?><span class="dot-new"></span><?php endif; ?>
                                </h4>
                                <p><?= htmlspecialchars($nrow["message"]) ?></p>
                            </div>
                            <div class="notif-time"><?= date("d M", strtotime($nrow["created_at"])) ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">🔕</div>
                    <h3>No notifications yet</h3>
                    <p>Updates about your school applications and admission requests will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card rise" style="--i:7">
            <div class="card-header">
                <h2>📨 Admission applications from parents</h2>
                <a href="institution-applications.php" class="view-all">View all →</a>
            </div>

            <?php if ($recent_applications && $recent_applications->num_rows > 0): ?>
                <?php while ($app = $recent_applications->fetch_assoc()): ?>
                    <div class="app-row">
                        <div>
                            <div class="app-school"><?= htmlspecialchars($app["school_name"]) ?></div>
                            <div class="app-meta">
                                Child: <strong><?= htmlspecialchars($app["child_name"]) ?></strong>
                                <?php if (!empty($app["child_age"])): ?> · Age <?= htmlspecialchars($app["child_age"]) ?><?php endif; ?>
                                    <?php if (!empty($app["preferred_class"])): ?> · <?= htmlspecialchars($app["preferred_class"]) ?><?php endif; ?>
                            </div>
                        </div>

                        <div class="app-parent">
                            <div class="avatar"><?= htmlspecialchars(initials($app["parent_name"])) ?></div>
                            <div style="min-width:0">
                                <div class="avatar-name"><?= htmlspecialchars($app["parent_name"]) ?></div>
                                <div class="avatar-email"><?= htmlspecialchars($app["parent_email"]) ?></div>
                            </div>
                        </div>

                        <div class="app-meta">
                            <?php if (!empty($app["booking_date"])): ?>
                                📅 <?= date("d M Y", strtotime($app["booking_date"])) ?>
                            <?php else: ?>
                                📅 Not specified
                            <?php endif; ?>
                        </div>

                        <div>
                            <span class="status status-<?= htmlspecialchars($app["status"]) ?>">
                                <?= htmlspecialchars($app["status"]) ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty">
                    <div class="empty-icon">📭</div>
                    <h3>No admission applications yet</h3>
                    <p>When parents apply to your approved schools, their requests will show up here.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card rise" style="--i:8">
            <div class="card-header">
                <h2>🏫 Your schools</h2>
                <a href="submit-school.php" class="view-all">➕ List a new school</a>
            </div>

            <?php if ($schools->num_rows === 0): ?>
                <div class="empty">
                    <div class="empty-icon">🏫</div>
                    <h3>No schools listed yet</h3>
                    <p>Submit your first school and an admin will verify it before it becomes visible to parents.</p>
                    <a href="submit-school.php" class="primary-button">➕ List a new school</a>
                </div>
            <?php else: ?>
                <?php while ($s = $schools->fetch_assoc()): ?>
                    <div class="sub-row">
                        <div>
                            <h3>
                                <?= htmlspecialchars($s["school_name"]) ?>
                                <span class="status status-<?= htmlspecialchars($s["approval_status"]) ?>">
                                    <?= htmlspecialchars($s["approval_status"]) ?>
                                </span>
                            </h3>
                            <div class="meta">
                                📍 <?= htmlspecialchars($s["location"]) ?> ·
                                <?= htmlspecialchars($s["school_type"]) ?> ·
                                Submitted <?= date("d M Y", strtotime($s["created_at"])) ?>
                            </div>

                            <?php if ($s["approval_status"] === "pending"): ?>
                                <div class="meta">An admin is checking that this school exists. You'll be notified.</div>
                            <?php elseif ($s["approval_status"] === "approved"): ?>
                                <div class="meta">This school is currently visible to parents.</div>
                            <?php endif; ?>
                        </div>

                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                            <?php if ($s["approval_status"] === "rejected" && $s["review_note"]): ?>
                                <div class="alert alert-warn" style="margin:0;max-width:360px;">
                                    <span>📝</span>
                                    <span><strong>Admin note:</strong> <?= htmlspecialchars($s["review_note"]) ?></span>
                                </div>
                            <?php endif; ?>
                            <a href="edit-school.php?id=<?= (int) $s["id"] ?>" class="primary-button school-edit-button" style="white-space:nowrap;">✏️ Edit School</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <script>
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