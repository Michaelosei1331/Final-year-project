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

// Institutions and admins have their own dashboards
$role_home = ["institution" => "institution-dashboard.php", "admin" => "admin-dashboard.php"];
if (isset($role_home[$_SESSION["role"] ?? "parent"])) {
    header("Location: " . $role_home[$_SESSION["role"]]);
    exit;
}

// =====================================================
// GET SCHOOL ID (schools.php sends ?id=, booking links use ?school_id=)
// =====================================================
$school_id = intval($_GET["id"] ?? $_GET["school_id"] ?? 0);
if ($school_id <= 0) {
    header("Location: schools.php");
    exit;
}

// =====================================================
// GET SCHOOL (only verified + active schools are public)
// =====================================================
$stmt = $conn->prepare(
    "SELECT *
     FROM schools
     WHERE id = ?
     AND status = 'active'
     AND approval_status = 'approved'"
);
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: schools.php");
    exit;
}
$school = $result->fetch_assoc();
$stmt->close();

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// -----------------------------------------------------
// PHOTOS: main image + extra photos (maximum seven)
// -----------------------------------------------------
function schoolconnect_image_url($storedPath)
{
    $path = trim((string) $storedPath);
    if ($path === '') {
        return '';
    }

    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');
    if (strpos($path, 'uploads/') !== 0) {
        $path = 'uploads/' . $path;
    }
    return $path;
}

$school_images = [];
$primary_image_url = schoolconnect_image_url($school['image'] ?? '');
if ($primary_image_url !== '') {
    $school_images[] = $primary_image_url;
}
try {
    $gallery_stmt = $conn->prepare(
        "SELECT image_path FROM school_images WHERE school_id = ? ORDER BY sort_order ASC LIMIT 14"
    );
    if ($gallery_stmt) {
        $gallery_stmt->bind_param('i', $school_id);
        $gallery_stmt->execute();
        $gallery_result = $gallery_stmt->get_result();
        while ($gallery_row = $gallery_result->fetch_assoc()) {
            $candidate = schoolconnect_image_url($gallery_row['image_path'] ?? '');
            if ($candidate !== '' && !in_array($candidate, $school_images, true) && count($school_images) < 7) {
                $school_images[] = $candidate;
            }
        }
        $gallery_stmt->close();
    }
} catch (Throwable $e) {
    // The optional school_images table may not exist yet.
}

// -----------------------------------------------------
// FACILITIES
// -----------------------------------------------------
$facility_list = [];
if (!empty($school['facilities'])) {
    foreach (preg_split('/[,;\r\n]+/', (string) $school['facilities']) as $facility) {
        $facility = trim($facility);
        if ($facility !== '') {
            $facility_list[] = $facility;
        }
    }
}

$has_pin = is_numeric($school['latitude'] ?? null) && is_numeric($school['longitude'] ?? null)
    && ((float) $school['latitude'] != 0 || (float) $school['longitude'] != 0);
$website = trim((string) ($school['website'] ?? ''));
$has_fees = isset($school['school_fees']) && $school['school_fees'] !== '' && $school['school_fees'] !== null;

