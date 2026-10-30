<?php
session_start();
require_once "db.php";

/*
|--------------------------------------------------------------------------
| LOGIN STATUS
|--------------------------------------------------------------------------
*/
$is_logged_in = isset($_SESSION['user_id']);
$user_name = "";
$user_role = "";

if ($is_logged_in) {
    $user_id = (int) $_SESSION['user_id'];

    $user_stmt = $conn->prepare("
        SELECT full_name, role
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($user_stmt) {
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_result = $user_stmt->get_result();

        if ($user_result && $user_result->num_rows === 1) {
            $user = $user_result->fetch_assoc();
            $user_name = $user['full_name'];
            $user_role = $user['role'];
        }

        $user_stmt->close();
    }
}

/*
|--------------------------------------------------------------------------
| ROLE-BASED LINKS / CONTENT
|--------------------------------------------------------------------------
*/
if ($user_role === 'admin') {
    $dashboard_link = 'admin-dashboard.php';
    $dashboard_label = 'Admin dashboard';
    $hero_description = 'Manage schools, review institution submissions, monitor bookings and keep the SchoolConnect platform running smoothly.';
} elseif ($user_role === 'institution') {
    $dashboard_link = 'institution-dashboard.php';
    $dashboard_label = 'Institution dashboard';
    $hero_description = 'Manage your school information, submit schools for verification and track the approval process from one place.';
} elseif ($user_role === 'parent') {
    $dashboard_link = 'parent-dashboard.php';
    $dashboard_label = 'Parent dashboard';
    $hero_description = "Continue exploring schools, compare your options, submit bookings and keep track of your child's applications.";
} else {
    $dashboard_link = 'login.php';
    $dashboard_label = 'Login';
    $hero_description = 'SchoolConnect makes it easy for parents to discover schools, compare options and submit school booking requests online.';
}

/*
|--------------------------------------------------------------------------
| DATABASE STATISTICS
|--------------------------------------------------------------------------
*/
$schools_count = 0;
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM schools
    WHERE status = 'active'
      AND approval_status = 'approved'
");
if ($result) {
    $row = $result->fetch_assoc();
    $schools_count = (int) $row['total'];
}

$parents_count = 0;
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'parent'
");
if ($result) {
    $row = $result->fetch_assoc();
    $parents_count = (int) $row['total'];
}

$institutions_count = 0;
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM users
    WHERE role = 'institution'
");
if ($result) {
    $row = $result->fetch_assoc();
    $institutions_count = (int) $row['total'];
}

$bookings_count = 0;
$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings
");
if ($result) {
    $row = $result->fetch_assoc();
    $bookings_count = (int) $row['total'];
}

/*
|--------------------------------------------------------------------------
| FEATURED SCHOOLS
|--------------------------------------------------------------------------
|
| Only approved and active schools are displayed to the public.
| Institution submissions remain hidden until an administrator approves them.
|
*/
$featured_schools = $conn->query("
    SELECT
        id,
        school_name,
        location,
        description,
        school_type,
        phone,
        email,
        image
    FROM schools
    WHERE status = 'active'
      AND approval_status = 'approved'
    ORDER BY id DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SchoolConnect | Find the Right School for Your Child</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

    <script>
        document.documentElement.classList.add("js");
    </script>

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

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }

        body {
            font-family: var(--body);
            background: var(--chalk);
            color: var(--ink);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a:focus-visible,
        button:focus-visible {
            outline: 3px solid var(--sun);
            outline-offset: 3px;
        }

        .wrap {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* ---------- NAVBAR ---------- */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(11, 27, 58, .92);
            backdrop-filter: blur(12px);
            color: #fff;
            min-height: 70px;
            padding: 0 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            text-decoration: none;
            font-family: var(--display);
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -.01em;
        }

        .logo-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: grid;
            place-items: center;
            font-size: 19px;
            background: linear-gradient(135deg, var(--sun), #ffcf4d);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-links a {
            color: #cbd7ee;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            transition: color .2s, background .2s, transform .2s;
        }

        .nav-links a:hover {
            color: #fff;
            background: rgba(255, 255, 255, .09);
        }

        .nav-links .login-button {
            margin-left: 8px;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .3);
        }

        .nav-links .register-button,
        .nav-links .dashboard-button {
            background: var(--sun);
            color: var(--ink);
            font-weight: 700;
        }

        .nav-links .register-button:hover,
        .nav-links .dashboard-button:hover {
            background: #ffc61f;
            color: var(--ink);
            transform: translateY(-1px);
        }

        .nav-links .dashboard-button {
            margin-left: 8px;
        }

        .nav-links .logout-button {
            background: var(--red);
            color: #fff;
        }

        .nav-links .logout-button:hover {
            background: #b91c1c;
        }

        /* ---------- HERO ---------- */
        .hero {
            position: relative;
            overflow: hidden;
            color: #fff;
            background:
                linear-gradient(rgba(255, 255, 255, .045) 1px, transparent 1px) 0 0 / 38px 38px,
                linear-gradient(90deg, rgba(255, 255, 255, .045) 1px, transparent 1px) 0 0 / 38px 38px,
                radial-gradient(circle at 78% 30%, rgba(245, 179, 1, .22), transparent 42%),
                linear-gradient(135deg, #07142e 0%, var(--ink) 40%, #0b3a86 100%);
            padding: 80px 6% 150px;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 56px;
            align-items: center;
        }

        .hero::before {
            content: "";
            position: absolute;
            left: -120px;
            bottom: -160px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            border: 56px solid rgba(255, 255, 255, .05);
            animation: drift 10s ease-in-out infinite alternate;
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -90px;
            top: -110px;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            border: 50px solid rgba(245, 179, 1, .14);
            animation: drift 9s ease-in-out infinite alternate-reverse;
        }

        @keyframes drift {
            from {
                transform: translate(0, 0) scale(1);
            }

            to {
                transform: translate(-26px, 20px) scale(1.07);
            }
        }

        .hero>* {
            position: relative;
            z-index: 1;
        }

        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .22);
            padding: 7px 16px;
            border-radius: 99px;
            font-size: 13px;
            font-weight: 500;
            color: #dbe6fa;
            margin-bottom: 22px;
        }

        .hero h1 {
            font-family: var(--display);
            font-size: clamp(38px, 5.6vw, 66px);
            font-weight: 800;
            letter-spacing: -.035em;
            line-height: 1.02;
            margin-bottom: 20px;
        }

        .hero h1 span {
            color: var(--sun);
            position: relative;
            white-space: nowrap;
        }

        .hero h1 span::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -4px;
            height: 6px;
            border-radius: 6px;
            background: var(--sun);
            opacity: .35;
            transform-origin: left;
            animation: underline 1s cubic-bezier(.2, .7, .2, 1) .9s both;
        }

        @keyframes underline {
            from {
                transform: scaleX(0);
            }
        }

        .hero-text {
            color: #c3d3f0;
            font-size: 18px;
            max-width: 48ch;
            margin-bottom: 32px;
        }

        .hero-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .primary-button,
        .secondary-button,
        .cta-button {
            display: inline-block;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            padding: 15px 26px;
            border-radius: 14px;
            transition: transform .2s, box-shadow .2s, background .2s, color .2s;
        }

        .primary-button,
        .cta-button {
            background: var(--sun);
            color: var(--ink);
        }

        .primary-button:hover,
        .cta-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 28px -12px rgba(245, 179, 1, .85);
        }

        .secondary-button {
            color: #fff;
            border: 1.5px solid rgba(255, 255, 255, .35);
        }

        .secondary-button:hover {
            background: rgba(255, 255, 255, .12);
            transform: translateY(-3px);
        }

        .hero-content>* {
            animation: rise .8s cubic-bezier(.2, .7, .2, 1) both;
        }

        .hero-content> :nth-child(1) {
            animation-delay: .05s;
        }

        .hero-content> :nth-child(2) {
            animation-delay: .15s;
        }

        .hero-content> :nth-child(3) {
            animation-delay: .25s;
        }

        .hero-content> :nth-child(4) {
            animation-delay: .35s;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .hero-visual {
            animation: slide-in 1s cubic-bezier(.2, .7, .2, 1) .3s both;
        }

        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateX(50px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .hero-photo-frame {
            position: relative;
            aspect-ratio: 5 / 4;
            border-radius: 30px;
            padding: 10px;
            background: rgba(255, 255, 255, .14);
            border: 1px solid rgba(255, 255, 255, .25);
            box-shadow: 0 40px 70px -30px rgba(0, 0, 0, .7);
            transform: rotate(2.2deg);
            transition: transform .5s cubic-bezier(.2, .7, .2, 1);
        }

        .hero-photo-frame:hover {
            transform: rotate(0deg) scale(1.02);
        }

        .hero-photo-frame .fallback,
        .hero-photo-frame img {
            position: absolute;
            inset: 10px;
            width: calc(100% - 20px);
            height: calc(100% - 20px);
            border-radius: 22px;
        }

        .hero-photo-frame .fallback {
            display: grid;
            place-items: center;
            font-size: 90px;
            background: linear-gradient(135deg, #1d4d99, #0b3a86);
        }

        .hero-photo-frame img {
            object-fit: cover;
        }

        .floating-card {
            position: absolute;
            background: #fff;
            color: var(--ink);
            font-size: 14px;
            font-weight: 700;
            padding: 11px 18px;
            border-radius: 14px;
            box-shadow: 0 18px 30px -14px rgba(0, 0, 0, .55);
            animation: float 4.5s ease-in-out infinite;
        }

        .card-one {
            left: -28px;
            top: 14%;
        }

        .card-two {
            right: -22px;
            bottom: 12%;
            animation-delay: -2.2s;
        }

        @keyframes float {
            50% {
                transform: translateY(-12px);
            }
        }

        /* ---------- STATS ---------- */
        .stats {
            position: relative;
            z-index: 3;
            width: 90%;
            max-width: 1100px;
            margin: -76px auto 0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .stat {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 24px 22px;
            text-align: center;
            box-shadow: 0 24px 40px -26px rgba(11, 27, 58, .45);
            transition: transform .3s, box-shadow .3s;
        }

        .stat:hover {
            transform: translateY(-6px);
            box-shadow: 0 30px 44px -24px rgba(11, 27, 58, .5);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 12px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            font-size: 24px;
            background: var(--sky);
            transition: transform .3s;
        }

        .stat:hover .stat-icon {
            transform: rotate(-8deg) scale(1.1);
        }

        .stat-number {
            font-family: var(--display);
            font-size: 40px;
            font-weight: 800;
            letter-spacing: -.03em;
            line-height: 1.1;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }

        /* ---------- SECTIONS ---------- */
        .section {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 100px 0 10px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 46px;
        }

        .section-header h2 {
            font-family: var(--display);
            font-size: clamp(30px, 4vw, 42px);
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .section-header p {
            color: var(--muted);
            font-size: 17px;
        }

        /* reveal on scroll (only when JS is on) */
        .js .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity .7s ease, transform .7s cubic-bezier(.2, .7, .2, 1);
            transition-delay: calc(var(--d, 0) * 90ms);
        }

        .js .reveal.in {
            opacity: 1;
            transform: none;
        }

        /* ---------- SCHOOL CARDS ---------- */
        .school-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 24px;
        }

        .school-card {
            display: flex;
            flex-direction: column;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            overflow: hidden;
            transition: transform .3s, box-shadow .3s;
        }

        .school-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 28px 44px -24px rgba(11, 27, 58, .42);
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
            transition: transform .7s cubic-bezier(.2, .7, .2, 1);
        }

        .school-card:hover .school-image img {
            transform: scale(1.09);
        }

        .placeholder {
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 64px;
            background: linear-gradient(135deg, var(--sky), #cfe1fa);
        }

        .school-content {
            padding: 22px 24px 24px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .school-type {
            align-self: flex-start;
            background: var(--chalk);
            color: var(--blue);
            border: 1px solid var(--line);
            font-size: 12px;
            font-weight: 700;
            padding: 3px 12px;
            border-radius: 99px;
            margin-bottom: 12px;
        }

        .school-name {
            font-family: var(--display);
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -.01em;
            line-height: 1.2;
            margin-bottom: 6px;
        }

        .school-location {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .school-description {
            color: #475569;
            font-size: 15px;
            margin-bottom: 20px;
        }

        .view-button {
            margin-top: auto;
            text-align: center;
            text-decoration: none;
            background: var(--ink);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            padding: 13px;
            border-radius: 13px;
            transition: background .2s, transform .2s, box-shadow .2s;
        }

        .view-button:hover {
            background: var(--blue);
            transform: translateY(-2px);
            box-shadow: 0 12px 22px -12px rgba(7, 86, 184, .85);
        }

        .more {
            text-align: center;
            margin-top: 40px;
        }

        .more .secondary-button {
            color: var(--ink);
            border-color: var(--ink);
        }

        .more .secondary-button:hover {
            background: var(--ink);
            color: #fff;
        }

        .empty-schools {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 70px 20px;
            text-align: center;
        }

        .empty-schools-icon {
            font-size: 54px;
            display: inline-block;
            margin-bottom: 10px;
            animation: float 3.5s ease-in-out infinite;
        }

        .empty-schools h3 {
            font-family: var(--display);
            font-size: 22px;
            margin-bottom: 6px;
        }

        .empty-schools p {
            color: var(--muted);
        }

        /* ---------- FEATURES ---------- */
        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px;
            transition: transform .3s, box-shadow .3s, border-color .3s;
        }

        .feature:hover {
            transform: translateY(-6px);
            border-color: var(--blue);
            box-shadow: 0 24px 38px -26px rgba(7, 86, 184, .6);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            font-size: 26px;
            background: var(--sky);
            margin-bottom: 18px;
            transition: transform .35s, background .3s;
        }

        .feature:hover .feature-icon {
            transform: rotate(-10deg) scale(1.1);
            background: #cfe1fa;
        }

        .feature h3 {
            font-family: var(--display);
            font-size: 20px;
            margin-bottom: 6px;
        }

        .feature p {
            color: var(--muted);
            font-size: 15px;
        }

        /* ---------- HOW IT WORKS ---------- */
        .how-section {
            position: relative;
            width: 100%;
            max-width: none;
            margin-top: 100px;
            padding: 90px 5% 100px;
            background:
                linear-gradient(rgba(255, 255, 255, .04) 1px, transparent 1px) 0 0 / 38px 38px,
                linear-gradient(90deg, rgba(255, 255, 255, .04) 1px, transparent 1px) 0 0 / 38px 38px,
                linear-gradient(135deg, #07142e, var(--ink) 55%, #0b3a86);
            color: #fff;
        }

        .how-section .section-header p {
            color: #b9c9e8;
        }

        .steps {
            position: relative;
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .steps::before {
            content: "";
            position: absolute;
            top: 34px;
            left: 12%;
            right: 12%;
            border-top: 2px dashed rgba(255, 255, 255, .25);
        }

        .step {
            position: relative;
            text-align: center;
            padding: 0 8px;
        }

        .step-number {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-family: var(--display);
            font-size: 28px;
            font-weight: 800;
            color: var(--ink);
            background: var(--sun);
            box-shadow: 0 0 0 8px rgba(245, 179, 1, .18);
            transition: transform .3s;
        }

        .step:hover .step-number {
            transform: scale(1.12) rotate(-6deg);
        }

        .step h3 {
            font-family: var(--display);
            font-size: 20px;
            margin-bottom: 4px;
        }

        .step p {
            color: #b9c9e8;
            font-size: 15px;
        }

        /* ---------- CTA ---------- */
        .cta {
            position: relative;
            overflow: hidden;
            width: 90%;
            max-width: 1100px;
            margin: 100px auto 90px;
            text-align: center;
            color: #fff;
            padding: 64px 30px;
            border-radius: 30px;
            background: linear-gradient(135deg, var(--blue), #0b3a86 55%, var(--ink));
            box-shadow: 0 40px 70px -36px rgba(11, 27, 58, .8);
        }

        .cta::after {
            content: "";
            position: absolute;
            right: -80px;
            top: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            border: 48px solid rgba(245, 179, 1, .18);
            animation: drift 9s ease-in-out infinite alternate;
        }

        .cta>* {
            position: relative;
            z-index: 1;
        }

        .cta h2 {
            font-family: var(--display);
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.1;
            margin-bottom: 10px;
        }

        .cta p {
            color: #c3d3f0;
            font-size: 17px;
            margin-bottom: 28px;
        }

        /* ---------- FOOTER ---------- */
        .footer {
            background: #07142e;
            color: #fff;
            padding: 60px 6% 0;
        }

        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr;
            gap: 40px;
            padding-bottom: 40px;
        }

        .footer h3 {
            font-family: var(--display);
            font-size: 18px;
            margin-bottom: 14px;
        }

        .footer p {
            color: #8fa1c4;
            font-size: 14px;
            max-width: 38ch;
        }

        .footer-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .footer-links a {
            color: #8fa1c4;
            text-decoration: none;
            font-size: 14px;
            width: fit-content;
            transition: color .2s, transform .2s;
        }

        .footer-links a:hover {
            color: var(--sun);
            transform: translateX(4px);
        }

        .copyright {
            max-width: 1200px;
            margin: 0 auto;
            padding: 22px 0;
            border-top: 1px solid rgba(255, 255, 255, .1);
            color: #6d80a6;
            font-size: 13px;
            text-align: center;
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 960px) {
            .hero {
                grid-template-columns: 1fr;
                padding-top: 56px;
                gap: 44px;
            }

            .hero-visual {
                max-width: 520px;
                margin: 0 auto;
                width: 100%;
            }

            .card-one {
                left: -8px;
            }

            .card-two {
                right: -8px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .features {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps {
                grid-template-columns: repeat(2, 1fr);
                row-gap: 40px;
            }

            .steps::before {
                display: none;
            }
        }

        @media (max-width: 760px) {
            .nav-links a:not(.login-button):not(.register-button):not(.dashboard-button):not(.logout-button) {
                display: none;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 560px) {
            .navbar {
                padding: 0 5%;
            }

            .logo {
                font-size: 18px;
            }

            .hero {
                padding: 44px 5% 130px;
            }

            .stats,
            .features,
            .steps {
                grid-template-columns: 1fr;
            }

            .stat-number {
                font-size: 34px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation: none !important;
                transition: none !important;
            }

            .js .reveal {
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>

<body>

    <!-- NAVIGATION -->
    <nav class="navbar">
        <a href="index.php" class="logo">
            <span class="logo-icon">🎓</span>
            SchoolConnect
        </a>

        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="#featured-schools">Schools</a>
            <a href="#everything-you-need">Features</a>
            <a href="#how-it-works">How it works</a>

            <?php if (!$is_logged_in): ?>
                <a href="login.php" class="login-button">Login</a>
                <a href="register.php" class="register-button">Register</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($dashboard_link) ?>" class="dashboard-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero">
        <div class="hero-content">
            <span class="badge">🎓 Basic school booking system</span>

            <?php if ($is_logged_in): ?>
                <?php if ($user_role === "institution"): ?>
                    <h1>Welcome back, <span><?= htmlspecialchars($user_name) ?></span></h1>
                    <p class="hero-text">
                        <?= htmlspecialchars($hero_description) ?>
                    </p>
                <?php elseif ($user_role === "admin"): ?>
                    <h1>Welcome back, <span><?= htmlspecialchars($user_name) ?></span></h1>
                    <p class="hero-text">
                        <?= htmlspecialchars($hero_description) ?>
                    </p>
                <?php else: ?>
                    <h1>Welcome back, <span><?= htmlspecialchars($user_name) ?></span></h1>
                    <p class="hero-text">
                        <?= htmlspecialchars($hero_description) ?>
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <h1>Find the <span>right school</span> for your child</h1>
                <p class="hero-text">
                    <?= htmlspecialchars($hero_description) ?>
                </p>
            <?php endif; ?>

            <div class="hero-buttons">
                <?php if (!$is_logged_in): ?>
                    <a href="register.php" class="primary-button">🚀 Get started</a>
                    <a href="schools.php" class="secondary-button">🏫 Explore schools</a>
                <?php elseif ($user_role === "admin"): ?>
                    <a href="admin-dashboard.php" class="primary-button">📊 Admin dashboard</a>
                    <a href="admin-schools.php" class="secondary-button">🏫 Manage schools</a>
                <?php elseif ($user_role === "institution"): ?>
                    <a href="institution-dashboard.php" class="primary-button">🏫 Institution dashboard</a>
                    <a href="submit-school.php" class="secondary-button">➕ Submit a school</a>
                <?php else: ?>
                    <a href="schools.php" class="primary-button">🏫 Explore schools</a>
                    <a href="my-bookings.php" class="secondary-button">📅 My bookings</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- HERO VISUAL -->
        <div class="hero-visual">
            <div class="hero-photo-frame">
                <div class="fallback">🏫</div>
                <img src="images/image copy 3.png" alt="SchoolConnect partner school building" onerror="this.style.display='none'">
                <div class="floating-card card-one">⭐ Quality education</div>
                <div class="floating-card card-two">📅 Easy booking</div>
            </div>
        </div>
    </section>

    <!-- LIVE DATABASE STATISTICS -->
    <section class="stats">
        <div class="stat reveal" style="--d:0">
            <div class="stat-icon">🏫</div>
            <div class="stat-number" data-count="<?= (int) $schools_count ?>"><?= number_format($schools_count) ?></div>
            <div class="stat-label">Active schools</div>
        </div>

        <div class="stat reveal" style="--d:1">
            <div class="stat-icon">👨‍👩‍👧</div>
            <div class="stat-number" data-count="<?= (int) $parents_count ?>"><?= number_format($parents_count) ?></div>
            <div class="stat-label">Registered parents</div>
        </div>

        <div class="stat reveal" style="--d:2">
            <div class="stat-icon">🏢</div>
            <div class="stat-number" data-count="<?= (int) $institutions_count ?>"><?= number_format($institutions_count) ?></div>
            <div class="stat-label">Registered institutions</div>
        </div>

        <div class="stat reveal" style="--d:3">
            <div class="stat-icon">📅</div>
            <div class="stat-number" data-count="<?= (int) $bookings_count ?>"><?= number_format($bookings_count) ?></div>
            <div class="stat-label">Total bookings</div>
        </div>
    </section>

    <!-- FEATURED SCHOOLS -->
    <section class="section" id="featured-schools">
        <div class="section-header reveal">
            <h2>Featured schools</h2>
            <p>Explore some of the latest active schools available through SchoolConnect.</p>
        </div>

        <?php if ($featured_schools && $featured_schools->num_rows > 0): ?>

            <div class="school-grid">
                <?php $card_no = 0; ?>
                <?php while ($school = $featured_schools->fetch_assoc()): ?>

                    <article class="school-card reveal" style="--d:<?= $card_no++ % 3 ?>">

                        <div class="school-image">
                            <?php
                            $image = trim($school["image"] ?? "");

                            // Plain file names live in the uploads folder (same as the other pages)
                            if ($image !== "" && strpos($image, "/") === false) {
                                $image = "uploads/" . $image;
                            }
                            ?>
                            <?php if ($image !== ""): ?>
                                <img
                                    src="<?= htmlspecialchars($image) ?>"
                                    alt="<?= htmlspecialchars($school["school_name"]) ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                                <div class="placeholder" style="display:none;">🏫</div>
                            <?php else: ?>
                                <div class="placeholder">🏫</div>
                            <?php endif; ?>
                        </div>

                        <div class="school-content">
                            <?php if (!empty($school["school_type"])): ?>
                                <span class="school-type"><?= htmlspecialchars($school["school_type"]) ?></span>
                            <?php endif; ?>

                            <h3 class="school-name"><?= htmlspecialchars($school["school_name"]) ?></h3>
                            <div class="school-location">📍 <?= htmlspecialchars($school["location"]) ?></div>

                            <div class="school-description">
                                <?php
                                $description = trim($school["description"] ?? "");

                                if ($description === "") {
                                    echo "Quality education for your child.";
                                } else {
                                    echo htmlspecialchars(
                                        strlen($description) > 120
                                            ? substr($description, 0, 120) . "..."
                                            : $description
                                    );
                                }
                                ?>
                            </div>

                            <?php if ($user_role === "institution"): ?>
                                <a href="schools.php" class="view-button">🏫 Explore schools</a>
                            <?php else: ?>
                                <a href="booking.php?school_id=<?= (int) $school["id"] ?>" class="view-button">📅 Book this school</a>
                            <?php endif; ?>
                        </div>

                    </article>

                <?php endwhile; ?>
            </div>

            <div class="more reveal">
                <a href="schools.php" class="secondary-button">View all schools →</a>
            </div>

        <?php else: ?>

            <div class="empty-schools reveal">
                <div class="empty-schools-icon">🏫</div>
                <h3>No active schools yet</h3>
                <p>Schools added by the administrator will appear here automatically.</p>
            </div>

        <?php endif; ?>
    </section>

    <!-- FEATURES -->
    <section class="section" id="everything-you-need">
        <div class="section-header reveal">
            <h2>Everything you need</h2>
            <p>A simple platform connecting parents, institutions and administrators.</p>
        </div>

        <div class="features">
            <div class="feature reveal" style="--d:0">
                <div class="feature-icon">🔎</div>
                <h3>Find schools</h3>
                <p>Search schools by name, location, school type and available options.</p>
            </div>

            <div class="feature reveal" style="--d:1">
                <div class="feature-icon">📅</div>
                <h3>Easy booking</h3>
                <p>Parents can submit school booking requests online quickly and conveniently.</p>
            </div>

            <div class="feature reveal" style="--d:2">
                <div class="feature-icon">🔔</div>
                <h3>Booking notifications</h3>
                <p>Receive updates when booking or school-related statuses change.</p>
            </div>

            <div class="feature reveal" style="--d:0">
                <div class="feature-icon">🏢</div>
                <h3>Institution dashboard</h3>
                <p>Institutions can manage their school information and monitor submitted schools.</p>
            </div>

            <div class="feature reveal" style="--d:1">
                <div class="feature-icon">📋</div>
                <h3>School verification</h3>
                <p>New institution submissions remain pending until an administrator reviews them.</p>
            </div>

            <div class="feature reveal" style="--d:2">
                <div class="feature-icon">🔐</div>
                <h3>Role-based access</h3>
                <p>Separate parent, institution and administrator accounts protect system access.</p>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="section how-section" id="how-it-works">
        <div class="section-header reveal">
            <h2>How SchoolConnect works</h2>
            <p>Parents and institutions each have a simple path through the platform.</p>
        </div>

        <div class="steps">
            <div class="step reveal" style="--d:0">
                <div class="step-number">1</div>
                <h3>Create an account</h3>
                <p>Register as a parent or institution.</p>
            </div>

            <div class="step reveal" style="--d:1">
                <div class="step-number">2</div>
                <h3>Explore or submit</h3>
                <p>Parents find schools while institutions submit school details.</p>
            </div>

            <div class="step reveal" style="--d:2">
                <div class="step-number">3</div>
                <h3>Review and book</h3>
                <p>Administrators verify schools and parents can submit bookings.</p>
            </div>

            <div class="step reveal" style="--d:3">
                <div class="step-number">4</div>
                <h3>Track progress</h3>
                <p>Users monitor their school activities and application status.</p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="cta reveal">
        <?php if (!$is_logged_in): ?>
            <h2>Ready to join SchoolConnect?</h2>
            <p>Create a parent or institution account and get started today.</p>
            <a href="register.php" class="cta-button">🚀 Create an account</a>
        <?php elseif ($user_role === "admin"): ?>
            <h2>Manage SchoolConnect</h2>
            <p>Review institutions, manage schools and monitor the platform from your administrator dashboard.</p>
            <a href="admin-dashboard.php" class="cta-button">📊 Open admin dashboard</a>
        <?php elseif ($user_role === "institution"): ?>
            <h2>Ready to add your school?</h2>
            <p>Submit your school information for administrator verification and make it available to parents after approval.</p>
            <a href="submit-school.php" class="cta-button">➕ Submit a school</a>
        <?php else: ?>
            <h2>Continue your school journey</h2>
            <p>Explore approved schools and manage your applications from your parent dashboard.</p>
            <a href="schools.php" class="cta-button">🏫 Explore schools</a>
        <?php endif; ?>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="footer-grid">
            <div>
                <h3>🎓 SchoolConnect</h3>
                <p>A basic school booking system designed to make school discovery and booking easier for parents.</p>
            </div>

            <div>
                <h3>Quick links</h3>
                <div class="footer-links">
                    <a href="index.php">Home</a>
                    <a href="schools.php">Schools</a>
                    <a href="login.php">Login</a>
                    <a href="register.php">Register</a>
                </div>
            </div>

            <div>
                <h3>Account</h3>
                <div class="footer-links">
                    <a href="login.php">Parent / institution login</a>
                    <a href="register.php">Create account</a>
                    <a href="admin-login.php">Admin login</a>
                    <a href="institution-dashboard.php">Institution dashboard</a>
                </div>
            </div>
        </div>

        <div class="copyright">
            © 2026 SchoolConnect — Basic School Booking System. All rights reserved.
        </div>
    </footer>

    <script>
        (function() {
            var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

            // Count-up for the numbers once they scroll into view
            function countUp(el) {
                var target = parseInt(el.dataset.count, 10) || 0;
                if (target === 0 || reduce) return;

                var duration = 1300,
                    start = null;

                function tick(t) {
                    if (start === null) start = t;
                    var p = Math.min((t - start) / duration, 1);
                    el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
                    if (p < 1) requestAnimationFrame(tick);
                }

                el.textContent = "0";
                requestAnimationFrame(tick);
            }

            var items = document.querySelectorAll(".reveal");

            if (!("IntersectionObserver" in window)) {
                items.forEach(function(el) {
                    el.classList.add("in");
                });
                return;
            }

            var io = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (!entry.isIntersecting) return;

                    entry.target.classList.add("in");

                    var num = entry.target.querySelector("[data-count]");
                    if (num) countUp(num);

                    io.unobserve(entry.target);
                });
            }, {
                threshold: 0.15
            });

            items.forEach(function(el) {
                io.observe(el);
            });
        })();
    </script>

</body>

</html>