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
// GET SCHOOL ID
// =====================================================
$school_id = intval($_GET["school_id"] ?? 0);
// =====================================================
// IF FORM WAS SUBMITTED, GET SCHOOL ID FROM FORM
// =====================================================
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["school_id"])
) {
    $school_id = intval(
        $_POST["school_id"]
    );
}
// =====================================================
// CHECK SCHOOL ID
// =====================================================
if ($school_id <= 0) {
    header("Location: schools.php");
    exit;
}
// =====================================================
// GET SCHOOL
// =====================================================
$stmt = $conn->prepare(
    "SELECT *
     FROM schools
     WHERE id = ?
     AND status = 'active'
     AND approval_status = 'approved'"
);
$stmt->bind_param(
    "i",
    $school_id
);
$stmt->execute();
$result = $stmt->get_result();
// =====================================================
// CHECK IF SCHOOL EXISTS
// =====================================================
if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: schools.php");
    exit;
}
$school = $result->fetch_assoc();
$stmt->close();
// =====================================================
// MESSAGE
// =====================================================
$message = "";
$message_type = "";
// =====================================================
// HANDLE BOOKING
// =====================================================
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["submit_booking"])
) {
    // =================================================
    // GET FORM DATA
    // =================================================
    $user_id = intval(
        $_SESSION["user_id"]
    );
    $child_name = trim(
        $_POST["child_name"] ?? ""
    );
    $child_age = intval(
        $_POST["child_age"] ?? 0
    );
    $preferred_class = trim(
        $_POST["preferred_class"] ?? ""
    );
    $booking_date = trim(
        $_POST["booking_date"] ?? ""
    );
    $booking_message = trim(
        $_POST["message"] ?? ""
    );
    // =================================================
    // VALIDATE FORM
    // =================================================
    if (
        empty($child_name) ||
        empty($preferred_class) ||
        empty($booking_date)
    ) {
        $message =
            "Please fill in all required fields.";
        $message_type =
            "error";
    } elseif (
        $child_age < 1 ||
        $child_age > 20
    ) {
        $message =
            "Please enter a valid child age.";
        $message_type =
            "error";
    } else {
        // =================================================
        // CHECK BOOKING DATE
        // =================================================
        $today = date("Y-m-d");
        if ($booking_date < $today) {
            $message =
                "Booking date cannot be in the past.";
            $message_type =
                "error";
        } else {
            // =================================================
            // INSERT BOOKING
            // =================================================
            $sql = "
                INSERT INTO bookings
                (
                    user_id,
                    school_id,
                    child_name,
                    child_age,
                    preferred_class,
                    booking_date,
                    message,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?, ?, ?, 'pending')
            ";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "iisisss",
                    $user_id,
                    $school_id,
                    $child_name,
                    $child_age,
                    $preferred_class,
                    $booking_date,
                    $booking_message
                );
                if ($stmt->execute()) {
                    $message =
                        "Booking submitted successfully! "
                        . "Your booking is now pending admin approval.";
                    $message_type =
                        "success";
                    // Clear form values
                    $child_name = "";
                    $child_age = "";
                    $preferred_class = "";
                    $booking_date = "";
                    $booking_message = "";
                } else {
                    $message =
                        "Unable to submit booking: "
                        . $stmt->error;
                    $message_type =
                        "error";
                }
                $stmt->close();
            } else {
                $message =
                    "Database error: "
                    . $conn->error;
                $message_type =
                    "error";
            }
        }
    }
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