// =====================================================
// NOTIFICATIONS
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

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($school["school_name"]) ?> | SchoolConnect</title>

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

        /* ---------- SCHOOL PROFILE ACTION + PHOTO GALLERY ---------- */
        .profile-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            margin-top: 18px;
            padding: 13px 16px;
            border-radius: 12px;
            border: 0;
            background: var(--blue);
            color: #fff;
            font: 700 14px var(--body);
            text-align: center;
            text-decoration: none;
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        .profile-button:hover {
            background: #06479a;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(7, 86, 184, .18);
        }

        .profile-prompt {
            margin-top: 14px;
            color: var(--muted);
            font-size: 12px;
        }

        .gallery-section {
            padding: 18px 22px 22px;
            border-top: 1px solid var(--line);
        }

        .gallery-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
            font-family: var(--display);
            font-size: 16px;
            font-weight: 700;
        }

        .gallery-count {
            padding: 4px 8px;
            border-radius: 99px;
            background: var(--sky);
            color: var(--blue);
            font: 700 11px var(--body);
            white-space: nowrap;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .photo-thumb {
            display: block;
            width: 100%;
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            aspect-ratio: 1 / 1;
            background: var(--sky);
            cursor: zoom-in;
        }

        .photo-thumb img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .25s ease;
        }

        .photo-thumb:hover img {
            transform: scale(1.06);
        }

        .gallery-empty {
            padding: 13px;
            border-radius: 10px;
            background: #f8faff;
            color: var(--muted);
            font-size: 13px;
        }

        .school-about {
            margin-top: 16px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.65;
        }

        .school-about strong {
            color: var(--ink);
        }

        .facilities-toggle {
            width: 100%;
            margin-top: 14px;
            padding: 11px 12px;
            border: 1px solid #cfe0f7;
            border-radius: 11px;
            background: var(--sky);
            color: var(--blue);
            font: 700 13px var(--body);
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .facilities-panel {
            display: none;
            margin-top: 8px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #f8faff;
        }

        .facilities-panel.show {
            display: block;
        }

        .facility-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .facility-chip {
            display: inline-flex;
            padding: 6px 9px;
            border: 1px solid #dce5f2;
            border-radius: 999px;
            background: #fff;
            color: var(--ink);
            font-size: 11px;
            font-weight: 700;
        }

        .no-facilities {
            color: var(--muted);
            font-size: 12px;
        }

        .photo-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(5, 15, 34, .88);
        }

        .photo-modal.open {
            display: flex;
        }

        .photo-modal img {
            max-width: min(100%, 1100px);
            max-height: 82vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 22px 70px rgba(0, 0, 0, .35);
        }

        .photo-modal-close {
            position: absolute;
            top: 18px;
            right: 22px;
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 50%;
            background: #fff;
            color: var(--ink);
            font-size: 26px;
            cursor: pointer;
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

            .gallery-section {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 650px) {
            .photo-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .gallery-section {
                padding: 16px;
            }

            .profile-button {
                font-size: 13px;
            }

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

        /* ---------- SCHOOL PROFILE PAGE ---------- */
        .sd-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 22px
        }

        .sd-header h1 {
            font-family: var(--display);
            font-size: clamp(28px, 4.4vw, 42px);
            line-height: 1.1;
            letter-spacing: -.02em;
            margin: 10px 0 6px
        }

        .sd-loc {
            color: var(--muted);
            font-size: 16px
        }

        .sd-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            align-items: start
        }

        .sd-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 22px;
            box-shadow: 0 8px 24px -20px rgba(11, 27, 58, .35)
        }

        .sd-card h2 {
            font-family: var(--display);
            font-size: 20px;
            margin-bottom: 14px
        }

        .sd-card p {
            color: #334155;
            line-height: 1.7
        }

        .sd-gallery {
            padding: 14px
        }

        .sd-main-photo {
            position: relative;
            width: 100%;
            height: 420px;
            border-radius: 14px;
            overflow: hidden;
            background: #e8f1fd;
            border: 0;
            padding: 0;
            cursor: zoom-in;
            display: block
        }

        .sd-main-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .sd-zoom {
            position: absolute;
            right: 12px;
            bottom: 12px;
            background: rgba(11, 27, 58, .75);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 999px
        }

        .sd-thumbs {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
            gap: 10px;
            margin-top: 12px
        }

        .sd-thumb {
            height: 68px;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid transparent;
            padding: 0;
            cursor: pointer;
            background: #e8f1fd;
            opacity: .75;
            transition: .2s
        }

        .sd-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block
        }

        .sd-thumb:hover,
        .sd-thumb.active {
            opacity: 1;
            border-color: var(--blue)
        }

        .sd-nophoto {
            height: 260px;
            border-radius: 14px;
            background: linear-gradient(135deg, #e8f1fd, #f4f6fb);
            display: grid;
            place-items: center;
            text-align: center;
            color: var(--muted)
        }

        .sd-nophoto span {
            font-size: 56px;
            display: block
        }

        .sd-facilities {
            display: flex;
            flex-wrap: wrap;
            gap: 10px
        }

        .sd-chip {
            background: #e8f1fd;
            color: #0756b8;
            font-weight: 700;
            font-size: 14px;
            padding: 8px 14px;
            border-radius: 999px
        }

        #sdMap {
            height: 300px;
            border-radius: 14px;
            z-index: 1
        }

        .sd-maplink {
            display: inline-block;
            margin-top: 12px;
            font-weight: 700;
            color: var(--blue);
            text-decoration: none
        }

        .sd-side {
            position: sticky;
            top: 90px
        }

        .sd-fee-box {
            text-align: center;
            background: linear-gradient(135deg, #0b1b3a, #0b3a86);
            color: #fff;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 18px
        }

        .sd-fee-box small {
            display: block;
            color: #c3d3f0;
            margin-bottom: 4px
        }

        .sd-fee-box b {
            font-family: var(--display);
            font-size: 32px
        }

        .sd-book {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            background: var(--sun);
            color: #0b1b3a;
            font-weight: 800;
            font-size: 17px;
            padding: 16px;
            border-radius: 14px;
            text-decoration: none;
            margin-top: 16px;
            transition: .2s
        }

        .sd-book:hover {
            background: #ffc61f;
            transform: translateY(-1px)
        }

        .sd-contact a,
        .sd-contact div {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            padding: 10px 0;
            border-top: 1px solid var(--line);
            color: #0b1b3a;
            text-decoration: none;
            word-break: break-word;
            font-size: 15px
        }

        .sd-contact a:first-of-type,
        .sd-contact div:first-of-type {
            border-top: 0
        }

        .sd-contact a:hover {
            color: var(--blue)
        }

        @media(max-width:900px) {
            .sd-layout {
                grid-template-columns: 1fr
            }

            .sd-side {
                position: static
            }

            .sd-main-photo {
                height: 300px
            }
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
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

        <a href="schools.php" class="back rise" style="--i:0">← Back to schools</a>

        <div class="sd-header rise" style="--i:1">
            <div>
                <span class="school-type"><?= e($school["school_type"] ?? "Basic School") ?></span>
                <h1><?= e($school["school_name"]) ?></h1>
                <div class="sd-loc">📍 <?= e($school["location"] ?? "Location not provided") ?></div>
            </div>
        </div>

        <div class="sd-layout">

            <div class="sd-main">

                <!-- PHOTOS -->
                <section class="sd-card sd-gallery rise" style="--i:2">
                    <?php if (!empty($school_images)): ?>
                        <button type="button" class="sd-main-photo" id="mainPhotoBtn" aria-label="Enlarge school photo">
                            <img id="mainPhoto" src="<?= e($school_images[0]) ?>" alt="<?= e($school["school_name"]) ?>">
                            <span class="sd-zoom">🔍 View larger · <span id="photoCounter">1</span>/<?= count($school_images) ?></span>
                        </button>
                        <?php if (count($school_images) > 1): ?>
                            <div class="sd-thumbs" aria-label="School photos">
                                <?php foreach ($school_images as $i => $photo_url): ?>
                                    <button type="button" class="sd-thumb <?= $i === 0 ? "active" : "" ?>" data-photo-src="<?= e($photo_url) ?>" data-index="<?= $i + 1 ?>" aria-label="Show photo <?= $i + 1 ?>">
                                        <img src="<?= e($photo_url) ?>" alt="" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="sd-nophoto">
                            <div><span>🏫</span>This school hasn't uploaded photos yet.</div>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- ABOUT -->
                <section class="sd-card rise" style="--i:3">
                    <h2>About this school</h2>
                    <?php if (!empty($school["description"])): ?>
                        <p><?= nl2br(e($school["description"])) ?></p>
                    <?php else: ?>
                        <p>This school hasn't added a description yet. Contact them directly for more information.</p>
                    <?php endif; ?>
                </section>

                <!-- FACILITIES -->
                <section class="sd-card rise" style="--i:4">
                    <h2>Facilities</h2>
                    <?php if (!empty($facility_list)): ?>
                        <div class="sd-facilities">
                            <?php foreach ($facility_list as $facility): ?>
                                <span class="sd-chip">✓ <?= e($facility) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p>This school has not added its facilities yet.</p>
                    <?php endif; ?>
                </section>

                <!-- MAP -->
                <?php if ($has_pin): ?>
                    <section class="sd-card rise" style="--i:5">
                        <h2>Where to find us</h2>
                        <div id="sdMap"></div>
                        <a class="sd-maplink" target="_blank" rel="noopener noreferrer"
                            href="https://www.openstreetmap.org/?mlat=<?= urlencode($school["latitude"]) ?>&mlon=<?= urlencode($school["longitude"]) ?>#map=17/<?= urlencode($school["latitude"]) ?>/<?= urlencode($school["longitude"]) ?>">
                            Open in OpenStreetMap →
                        </a>
                    </section>
                <?php endif; ?>

            </div>

            <!-- SIDEBAR -->
            <aside class="sd-side">
                <?php if ($has_fees): ?>
                    <div class="sd-fee-box rise" style="--i:2">
                        <small>School fees</small>
                        <b>GHS <?= number_format((float) $school["school_fees"], 2) ?></b>
                    </div>
                <?php endif; ?>

                <div class="sd-card rise" style="--i:3">
                    <h2>Contact</h2>
                    <div class="sd-contact">
                        <div>📍 <span><?= e($school["location"] ?? "Not provided") ?></span></div>
                        <?php if (!empty($school["phone"])): ?>
                            <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $school["phone"])) ?>">📞 <span><?= e($school["phone"]) ?></span></a>
                        <?php endif; ?>
                        <?php if (!empty($school["email"])): ?>
                            <a href="mailto:<?= e($school["email"]) ?>">✉️ <span><?= e($school["email"]) ?></span></a>
                        <?php endif; ?>
                        <?php if ($website !== "" && preg_match('#^https?://#i', $website)): ?>
                            <a href="<?= e($website) ?>" target="_blank" rel="noopener noreferrer">🌐 <span><?= e($website) ?></span></a>
                        <?php endif; ?>
                    </div>

                    <a class="sd-book" href="booking.php?school_id=<?= (int) $school["id"] ?>">📅 Book this school</a>
                </div>
            </aside>

        </div>

    </main>

    <footer class="footer">
        <p>© 2026 SchoolConnect. Basic School Booking System.</p>
    </footer>

    <div class="photo-modal" id="photoModal" role="dialog" aria-modal="true" aria-label="School photo preview" aria-hidden="true">
        <button type="button" class="photo-modal-close" id="photoModalClose" aria-label="Close photo preview">×</button>
        <img id="photoModalImage" src="" alt="Enlarged school photo">
    </div>

    <?php if ($has_pin): ?>
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var pos = [<?= (float) $school["latitude"] ?>, <?= (float) $school["longitude"] ?>];
                var map = L.map("sdMap", {
                    scrollWheelZoom: false
                }).setView(pos, 16);
                L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
                }).addTo(map);
                L.marker(pos).addTo(map).bindPopup(<?= json_encode($school["school_name"]) ?>);
            });
        </script>
    <?php endif; ?>

    <script>
        (function() {
            var mainPhoto = document.getElementById("mainPhoto");
            var mainBtn = document.getElementById("mainPhotoBtn");
            var counter = document.getElementById("photoCounter");
            var modal = document.getElementById("photoModal");
            var modalImage = document.getElementById("photoModalImage");
            var closeButton = document.getElementById("photoModalClose");

            // Click a thumbnail to show it as the main photo
            document.querySelectorAll(".sd-thumb").forEach(function(thumb) {
                thumb.addEventListener("click", function() {
                    mainPhoto.src = thumb.getAttribute("data-photo-src");
                    counter.textContent = thumb.getAttribute("data-index");
                    document.querySelectorAll(".sd-thumb").forEach(function(t) {
                        t.classList.remove("active");
                    });
                    thumb.classList.add("active");
                });
            });

            // Click the main photo to enlarge it
            function closeModal() {
                modal.classList.remove("open");
                modal.setAttribute("aria-hidden", "true");
                modalImage.src = "";
            }
            if (mainBtn) {
                mainBtn.addEventListener("click", function() {
                    modalImage.src = mainPhoto.src;
                    modal.classList.add("open");
                    modal.setAttribute("aria-hidden", "false");
                    closeButton.focus();
                });
            }
            closeButton.addEventListener("click", closeModal);
            modal.addEventListener("click", function(e) {
                if (e.target === modal) closeModal();
            });
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape" && modal.classList.contains("open")) closeModal();
            });
        })();
    </script>

</body>

</html>