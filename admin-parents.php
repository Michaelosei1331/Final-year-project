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
// =====================================================
// 2. CHECK ADMIN
// =====================================================
if (
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: parent-dashboard.php");
    exit;
}
// =====================================================
// 3. GET ALL PARENTS
// =====================================================
$sql = "
    SELECT
        users.id,
        users.full_name,
        users.email,
        users.phone,
        users.created_at,
        COUNT(bookings.id) AS total_bookings,
        SUM(
            bookings.status = 'pending'
        ) AS pending_bookings,
        SUM(
            bookings.status = 'approved'
        ) AS approved_bookings
    FROM users
    LEFT JOIN bookings
        ON users.id = bookings.user_id
    WHERE users.role = 'parent'
    GROUP BY
        users.id,
        users.full_name,
        users.email,
        users.phone,
        users.created_at
    ORDER BY users.created_at DESC
";
$parents = $conn->query($sql);
// =====================================================
// 4. GET PARENT STATISTICS
// =====================================================
$total_parents_result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'parent'
    "
);
$total_parents =
    $total_parents_result->fetch_assoc()["total"];
$total_bookings_result = $conn->query(
    "
    SELECT COUNT(*) AS total
    FROM bookings
    "
);
$total_bookings =
    $total_bookings_result->fetch_assoc()["total"];