$classes = [
    "Creche",
    "Nursery",
    "KG 1",
    "KG 2",
    "Basic 1",
    "Basic 2",
    "Basic 3",
    "Basic 4",
    "Basic 5",
    "Basic 6",
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?= htmlspecialchars($school["school_name"]) ?> | SchoolConnect</title>

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
            max-width: 1100px;
            margin: 28px auto 60px;
        }

        .rise {
            animation: rise .7s cubic-bezier(.2, .7, .2, 1) both;
            animation-delay: calc(var(--i, 0) * 100ms);
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

        .back {
            display: inline-block;
            color: var(--blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 6px 12px;
            margin: 0 0 14px -12px;
            border-radius: 9px;
            transition: background .2s, transform .2s;
        }

        .back:hover {
            background: var(--sky);
            transform: translateX(-3px);
        }

        .booking-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: start;
        }

        /* ---------- SCHOOL CARD ---------- */
        .school-card {
            position: sticky;
            top: 90px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            overflow: hidden;
        }

        .school-image {
            height: 200px;
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
            transform: scale(1.06);
        }

        .placeholder {
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 64px;
            background: linear-gradient(135deg, var(--sky), #cfe1fa);
        }

        .school-info {
            padding: 22px;
        }

        .school-info h2 {
            font-family: var(--display);
            font-size: 23px;
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: 1.2;
            margin-bottom: 10px;
        }

        .school-type {
            display: inline-block;
            background: var(--chalk);
            color: var(--blue);
            border: 1px solid var(--line);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 11px;
            border-radius: 99px;
            margin-bottom: 14px;
        }

        .info {
            color: var(--muted);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        /* ---------- FORM CARD ---------- */
        .form-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 34px 36px;
        }

        .form-card h1 {
            font-family: var(--display);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -.02em;
            line-height: 1.1;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 15px;
            margin-bottom: 26px;
        }

        /* ---------- MESSAGES ---------- */
        .message {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 15px 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 500;
        }

        .message .m-icon {
            width: 28px;
            height: 28px;
            flex-shrink: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 14px;
            color: #fff;
            font-weight: 700;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #a7e6bd;
            animation: pop .5s cubic-bezier(.2, .9, .3, 1.3) both;
        }

        .message.success .m-icon {
            background: var(--green);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f7b5b5;
            animation: shake .5s ease both;
        }

        .message.error .m-icon {
            background: var(--red);
        }

        .message a {
            display: inline-block;
            margin-top: 6px;
            color: inherit;
            font-weight: 700;
        }

        @keyframes pop {
            from {
                opacity: 0;
                transform: scale(.94);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: none;
            }

            20% {
                transform: translateX(-7px);
            }

            40% {
                transform: translateX(6px);
            }

            60% {
                transform: translateX(-4px);
            }

            80% {
                transform: translateX(3px);
            }
        }

        /* ---------- FORM ---------- */
        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 7px;
        }

        input,
        select,
        textarea {
            width: 100%;
            font-family: var(--body);
            font-size: 15px;
            color: var(--ink);
            padding: 13px 15px;
            background: var(--chalk);
            border: 1.5px solid var(--line);
            border-radius: 12px;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        input::placeholder,
        textarea::placeholder {
            color: #94a3b8;
        }

        input:hover,
        select:hover,
        textarea:hover {
            border-color: #c9d4e8;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            background: #fff;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px var(--sky);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .submit-button {
            position: relative;
            width: 100%;
            border: none;
            cursor: pointer;
            font-family: var(--body);
            font-size: 16px;
            font-weight: 700;
            background: var(--ink);
            color: #fff;
            padding: 15px;
            border-radius: 14px;
            margin-top: 4px;
            transition: transform .2s, background .2s, box-shadow .2s;
        }

        .submit-button:hover {
            background: var(--blue);
            transform: translateY(-2px);
            box-shadow: 0 14px 26px -12px rgba(7, 86, 184, .8);
        }

        .submit-button:active {
            transform: scale(.98);
        }

        .submit-button.loading {
            background: var(--blue);
            pointer-events: none;
        }

        .submit-button.loading::after {
            content: "";
            display: inline-block;
            width: 15px;
            height: 15px;
            margin-left: 10px;
            vertical-align: -2px;
            border: 2px solid rgba(255, 255, 255, .4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .notice {
            margin-top: 22px;
            padding: 15px 18px;
            border-radius: 14px;
            background: #fffaf0;
            border: 1px solid #fde9b0;
            color: #6b4e08;
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
        @media (max-width: 900px) {
            .booking-layout {
                grid-template-columns: 1fr;
            }

            .school-card {
                position: static;
                display: grid;
                grid-template-columns: 200px 1fr;
            }

            .school-image {
                height: 100%;
                min-height: 160px;
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

            .school-card {
                display: block;
            }

            .school-image {
                height: 170px;
            }

            .form-card {
                padding: 26px 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
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

    <main class="container">

        <a href="school-details.php?id=<?= (int) $school["id"] ?>" class="back rise" style="--i:0">← Back to school details</a>

        <div class="booking-layout">

            <!-- SCHOOL INFORMATION -->
            <div class="school-card rise" style="--i:1">

                <div class="school-image">
                    <?php if (!empty($school["image"])): ?>
                        <img src="<?= htmlspecialchars(strpos($school["image"], "uploads/") === 0 ? $school["image"] : "uploads/" . $school["image"]) ?>" alt="<?= htmlspecialchars($school["school_name"]) ?>">
                    <?php else: ?>
                        <div class="placeholder">🏫</div>
                    <?php endif; ?>
                </div>

                <div class="school-info">
                    <h2><?= htmlspecialchars($school["school_name"]) ?></h2>

                    <span class="school-type"><?= htmlspecialchars($school["school_type"]) ?></span>

                    <div class="info">
                        📍 <?= htmlspecialchars($school["location"]) ?>
                        <?php if (!empty($school["phone"])): ?>
                            <br>📞 <?= htmlspecialchars($school["phone"]) ?>
                        <?php endif; ?>
                        <?php if (!empty($school["email"])): ?>
                            <br>✉️ <?= htmlspecialchars($school["email"]) ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- BOOKING FORM -->
            <div class="form-card rise" style="--i:2">

                <h1>📅 Book this school</h1>
                <p class="subtitle">Enter your child's information to submit a school booking request.</p>

                <!-- MESSAGE -->
                <?php if (!empty($message)): ?>
                    <div class="message <?= htmlspecialchars($message_type) ?>" role="<?= $message_type === "error" ? "alert" : "status" ?>">
                        <span class="m-icon"><?= $message_type === "success" ? "✓" : "!" ?></span>
                        <div>
                            <?= htmlspecialchars($message) ?>
                            <?php if ($message_type === "success"): ?>
                                <br><a href="my-bookings.php">View my bookings →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- FORM -->
                <form method="POST" id="bookingForm">

                    <input type="hidden" name="school_id" value="<?= (int) $school["id"] ?>">

                    <div class="form-group">
                        <label for="child_name">Child's full name *</label>
                        <input type="text" id="child_name" name="child_name" placeholder="Enter child's full name" value="<?= htmlspecialchars($child_name ?? "") ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="child_age">Child's age *</label>
                            <input type="number" id="child_age" name="child_age" min="1" max="20" placeholder="e.g. 8" value="<?= htmlspecialchars($child_age ?? "") ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="preferred_class">Preferred class *</label>
                            <select id="preferred_class" name="preferred_class" required>
                                <option value="">Select class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= htmlspecialchars($class) ?>" <?= ($preferred_class ?? "") === $class ? "selected" : "" ?>><?= htmlspecialchars($class) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="booking_date">Preferred booking date *</label>
                        <input type="date" id="booking_date" name="booking_date" min="<?= date("Y-m-d") ?>" value="<?= htmlspecialchars($booking_date ?? "") ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="message">Additional message</label>
                        <textarea id="message" name="message" placeholder="Tell the school anything important about your child's admission..."><?= htmlspecialchars($booking_message ?? "") ?></textarea>
                    </div>

                    <button type="submit" name="submit_booking" class="submit-button" id="submitBtn">📅 Submit booking request</button>

                </form>

                <!-- NOTICE -->
                <div class="notice">
                    <strong>ℹ️ Important:</strong>
                    Your booking will start with <strong>Pending</strong> status. The administrator will review your request and either approve or reject it. You'll get a notification when it changes.
                </div>

            </div>

        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <script>
        // Show a spinner and block double-clicks. The button itself is NOT disabled,
        // because the server checks for the "submit_booking" value it sends.
        (function() {
            var form = document.getElementById("bookingForm");
            var btn = document.getElementById("submitBtn");
            var sent = false;

            form.addEventListener("submit", function(e) {
                if (sent) {
                    e.preventDefault();
                    return;
                }
                sent = true;
                btn.classList.add("loading");
                btn.firstChild.nodeValue = "Submitting";
            });
        })();
    </script>

</body>

</html>