<?php

require_once "db.php";

$message = "";
$message_type = "";
$registration_success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get information from the form
    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";
    $account_type = $_POST["account_type"] ?? "parent";

    // Only allow valid account types
    if (!in_array($account_type, ["parent", "institution"], true)) {
        $account_type = "parent";
    }

    // Check that all fields are filled
    if (
        empty($full_name) ||
        empty($email) ||
        empty($phone) ||
        empty($password) ||
        empty($confirm_password)
    ) {

        $message = "Please fill in all fields.";
        $message_type = "error";
    }

    // Check email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "error";
    }

    // Check password length
    elseif (strlen($password) < 6) {

        $message = "Password must contain at least 6 characters.";
        $message_type = "error";
    }

    // Check passwords
    elseif ($password !== $confirm_password) {

        $message = "Passwords do not match.";
        $message_type = "error";
    } else {

        // Check whether email already exists
        $check_sql = "SELECT id FROM users WHERE email = ?";

        $check_stmt = $conn->prepare($check_sql);

        if (!$check_stmt) {

            $message = "Database error. Please try again.";
            $message_type = "error";
        } else {

            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_stmt->store_result();

            if ($check_stmt->num_rows > 0) {

                $message = "An account with this email already exists.";
                $message_type = "error";
            } else {

                // Encrypt the password
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert the user
                $sql = "
                    INSERT INTO users
                    (
                        full_name,
                        email,
                        phone,
                        password,
                        role
                    )
                    VALUES
                    (?, ?, ?, ?, ?)
                ";

                $stmt = $conn->prepare($sql);

                if (!$stmt) {

                    $message = "Database error. Please try again.";
                    $message_type = "error";
                } else {

                    $stmt->bind_param(
                        "sssss",
                        $full_name,
                        $email,
                        $phone,
                        $hashed_password,
                        $account_type
                    );

                    if ($stmt->execute()) {

                        $registration_success = true;

                        if ($account_type === "institution") {

                            $message =
                                "Institution account created successfully. You can now login and submit your school for verification.";
                        } else {

                            $message =
                                "Parent account created successfully. You can now login and find a school.";
                        }

                        $message_type = "success";
                    } else {

                        $message =
                            "Registration failed. Please try again.";

                        $message_type = "error";
                    }

                    $stmt->close();
                }
            }

            $check_stmt->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Create Account | SchoolConnect</title>


    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com">

    <link
        href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap"
        rel="stylesheet">


    <style>
        :root {

            --ink: #0b1b3a;
            --blue: #0756b8;
            --sky: #e8f1fd;
            --sun: #f5b301;
            --muted: #62708a;
            --line: #e5eaf3;
            --red: #dc2626;

            --display:
                "Bricolage Grotesque",
                "Segoe UI",
                Arial,
                sans-serif;

            --body:
                "DM Sans",
                "Segoe UI",
                Arial,
                sans-serif;
        }


        * {

            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html,
        body {

            min-height: 100%;
        }


        body {

            font-family: var(--body);
            color: var(--ink);
            line-height: 1.5;
            position: relative;
            overflow-x: hidden;
            background: #06122b;
        }


        a:focus-visible,
        button:focus-visible,
        input:focus-visible {

            outline: 3px solid var(--sun);
            outline-offset: 2px;
        }


        /* =========================
           BACKGROUND
        ========================= */

        .scene {

            position: fixed;
            inset: 0;
            z-index: 0;

            background:

                linear-gradient(180deg,
                    rgba(6, 18, 43, .55),
                    rgba(11, 58, 134, .35)),

                url("images/login-bg.jpg") center / cover no-repeat,

                linear-gradient(180deg,
                    #06122b 0%,
                    #0b2a63 38%,
                    #1f5aa8 68%,
                    #f2a93b 100%);
        }


        .stars span {

            position: absolute;

            width: 2px;
            height: 2px;

            border-radius: 50%;

            background: #fff;

            top: var(--y);
            left: var(--x);

            opacity: .7;

            animation:
                twinkle 3.5s ease-in-out infinite;

            animation-delay: var(--d);
        }


        @keyframes twinkle {

            50% {
                opacity: .1;
            }
        }


        .cloud {

            position: absolute;

            left: -240px;
            top: var(--t);

            width: var(--w);
            height: calc(var(--w) * .28);

            border-radius: 99px;

            background: rgba(255, 255, 255, .13);

            filter: blur(10px);

            animation:
                cloud var(--s) linear infinite;

            animation-delay: var(--d);
        }


        @keyframes cloud {

            to {

                transform:
                    translateX(calc(100vw + 520px));
            }
        }


        .landscape {

            position: absolute;

            left: 0;
            right: 0;
            bottom: 0;

            width: 100%;

            height:
                clamp(240px,
                    36vh,
                    460px);
        }


        .sun-glow {

            transform-origin:
                380px 300px;

            animation:
                glow 7s ease-in-out infinite alternate;
        }


        @keyframes glow {

            from {

                transform: scale(.94);
                opacity: .8;
            }

            to {

                transform: scale(1.06);
                opacity: 1;
            }
        }


        .lit {

            animation:
                flicker 6s ease-in-out infinite;

            animation-delay: var(--d, 0s);
        }


        @keyframes flicker {

            0%,
            90%,
            100% {

                opacity: 1;
            }

            94% {

                opacity: .35;
            }
        }


        .flag {

            transform-origin:
                380px 60px;

            animation:
                flap 2.4s ease-in-out infinite alternate;
        }


        @keyframes flap {

            from {

                transform:
                    skewY(0deg);
            }

            to {

                transform:
                    skewY(-6deg) scaleX(.94);
            }
        }


        /* =========================
           PAGE
        ========================= */

        .page {

            position: relative;

            z-index: 1;

            min-height: 100vh;

            width: 90%;

            max-width: 1180px;

            margin: 0 auto;

            display: grid;

            grid-template-columns:
                1fr 500px;

            gap: 60px;

            align-items: center;

            padding:
                40px 0 calc(36vh - 10px);
        }


        .intro {

            color: #fff;
        }


        .intro .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            font-family: var(--display);

            font-size: 22px;

            font-weight: 700;

            margin-bottom: 34px;
        }


        .intro .brand span {

            color: var(--sun);
        }


        .intro h2 {

            font-family: var(--display);

            font-size:
                clamp(34px,
                    4.6vw,
                    54px);

            font-weight: 800;

            letter-spacing: -.03em;

            line-height: 1.04;

            max-width: 14ch;

            margin-bottom: 16px;
        }


        .intro p {

            color: #c3d3f0;

            font-size: 17px;

            max-width: 40ch;

            margin-bottom: 28px;
        }


        .steps {

            list-style: none;

            display: grid;

            gap: 14px;

            margin-bottom: 4px;
        }


        .steps li {

            display: flex;

            gap: 14px;

            align-items: center;

            font-size: 16px;

            color: #dbe6fa;
        }


        .steps b {

            width: 34px;
            height: 34px;

            flex-shrink: 0;

            border-radius: 50%;

            display: grid;

            place-items: center;

            font-family: var(--display);

            font-size: 15px;

            background: var(--sun);

            color: var(--ink);
        }


        /* =========================
           CARD
        ========================= */

        .login-container {

            background:
                rgba(255, 255, 255, .96);

            backdrop-filter: blur(14px);

            border:
                1px solid rgba(255, 255, 255, .6);

            border-radius: 26px;

            padding: 38px 36px;

            box-shadow:
                0 40px 80px -30px rgba(0, 0, 0, .65);

            animation:
                rise .8s cubic-bezier(.2, .7, .2, 1) .1s both;
        }


        @keyframes rise {

            from {

                opacity: 0;

                transform:
                    translateY(24px);
            }

            to {

                opacity: 1;

                transform: none;
            }
        }


        .logo {

            text-align: center;

            margin-bottom: 26px;
        }


        .logo-icon {

            width: 62px;
            height: 62px;

            margin: 0 auto 14px;

            border-radius: 18px;

            background:
                linear-gradient(135deg,
                    var(--ink),
                    #0b3a86);

            color: #fff;

            display: grid;

            place-items: center;

            font-size: 30px;

            box-shadow:
                0 14px 24px -12px rgba(11, 27, 58, .7);

            animation:
                bob 4s ease-in-out infinite;
        }


        @keyframes bob {

            50% {

                transform:
                    translateY(-5px) rotate(-4deg);
            }
        }


        .logo h1 {

            font-family: var(--display);

            font-size: 28px;

            font-weight: 800;

            letter-spacing: -.02em;

            line-height: 1.1;
        }


        .logo p {

            margin-top: 6px;

            color: var(--muted);

            font-size: 15px;
        }


        /* =========================
           ACCOUNT TYPE
        ========================= */

        .account-title {

            display: block;

            font-size: 14px;

            font-weight: 700;

            margin-bottom: 9px;
        }


        .account-options {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 10px;

            margin-bottom: 20px;
        }


        .account-option {

            position: relative;
        }


        .account-option input {

            position: absolute;

            opacity: 0;

            pointer-events: none;
        }


        .account-option label {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding: 12px 10px;

            border:
                1.5px solid var(--line);

            border-radius: 12px;

            background: #f4f6fb;

            color: var(--muted);

            cursor: pointer;

            font-size: 14px;

            font-weight: 700;

            transition:
                .2s;
        }


        .account-option label:hover {

            border-color:
                #c9d4e8;

            background: #fff;
        }


        .account-option input:checked+label {

            background:
                var(--sky);

            border-color:
                var(--blue);

            color:
                var(--blue);

            box-shadow:
                0 0 0 3px rgba(7, 86, 184, .08);
        }


        .account-description {

            background:
                #f8fafc;

            border:
                1px solid var(--line);

            border-radius: 10px;

            padding: 10px 12px;

            font-size: 12px;

            color: var(--muted);

            margin-top: -8px;

            margin-bottom: 20px;
        }


        /* =========================
           MESSAGES
        ========================= */

        .message {

            display: flex;

            gap: 10px;

            align-items: flex-start;

            padding: 12px 14px;

            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 14px;

            font-weight: 500;
        }


        .message::before {

            content: "!";

            flex-shrink: 0;

            width: 22px;
            height: 22px;

            border-radius: 50%;

            display: grid;

            place-items: center;

            font-size: 13px;

            font-weight: 700;

            color: #fff;

            background:
                var(--red);
        }


        .message.error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #f7b5b5;

            animation:
                shake .5s ease both;
        }


        @keyframes shake {

            0%,
            100% {

                transform: none;
            }

            20% {

                transform:
                    translateX(-7px);
            }

            40% {

                transform:
                    translateX(6px);
            }

            60% {

                transform:
                    translateX(-4px);
            }

            80% {

                transform:
                    translateX(3px);
            }
        }


        /* =========================
           FORM
        ========================= */

        .form-group {

            margin-bottom: 18px;
        }


        label {

            display: block;

            margin-bottom: 7px;

            font-size: 14px;

            font-weight: 700;
        }


        input {

            width: 100%;

            font-family: var(--body);

            font-size: 15px;

            color: var(--ink);

            padding: 13px 15px;

            background: #f4f6fb;

            border:
                1.5px solid var(--line);

            border-radius: 12px;

            transition:
                border-color .2s,
                background .2s,
                box-shadow .2s;
        }


        input::placeholder {

            color: #94a3b8;
        }


        input:hover {

            border-color:
                #c9d4e8;
        }


        input:focus {

            outline: none;

            background: #fff;

            border-color:
                var(--blue);

            box-shadow:
                0 0 0 4px var(--sky);
        }


        .field {

            position: relative;
        }


        .field input[type="password"],
        .field input.shown {

            padding-right: 76px;
        }


        .toggle {

            position: absolute;

            right: 8px;

            top: 50%;

            transform:
                translateY(-50%);

            border: none;

            background: none;

            cursor: pointer;

            font-family: var(--body);

            color: var(--blue);

            font-size: 13px;

            font-weight: 700;

            padding: 6px 10px;

            border-radius: 8px;
        }


        .toggle:hover {

            background:
                var(--sky);
        }


        .form-row {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 14px;
        }


        /* =========================
           BUTTON
        ========================= */

        .register-button {

            width: 100%;

            border: none;

            cursor: pointer;

            font-family: var(--body);

            font-size: 16px;

            font-weight: 700;

            padding: 14px;

            margin-top: 4px;

            border-radius: 13px;

            background:
                var(--ink);

            color: #fff;

            transition:
                transform .2s,
                background .2s,
                box-shadow .2s;
        }


        .register-button:hover {

            background:
                var(--blue);

            transform:
                translateY(-2px);

            box-shadow:
                0 14px 26px -12px rgba(7, 86, 184, .85);
        }


        .register-button:active {

            transform:
                scale(.98);
        }


        .register-button.loading {

            background:
                var(--blue);

            pointer-events: none;
        }


        .register-button.loading::after {

            content: "";

            display: inline-block;

            width: 14px;
            height: 14px;

            margin-left: 10px;

            vertical-align: -2px;

            border:
                2px solid rgba(255, 255, 255, .4);

            border-top-color: #fff;

            border-radius: 50%;

            animation:
                spin .7s linear infinite;
        }


        @keyframes spin {

            to {

                transform:
                    rotate(360deg);
            }
        }


        /* =========================
           PASSWORD
        ========================= */

        .strength {

            display: flex;

            align-items: center;

            gap: 6px;

            margin-top: 8px;

            min-height: 18px;
        }


        .strength i {

            flex: 1;

            height: 5px;

            border-radius: 99px;

            background:
                var(--line);

            transition:
                background .3s;
        }


        .strength small {

            font-size: 12px;

            font-weight: 700;

            color:
                var(--muted);

            min-width: 52px;

            text-align: right;
        }


        .strength[data-level="1"] i:nth-child(-n+1) {

            background:
                var(--red);
        }


        .strength[data-level="2"] i:nth-child(-n+2) {

            background:
                #f59e0b;
        }


        .strength[data-level="3"] i:nth-child(-n+3) {

            background:
                #84cc16;
        }


        .strength[data-level="4"] i:nth-child(-n+4) {

            background:
                #16a34a;
        }


        .hint {

            font-size: 12px;

            font-weight: 700;

            margin-top: 7px;

            min-height: 18px;
        }


        .hint.ok {

            color:
                #166534;
        }


        .hint.bad {

            color:
                #b91c1c;
        }


        /* =========================
           SUCCESS
        ========================= */

        .success-panel {

            text-align: center;

            padding:
                6px 0 4px;

            animation:
                pop .6s cubic-bezier(.2, .9, .3, 1.3) both;
        }


        @keyframes pop {

            from {

                opacity: 0;

                transform:
                    scale(.92);
            }

            to {

                opacity: 1;

                transform:
                    none;
            }
        }


        .check {

            width: 76px;
            height: 76px;

            margin:
                0 auto 18px;

            border-radius: 50%;

            background:
                #dcfce7;

            display: grid;

            place-items: center;
        }


        .check svg {

            width: 40px;
            height: 40px;
        }


        .check path {

            stroke:
                #16a34a;

            stroke-width: 4;

            fill: none;

            stroke-linecap: round;

            stroke-linejoin: round;

            stroke-dasharray: 40;

            stroke-dashoffset: 40;

            animation:
                draw .6s ease .3s forwards;
        }


        @keyframes draw {

            to {

                stroke-dashoffset: 0;
            }
        }


        .success-panel h2 {

            font-family:
                var(--display);

            font-size: 26px;

            font-weight: 800;

            margin-bottom: 8px;
        }


        .success-panel p {

            color:
                var(--muted);

            font-size: 15px;

            margin-bottom: 24px;
        }


        .go-login {

            display: block;

            text-decoration: none;

            text-align: center;

            background:
                var(--ink);

            color: #fff;

            font-weight: 700;

            font-size: 16px;

            padding: 14px;

            border-radius: 13px;
        }


        .go-login:hover {

            background:
                var(--blue);
        }


        /* =========================
           LINKS
        ========================= */

        .register-text {

            text-align: center;

            margin-top: 22px;

            color:
                var(--muted);

            font-size: 14px;
        }


        .register-text a {

            color:
                var(--blue);

            font-weight: 700;

            text-decoration: none;
        }


        .register-text a:hover {

            text-decoration:
                underline;
        }


        .home-link {

            display: block;

            text-align: center;

            margin-top: 14px;

            color:
                var(--muted);

            font-size: 13px;

            text-decoration: none;
        }


        .home-link:hover {

            color:
                var(--blue);
        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 960px) {

            .page {

                grid-template-columns:
                    1fr;

                max-width:
                    500px;

                padding-bottom:
                    40px;
            }


            .intro {

                display: none;
            }
        }


        @media (max-width: 500px) {

            .page {

                width: 94%;

                padding:
                    24px 0 32px;
            }


            .login-container {

                padding:
                    28px 22px;
            }


            .form-row {

                grid-template-columns:
                    1fr;

                gap: 0;
            }


            .account-options {

                grid-template-columns:
                    1fr;
            }


            .landscape {

                height:
                    30vh;
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


    <!-- =========================
     BACKGROUND SCENE
========================= -->

    <div class="scene" aria-hidden="true">

        <div class="stars">

            <span style="--x:8%;--y:9%;--d:0s"></span>
            <span style="--x:18%;--y:22%;--d:1s"></span>
            <span style="--x:31%;--y:7%;--d:2s"></span>
            <span style="--x:47%;--y:16%;--d:.5s"></span>
            <span style="--x:58%;--y:6%;--d:1.6s"></span>
            <span style="--x:72%;--y:19%;--d:2.4s"></span>
            <span style="--x:83%;--y:8%;--d:.8s"></span>
            <span style="--x:93%;--y:24%;--d:1.9s"></span>
            <span style="--x:12%;--y:33%;--d:2.8s"></span>
            <span style="--x:65%;--y:31%;--d:.3s"></span>

        </div>


        <div
            class="cloud"
            style="--t:12%;--w:300px;--s:70s;--d:-20s">
        </div>

        <div
            class="cloud"
            style="--t:26%;--w:220px;--s:95s;--d:-60s">
        </div>

        <div
            class="cloud"
            style="--t:40%;--w:340px;--s:120s;--d:-5s">
        </div>


        <svg
            class="landscape"
            viewBox="0 0 1440 420"
            preserveAspectRatio="xMinYMax meet"
            xmlns="http://www.w3.org/2000/svg">

            <defs>

                <radialGradient
                    id="sun"
                    cx="50%"
                    cy="50%"
                    r="50%">

                    <stop
                        offset="0"
                        stop-color="#ffd76a"
                        stop-opacity=".95" />

                    <stop
                        offset=".35"
                        stop-color="#f5b301"
                        stop-opacity=".4" />

                    <stop
                        offset="1"
                        stop-color="#f5b301"
                        stop-opacity="0" />

                </radialGradient>


                <pattern
                    id="win"
                    width="44"
                    height="52"
                    patternUnits="userSpaceOnUse">

                    <rect
                        x="12"
                        y="12"
                        width="18"
                        height="24"
                        rx="2"
                        fill="#ffd76a" />

                    <rect
                        x="12"
                        y="23.5"
                        width="18"
                        height="1.5"
                        fill="#b8860b"
                        opacity=".6" />

                </pattern>

            </defs>


            <circle
                class="sun-glow"
                cx="380"
                cy="300"
                r="330"
                fill="url(#sun)" />


            <path
                d="M-1500 290 L0 290 Q260 220 560 275 T1100 250 T1440 270 L3200 270 V420 H-1500Z"
                fill="#1a3f7d"
                opacity=".9" />

            <path
                d="M-1500 330 L0 330 Q320 280 640 320 T1200 300 T1440 320 L3200 320 V420 H-1500Z"
                fill="#12306a" />


            <g>

                <rect
                    x="150"
                    y="250"
                    width="176"
                    height="110"
                    fill="#1d3f80" />

                <rect
                    x="434"
                    y="250"
                    width="176"
                    height="110"
                    fill="#1d3f80" />

                <rect
                    x="150"
                    y="250"
                    width="176"
                    height="110"
                    fill="url(#win)" />

                <rect
                    x="434"
                    y="250"
                    width="176"
                    height="110"
                    fill="url(#win)" />

                <rect
                    x="150"
                    y="242"
                    width="176"
                    height="10"
                    fill="#122a5c" />

                <rect
                    x="424"
                    y="242"
                    width="196"
                    height="10"
                    fill="#122a5c" />

                <rect
                    x="326"
                    y="215"
                    width="108"
                    height="145"
                    fill="#234a93" />

                <polygon
                    points="316,215 380,175 444,215"
                    fill="#122a5c" />

                <rect
                    x="352"
                    y="112"
                    width="56"
                    height="103"
                    fill="#1f4488" />

                <polygon
                    points="342,112 380,62 418,112"
                    fill="#122a5c" />

                <line
                    x1="380"
                    y1="62"
                    x2="380"
                    y2="26"
                    stroke="#cbd7ee"
                    stroke-width="2" />

                <polygon
                    class="flag"
                    points="380,28 408,36 380,44"
                    fill="#f5b301" />

                <circle
                    cx="380"
                    cy="140"
                    r="15"
                    fill="#f6ecc0" />

                <rect
                    class="lit"
                    x="371"
                    y="172"
                    width="18"
                    height="26"
                    rx="9"
                    fill="#ffd76a" />

            </g>


            <g fill="#0d2350">

                <rect
                    x="104"
                    y="318"
                    width="8"
                    height="46" />

                <circle
                    cx="108"
                    cy="306"
                    r="30" />

                <rect
                    x="660"
                    y="322"
                    width="8"
                    height="42" />

                <circle
                    cx="664"
                    cy="310"
                    r="26" />

            </g>


            <path
                d="M-1500 372 L0 372 Q300 346 640 366 T1240 358 T1440 366 L3200 366 V420 H-1500Z"
                fill="#0a1a3d" />

            <path
                d="M-1500 396 L0 396 Q400 380 800 394 T1440 388 L3200 388 V420 H-1500Z"
                fill="#06122b" />

        </svg>

    </div>


    <!-- =========================
     PAGE
========================= -->

    <div class="page">


        <!-- LEFT SIDE -->

        <section class="intro">

            <div class="brand">

                🎓

                <div>
                    School<span>Connect</span>
                </div>

            </div>


            <h2>
                Your child's school journey starts here.
            </h2>


            <p>
                Create a free parent account or register your
                institution and connect with families.
            </p>


            <ul class="steps">

                <li>
                    <b>1</b>
                    Create your account
                </li>

                <li>
                    <b>2</b>
                    Choose your account type
                </li>

                <li>
                    <b>3</b>
                    Connect with schools and families
                </li>

            </ul>

        </section>


        <!-- RIGHT SIDE -->

        <div class="login-container">


            <?php if ($registration_success): ?>


                <!-- SUCCESS -->

                <div class="success-panel">

                    <div class="check">

                        <svg viewBox="0 0 40 40">

                            <path
                                d="M9 21 L17 29 L31 12" />

                        </svg>

                    </div>


                    <h2>
                        Account created!
                    </h2>


                    <p>

                        <?= htmlspecialchars($message) ?>

                    </p>


                    <a
                        href="login.php"
                        class="go-login">
                        Go to login →
                    </a>

                </div>


            <?php else: ?>


                <!-- LOGO -->

                <div class="logo">

                    <div class="logo-icon">
                        🎓
                    </div>

                    <h1>
                        Create account
                    </h1>

                    <p>
                        Join SchoolConnect today.
                    </p>

                </div>


                <!-- ERROR -->

                <?php if (!empty($message)): ?>

                    <div
                        class="message <?= htmlspecialchars($message_type) ?>"
                        role="alert">

                        <?= htmlspecialchars($message) ?>

                    </div>

                <?php endif; ?>


                <!-- FORM -->

                <form
                    method="POST"
                    action=""
                    id="registerForm">


                    <!-- ACCOUNT TYPE -->

                    <div class="form-group">

                        <span class="account-title">
                            I want to register as
                        </span>


                        <div class="account-options">


                            <div class="account-option">

                                <input
                                    type="radio"
                                    id="parent"
                                    name="account_type"
                                    value="parent"
                                    <?= (($_POST["account_type"] ?? "parent") === "parent") ? "checked" : "" ?>>

                                <label for="parent">

                                    👨‍👩‍👧

                                    Parent

                                </label>

                            </div>


                            <div class="account-option">

                                <input
                                    type="radio"
                                    id="institution"
                                    name="account_type"
                                    value="institution"
                                    <?= (($_POST["account_type"] ?? "") === "institution") ? "checked" : "" ?>>

                                <label for="institution">

                                    🏫

                                    Institution

                                </label>

                            </div>


                        </div>


                        <div
                            class="account-description"
                            id="accountDescription">
                            Parents can search for schools and make
                            booking requests. Institutions can submit
                            schools for admin verification.
                        </div>

                    </div>


                    <!-- NAME -->

                    <div class="form-group">

                        <label for="full_name">
                            Full name / Representative name
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>"
                            required>

                    </div>


                    <!-- EMAIL -->

                    <div class="form-group">

                        <label for="email">
                            Email address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email address"
                            autocomplete="email"
                            value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                            required>

                    </div>


                    <!-- PHONE -->

                    <div class="form-group">

                        <label for="phone">
                            Phone number
                        </label>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="e.g. 0241234567"
                            autocomplete="tel"
                            value="<?= htmlspecialchars($_POST["phone"] ?? "") ?>"
                            required>

                    </div>


                    <!-- PASSWORD -->

                    <div class="form-row">


                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>

                            <div class="field">

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    placeholder="Create a password"
                                    autocomplete="new-password"
                                    required>

                                <button
                                    type="button"
                                    class="toggle"
                                    data-target="password"
                                    aria-label="Show password">
                                    Show
                                </button>

                            </div>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm password
                            </label>

                            <div class="field">

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm password"
                                    autocomplete="new-password"
                                    required>

                                <button
                                    type="button"
                                    class="toggle"
                                    data-target="confirm_password"
                                    aria-label="Show password">
                                    Show
                                </button>

                            </div>

                        </div>


                    </div>


                    <!-- PASSWORD STRENGTH -->

                    <div
                        class="strength"
                        id="strength"
                        data-level="0"
                        aria-live="polite">

                        <i></i>
                        <i></i>
                        <i></i>
                        <i></i>

                        <small id="strengthLabel">
                            At least 6 characters
                        </small>

                    </div>


                    <div
                        class="hint"
                        id="matchHint"></div>


                    <!-- SUBMIT -->

                    <button
                        type="submit"
                        class="register-button"
                        id="registerBtn">
                        Create account
                    </button>


                </form>


                <div class="register-text">

                    Already have an account?

                    <a href="login.php">
                        Log in
                    </a>

                </div>


                <a
                    href="index.php"
                    class="home-link">
                    ← Back to home
                </a>


            <?php endif; ?>


        </div>

    </div>


    <script>
        (function() {


            /*
             * ACCOUNT TYPE DESCRIPTION
             */

            var accountInputs =
                document.querySelectorAll(
                    'input[name="account_type"]'
                );

            var accountDescription =
                document.getElementById(
                    "accountDescription"
                );


            function updateAccountDescription() {

                var selected =
                    document.querySelector(
                        'input[name="account_type"]:checked'
                    );


                if (!selected) {
                    return;
                }


                if (
                    selected.value ===
                    "institution"
                ) {

                    accountDescription.textContent =
                        "Institutions can create an account, submit school information and wait for an administrator to verify and approve the school.";

                } else {

                    accountDescription.textContent =
                        "Parents can search for approved schools, make booking requests and track their bookings.";

                }

            }


            accountInputs.forEach(function(input) {

                input.addEventListener(
                    "change",
                    updateAccountDescription
                );

            });


            updateAccountDescription();


            /*
             * FORM
             */

            var form =
                document.getElementById(
                    "registerForm"
                );


            if (!form) {
                return;
            }


            var pw =
                document.getElementById(
                    "password"
                );


            var cpw =
                document.getElementById(
                    "confirm_password"
                );


            var strength =
                document.getElementById(
                    "strength"
                );


            var label =
                document.getElementById(
                    "strengthLabel"
                );


            var hint =
                document.getElementById(
                    "matchHint"
                );


            var names = [

                "At least 6 characters",

                "Weak",

                "Fair",

                "Good",

                "Strong"

            ];


            /*
             * SHOW / HIDE PASSWORD
             */

            document
                .querySelectorAll(".toggle")
                .forEach(function(btn) {


                    btn.addEventListener(
                        "click",
                        function() {


                            var input =
                                document.getElementById(
                                    btn.dataset.target
                                );


                            var show =
                                input.type ===
                                "password";


                            input.type =
                                show ?
                                "text" :
                                "password";


                            input.classList.toggle(
                                "shown",
                                show
                            );


                            btn.textContent =
                                show ?
                                "Hide" :
                                "Show";


                            btn.setAttribute(
                                "aria-label",
                                show ?
                                "Hide password" :
                                "Show password"
                            );

                        }
                    );

                });


            /*
             * PASSWORD STRENGTH
             */

            function score(v) {

                if (!v) {
                    return 0;
                }


                var s = 0;


                if (v.length >= 6) {
                    s++;
                }


                if (v.length >= 10) {
                    s++;
                }


                if (
                    /[a-z]/.test(v) &&
                    /[A-Z]/.test(v)
                ) {
                    s++;
                }


                if (
                    /\d|[^A-Za-z0-9]/.test(v)
                ) {
                    s++;
                }


                return Math.max(
                    v.length >= 6 ? 1 : 0,
                    s
                );

            }


            function checkMatch() {


                if (!cpw.value) {

                    hint.textContent = "";

                    hint.className = "hint";

                    return;
                }


                var ok =
                    pw.value ===
                    cpw.value;


                hint.textContent =
                    ok ?
                    "✓ Passwords match" :
                    "Passwords do not match yet";


                hint.className =
                    "hint " +
                    (ok ? "ok" : "bad");

            }


            pw.addEventListener(
                "input",
                function() {


                    var l =
                        score(
                            pw.value
                        );


                    strength.dataset.level =
                        l;


                    label.textContent =
                        names[l];


                    checkMatch();

                }
            );


            cpw.addEventListener(
                "input",
                checkMatch
            );


            /*
             * PREVENT DOUBLE SUBMISSION
             */

            var btn =
                document.getElementById(
                    "registerBtn"
                );


            var sent = false;


            form.addEventListener(
                "submit",
                function(e) {


                    if (sent) {

                        e.preventDefault();

                        return;
                    }


                    sent = true;


                    btn.classList.add(
                        "loading"
                    );


                    btn.firstChild.nodeValue =
                        "Creating account";

                }
            );


        })();
    </script>


</body>

</html>