$active_parents_result = $conn->query(
    "
    SELECT COUNT(DISTINCT user_id) AS total
    FROM bookings
    "
);
$active_parents =
    $active_parents_result->fetch_assoc()["total"];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Parents | SchoolConnect</title>

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

        /* ---------- HEADER ---------- */
        .page-title {
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

        .page-title::after {
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

        .page-title>* {
            position: relative;
            z-index: 1;
        }

        .page-title h1 {
            font-family: var(--display);
            font-size: clamp(25px, 4vw, 34px);
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #c3d3f0;
            font-size: 15px;
            max-width: 52ch;
        }

        /* ---------- STATS ---------- */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
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
            letter-spacing: -.03em;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }

        /* ---------- TABLE CARD ---------- */
        .parents-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
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

        .search {
            position: relative;
            width: 260px;
            max-width: 100%;
        }

        .search input {
            width: 100%;
            font-family: var(--body);
            font-size: 14px;
            color: var(--ink);
            padding: 10px 14px 10px 38px;
            background: var(--chalk);
            border: 1px solid var(--line);
            border-radius: 12px;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        .search input:focus {
            outline: none;
            background: #fff;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px var(--sky);
        }

        .search::before {
            content: "🔎";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }

        th {
            text-align: left;
            padding: 14px 20px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            background: var(--chalk);
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
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

        .parent-profile {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-family: var(--display);
            font-weight: 700;
            font-size: 17px;
            flex-shrink: 0;
            background: var(--sky);
            color: var(--blue);
            transition: transform .3s;
        }

        tr:hover .avatar {
            transform: scale(1.1) rotate(-6deg);
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

        .parent-id {
            color: var(--muted);
            font-size: 12px;
        }

        .contact-email {
            font-size: 14px;
            word-break: break-all;
        }

        .contact-phone {
            color: var(--muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .booking-count {
            display: inline-block;
            background: var(--sky);
            color: var(--blue);
            font-weight: 700;
            font-size: 13px;
            padding: 5px 12px;
            border-radius: 99px;
            margin-bottom: 8px;
        }

        .booking-count.none {
            background: var(--chalk);
            color: var(--muted);
        }

        .chips {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .chip {
            font-size: 12px;
            font-weight: 700;
            padding: 2px 9px;
            border-radius: 99px;
        }

        .chip-approved {
            background: #dcfce7;
            color: #166534;
        }

        .chip-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .date {
            font-weight: 700;
            white-space: nowrap;
        }

        .time {
            color: var(--muted);
            font-size: 13px;
            font-weight: 400;
        }

        .role {
            display: inline-block;
            background: var(--ink);
            color: #fff;
            padding: 5px 12px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
        }

        .no-match {
            display: none;
            padding: 50px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
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
        @media (max-width: 800px) {
            .stats {
                grid-template-columns: 1fr;
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
                padding: 8px 16px;
            }

            td[data-label]::before {
                content: attr(data-label);
                display: block;
                font-size: 12px;
                font-weight: 700;
                color: var(--muted);
                margin-bottom: 3px;
            }

            td.who::before {
                display: none;
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

            .page-title {
                padding: 26px 24px;
            }

            .search {
                width: 100%;
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
            <a href="admin-bookings.php">📅 Bookings</a>
            <a href="admin-parents.php" class="active">👨‍👩‍👧 Parents</a>
            <a href="notifications.php">🔔 Notifications</a>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">

        <!-- PAGE TITLE -->
        <div class="page-title rise" style="--i:0">
            <h1>Parent management</h1>
            <p>View all parents registered on the SchoolConnect platform.</p>
        </div>

        <!-- STATISTICS -->
        <div class="stats">
            <div class="stat rise" style="--i:1">
                <div class="stat-icon">👨‍👩‍👧</div>
                <div class="stat-number" data-count="<?= (int) $total_parents ?>"><?= (int) $total_parents ?></div>
                <div class="stat-label">Registered parents</div>
            </div>

            <div class="stat rise" style="--i:2">
                <div class="stat-icon">🟢</div>
                <div class="stat-number" data-count="<?= (int) $active_parents ?>"><?= (int) $active_parents ?></div>
                <div class="stat-label">Parents with bookings</div>
            </div>

            <div class="stat rise" style="--i:3">
                <div class="stat-icon">📅</div>
                <div class="stat-number" data-count="<?= (int) $total_bookings ?>"><?= (int) $total_bookings ?></div>
                <div class="stat-label">Total bookings</div>
            </div>
        </div>

        <!-- PARENTS TABLE -->
        <div class="parents-card rise" style="--i:4">

            <div class="card-header">
                <div>
                    <h2>Registered parents</h2>
                    <p>Parents who have created accounts.</p>
                </div>

                <?php if ($parents && $parents->num_rows > 0): ?>
                    <div class="search">
                        <input type="search" id="parentSearch" placeholder="Search name, email or phone" aria-label="Search parents" autocomplete="off">
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($parents && $parents->num_rows > 0): ?>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Parent</th>
                                <th>Contact</th>
                                <th>Bookings</th>
                                <th>Registered</th>
                                <th>Role</th>
                            </tr>
                        </thead>

                        <tbody id="parentRows">
                            <?php $row_no = 0; ?>
                            <?php while ($parent = $parents->fetch_assoc()): ?>

                                <?php
                                $name = $parent["full_name"];
                                $initial = strtoupper(substr($name, 0, 1));
                                $total_b = (int) ($parent["total_bookings"] ?? 0);
                                ?>

                                <tr style="--r:<?= min($row_no++, 12) ?>">

                                    <td class="who">
                                        <div class="parent-profile">
                                            <div class="avatar av-<?= ord($initial) % 5 ?>"><?= htmlspecialchars($initial) ?></div>
                                            <div>
                                                <div class="parent-name"><?= htmlspecialchars($parent["full_name"]) ?></div>
                                                <div class="parent-id">ID: #<?= (int) $parent["id"] ?></div>
                                            </div>
                                        </div>
                                    </td>

                                    <td data-label="Contact">
                                        <div class="contact-email">✉️ <?= htmlspecialchars($parent["email"]) ?></div>
                                        <?php if (!empty($parent["phone"])): ?>
                                            <div class="contact-phone">📞 <?= htmlspecialchars($parent["phone"]) ?></div>
                                        <?php else: ?>
                                            <div class="contact-phone">No phone</div>
                                        <?php endif; ?>
                                    </td>

                                    <td data-label="Bookings">
                                        <span class="booking-count <?= $total_b === 0 ? "none" : "" ?>">
                                            <?= $total_b ?> booking<?= $total_b === 1 ? "" : "s" ?>
                                        </span>
                                        <div class="chips">
                                            <span class="chip chip-approved">Approved: <?= (int) ($parent["approved_bookings"] ?? 0) ?></span>
                                            <span class="chip chip-pending">Pending: <?= (int) ($parent["pending_bookings"] ?? 0) ?></span>
                                        </div>
                                    </td>

                                    <td data-label="Registered">
                                        <div class="date">
                                            <?= date("d M Y", strtotime($parent["created_at"])) ?>
                                            <br><span class="time"><?= date("h:i A", strtotime($parent["created_at"])) ?></span>
                                        </div>
                                    </td>

                                    <td data-label="Role">
                                        <span class="role">Parent</span>
                                    </td>

                                </tr>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <div class="no-match" id="noMatch">No parents match your search.</div>

            <?php else: ?>

                <div class="empty">
                    <div class="empty-icon">👨‍👩‍👧</div>
                    <h2>No parents found</h2>
                    <p>There are currently no registered parent accounts. New sign-ups will appear here.</p>
                </div>

            <?php endif; ?>

        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Admin Parent Management.</p>
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

            // Live search across the rows already on the page
            var input = document.getElementById("parentSearch");
            var rows = document.querySelectorAll("#parentRows tr");
            var noMatch = document.getElementById("noMatch");
            if (!input) return;

            input.addEventListener("input", function() {
                var q = input.value.trim().toLowerCase(),
                    shown = 0;

                rows.forEach(function(row) {
                    var hit = row.textContent.toLowerCase().indexOf(q) !== -1;
                    row.style.display = hit ? "" : "none";
                    if (hit) shown++;
                });

                noMatch.style.display = shown === 0 ? "block" : "none";
            });
        })();
    </script>

</body>

</html>