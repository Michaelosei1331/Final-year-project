<?php
session_start();
require_once "db.php";
$message = "";
$message_type = "";
// ==========================================
// WHEN LOGIN FORM IS SUBMITTED
// ==========================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get information from the form
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    // ==========================================
    // CHECK EMPTY FIELDS
    // ==========================================
    if (empty($email) || empty($password)) {
        $message = "Please enter your email and password.";
        $message_type = "error";
    }
    // ==========================================
    // CHECK EMAIL FORMAT
    // ==========================================
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "error";
    } else {
        // ==========================================
        // FIND USER
        // ==========================================
        $sql = "
            SELECT
                id,
                full_name,
                email,
                password,
                role
            FROM users
            WHERE email = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        // ==========================================
        // CHECK IF USER EXISTS
        // ==========================================
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // ==========================================
            // CHECK PASSWORD
            // ==========================================
            if (
                password_verify(
                    $password,
                    $user["password"]
                )
            ) {
                // ==========================================
                // CREATE SESSION
                // ==========================================
                session_regenerate_id(true);
                $_SESSION["user_id"] =
                    $user["id"];
                $_SESSION["full_name"] =
                    $user["full_name"];
                $_SESSION["email"] =
                    $user["email"];
                $_SESSION["role"] =
                    $user["role"];
                // ==========================================
                // SEND USER TO CORRECT DASHBOARD
                // ==========================================
                $redirects = [
                    "admin"       => "admin-dashboard.php",
                    "institution" => "institution-dashboard.php",
                    "parent"      => "parent-dashboard.php",
                ];

                header(
                    "Location: " . ($redirects[$user["role"]] ?? "parent-dashboard.php")
                );
                exit;
            } else {
                $message =
                    "Incorrect password.";
                $message_type =
                    "error";
            }
        } else {
            $message =
                "No account was found with this email.";
            $message_type =
                "error";
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SchoolConnect</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0b1b3a;
            --blue: #0756b8;
            --sky: #e8f1fd;
            --sun: #f5b301;
            --muted: #62708a;
            --line: #e5eaf3;
            --red: #dc2626;
            --display: "Bricolage Grotesque", "Segoe UI", Arial, sans-serif;
            --body: "DM Sans", "Segoe UI", Arial, sans-serif;
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

        /* ---------- SCENE ---------- */
        .scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            /* Optional: put a real photo at images/login-bg.jpg and it shows through automatically */
            background:
                linear-gradient(180deg, rgba(6, 18, 43, .55), rgba(11, 58, 134, .35)),
                url("images/login-bg.jpg") center / cover no-repeat,
                linear-gradient(180deg, #06122b 0%, #0b2a63 38%, #1f5aa8 68%, #f2a93b 100%);
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
            animation: twinkle 3.5s ease-in-out infinite;
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
            animation: cloud var(--s) linear infinite;
            animation-delay: var(--d);
        }

        @keyframes cloud {
            to {
                transform: translateX(calc(100vw + 520px));
            }
        }

        .landscape {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: clamp(240px, 36vh, 460px);
        }

        .sun-glow {
            transform-origin: 380px 300px;
            animation: glow 7s ease-in-out infinite alternate;
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
            animation: flicker 6s ease-in-out infinite;
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
            transform-origin: 380px 60px;
            animation: flap 2.4s ease-in-out infinite alternate;
        }

        @keyframes flap {
            from {
                transform: skewY(0deg);
            }

            to {
                transform: skewY(-6deg) scaleX(.94);
            }
        }

        /* ---------- PAGE LAYOUT ---------- */
        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            width: 90%;
            max-width: 1180px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 440px;
            gap: 60px;
            align-items: center;
            padding: 40px 0 calc(36vh - 10px);
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
            font-size: clamp(34px, 4.6vw, 54px);
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

        .points {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .points span {
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .2);
            backdrop-filter: blur(6px);
            padding: 8px 15px;
            border-radius: 99px;
            font-size: 14px;
            font-weight: 500;
        }

        /* ---------- CARD ---------- */
        .login-container {
            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, .6);
            border-radius: 26px;
            padding: 38px 36px;
            box-shadow: 0 40px 80px -30px rgba(0, 0, 0, .65);
            animation: rise .8s cubic-bezier(.2, .7, .2, 1) .1s both;
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

        .logo {
            text-align: center;
            margin-bottom: 26px;
        }

        .logo-icon {
            width: 62px;
            height: 62px;
            margin: 0 auto 14px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--ink), #0b3a86);
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 30px;
            box-shadow: 0 14px 24px -12px rgba(11, 27, 58, .7);
            animation: bob 4s ease-in-out infinite;
        }

        @keyframes bob {
            50% {
                transform: translateY(-5px) rotate(-4deg);
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
            background: var(--red);
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f7b5b5;
            animation: shake .5s ease both;
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

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: 700;
        }

        .field {
            position: relative;
        }

        input {
            width: 100%;
            font-family: var(--body);
            font-size: 15px;
            color: var(--ink);
            padding: 13px 15px;
            background: #f4f6fb;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:hover {
            border-color: #c9d4e8;
        }

        input:focus {
            outline: none;
            background: #fff;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px var(--sky);
        }

        .field input[type="password"],
        .field input.shown {
            padding-right: 76px;
        }

        .toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            cursor: pointer;
            font-family: var(--body);
            color: var(--blue);
            font-size: 13px;
            font-weight: 700;
            padding: 6px 10px;
            border-radius: 8px;
            transition: background .2s;
        }

        .toggle:hover {
            background: var(--sky);
        }

        .login-button {
            width: 100%;
            border: none;
            cursor: pointer;
            font-family: var(--body);
            font-size: 16px;
            font-weight: 700;
            padding: 14px;
            margin-top: 4px;
            border-radius: 13px;
            background: var(--ink);
            color: #fff;
            transition: transform .2s, background .2s, box-shadow .2s;
        }

        .login-button:hover {
            background: var(--blue);
            transform: translateY(-2px);
            box-shadow: 0 14px 26px -12px rgba(7, 86, 184, .85);
        }

        .login-button:active {
            transform: scale(.98);
        }

        .login-button.loading {
            background: var(--blue);
            pointer-events: none;
        }

        .login-button.loading::after {
            content: "";
            display: inline-block;
            width: 14px;
            height: 14px;
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

        .register-text {
            text-align: center;
            margin-top: 22px;
            color: var(--muted);
            font-size: 14px;
        }

        .register-text a {
            color: var(--blue);
            font-weight: 700;
            text-decoration: none;
        }

        .register-text a:hover {
            text-decoration: underline;
        }

        .home-link {
            display: block;
            text-align: center;
            margin-top: 14px;
            color: var(--muted);
            font-size: 13px;
            text-decoration: none;
            transition: color .2s, transform .2s;
        }

        .home-link:hover {
            color: var(--blue);
            transform: translateX(-3px);
        }

        /* ---------- RESPONSIVE ---------- */
        @media (max-width: 960px) {
            .page {
                grid-template-columns: 1fr;
                max-width: 460px;
                padding-bottom: 40px;
            }

            .intro {
                display: none;
            }
        }

        @media (max-width: 500px) {
            .page {
                width: 94%;
                padding: 24px 0 32px;
            }

            .login-container {
                padding: 28px 22px;
            }

            .landscape {
                height: 30vh;
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

    <!-- BACKGROUND SCENE -->
    <div class="scene" aria-hidden="true">

        <div class="stars">
            <span style="--x:8%;--y:9%;--d:0s"></span><span style="--x:18%;--y:22%;--d:1s"></span>
            <span style="--x:31%;--y:7%;--d:2s"></span><span style="--x:47%;--y:16%;--d:.5s"></span>
            <span style="--x:58%;--y:6%;--d:1.6s"></span><span style="--x:72%;--y:19%;--d:2.4s"></span>
            <span style="--x:83%;--y:8%;--d:.8s"></span><span style="--x:93%;--y:24%;--d:1.9s"></span>
            <span style="--x:12%;--y:33%;--d:2.8s"></span><span style="--x:65%;--y:31%;--d:.3s"></span>
        </div>

        <div class="cloud" style="--t:12%;--w:300px;--s:70s;--d:-20s"></div>
        <div class="cloud" style="--t:26%;--w:220px;--s:95s;--d:-60s"></div>
        <div class="cloud" style="--t:40%;--w:340px;--s:120s;--d:-5s"></div>

        <svg class="landscape" viewBox="0 0 1440 420" preserveAspectRatio="xMinYMax meet" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="sun" cx="50%" cy="50%" r="50%">
                    <stop offset="0" stop-color="#ffd76a" stop-opacity=".95" />
                    <stop offset=".35" stop-color="#f5b301" stop-opacity=".4" />
                    <stop offset="1" stop-color="#f5b301" stop-opacity="0" />
                </radialGradient>
                <pattern id="win" width="44" height="52" patternUnits="userSpaceOnUse">
                    <rect x="12" y="12" width="18" height="24" rx="2" fill="#ffd76a" />
                    <rect x="12" y="23.5" width="18" height="1.5" fill="#b8860b" opacity=".6" />
                </pattern>
            </defs>

            <!-- sun glow behind the school -->
            <circle class="sun-glow" cx="380" cy="300" r="330" fill="url(#sun)" />

            <!-- far hills -->
            <path d="M-1500 290 L0 290 Q260 220 560 275 T1100 250 T1440 270 L3200 270 V420 H-1500Z" fill="#1a3f7d" opacity=".9" />
            <path d="M-1500 330 L0 330 Q320 280 640 320 T1200 300 T1440 320 L3200 320 V420 H-1500Z" fill="#12306a" />

            <!-- SCHOOL -->
            <g>
                <!-- wings -->
                <rect x="150" y="250" width="176" height="110" fill="#1d3f80" />
                <rect x="434" y="250" width="176" height="110" fill="#1d3f80" />
                <rect x="150" y="250" width="176" height="110" fill="url(#win)" />
                <rect x="434" y="250" width="176" height="110" fill="url(#win)" />
                <rect x="140" y="242" width="196" height="10" fill="#122a5c" />
                <rect x="424" y="242" width="196" height="10" fill="#122a5c" />

                <!-- main hall -->
                <rect x="326" y="215" width="108" height="145" fill="#234a93" />
                <polygon points="316,215 380,175 444,215" fill="#122a5c" />
                <rect x="345" y="235" width="70" height="8" fill="#122a5c" opacity=".5" />

                <!-- tower -->
                <rect x="352" y="112" width="56" height="103" fill="#1f4488" />
                <polygon points="342,112 380,62 418,112" fill="#122a5c" />
                <line x1="380" y1="62" x2="380" y2="26" stroke="#cbd7ee" stroke-width="2" />
                <polygon class="flag" points="380,28 408,36 380,44" fill="#f5b301" />
                <circle cx="380" cy="140" r="15" fill="#f6ecc0" />
                <line x1="380" y1="140" x2="380" y2="130" stroke="#122a5c" stroke-width="2" />
                <line x1="380" y1="140" x2="387" y2="143" stroke="#122a5c" stroke-width="2" />
                <rect class="lit" x="371" y="172" width="18" height="26" rx="9" fill="#ffd76a" />

                <!-- door + steps -->
                <path d="M362 360 V318 Q380 296 398 318 V360Z" fill="#ffd76a" class="lit" style="--d:2s" />
                <rect x="352" y="358" width="56" height="6" fill="#122a5c" />
                <rect x="344" y="364" width="72" height="6" fill="#0f2350" />

                <!-- hall windows -->
                <rect class="lit" x="338" y="262" width="16" height="24" rx="2" fill="#ffd76a" style="--d:1s" />
                <rect class="lit" x="406" y="262" width="16" height="24" rx="2" fill="#ffd76a" style="--d:3s" />
            </g>

            <!-- trees around the school -->
            <g fill="#0d2350">
                <rect x="104" y="318" width="8" height="46" />
                <circle cx="108" cy="306" r="30" />
                <rect x="660" y="322" width="8" height="42" />
                <circle cx="664" cy="310" r="26" />
                <rect x="700" y="332" width="7" height="34" />
                <circle cx="703" cy="322" r="20" />
            </g>

            <!-- distant trees on the right -->
            <g fill="#0f2957">
                <circle cx="930" cy="300" r="28" />
                <circle cx="972" cy="308" r="22" />
                <circle cx="1260" cy="290" r="34" />
                <circle cx="1312" cy="302" r="26" />
            </g>

            <!-- foreground -->
            <path d="M-1500 372 L0 372 Q300 346 640 366 T1240 358 T1440 366 L3200 366 V420 H-1500Z" fill="#0a1a3d" />
            <path d="M-1500 396 L0 396 Q400 380 800 394 T1440 388 L3200 388 V420 H-1500Z" fill="#06122b" />
        </svg>

    </div>

    <div class="page">

        <!-- LEFT: WELCOME -->
        <section class="intro">
            <div class="brand">🎓 <div>School<span>Connect</span></div>
            </div>
            <h2>Find the right school for your child.</h2>
            <p>Browse schools, send booking requests and follow every decision in one place.</p>
            <div class="points">
                <span>🏫 Browse schools</span>
                <span>📅 Book online</span>
                <span>🔔 Get updates</span>
            </div>
        </section>

        <!-- RIGHT: LOGIN CARD -->
        <div class="login-container">

            <div class="logo">
                <div class="logo-icon">🎓</div>
                <h1>Welcome back</h1>
                <p>Log in to your SchoolConnect account.</p>
            </div>

            <!-- ERROR MESSAGE -->
            <?php if (!empty($message)): ?>
                <div class="message <?= htmlspecialchars($message_type) ?>" role="alert">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" action="" id="loginForm">

                <div class="form-group">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your email address"
                        autocomplete="email"
                        value="<?= htmlspecialchars($_POST["email"] ?? "") ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="field">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button type="button" class="toggle" id="togglePw" aria-label="Show password">Show</button>
                    </div>
                </div>

                <button type="submit" class="login-button" id="loginBtn">Log in</button>

            </form>

            <div class="register-text">
                Don't have an account?
                <a href="register.php">Create account</a>
            </div>

            <a href="index.php" class="home-link">← Back to home</a>

        </div>

    </div>

    <script>
        (function() {
            // Show / hide password
            var pw = document.getElementById("password");
            var toggle = document.getElementById("togglePw");

            toggle.addEventListener("click", function() {
                var show = pw.type === "password";
                pw.type = show ? "text" : "password";
                pw.classList.toggle("shown", show);
                toggle.textContent = show ? "Hide" : "Show";
                toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
            });

            // Spinner + block double submits
            var form = document.getElementById("loginForm");
            var btn = document.getElementById("loginBtn");
            var sent = false;

            form.addEventListener("submit", function(e) {
                if (sent) {
                    e.preventDefault();
                    return;
                }
                sent = true;
                btn.classList.add("loading");
                btn.firstChild.nodeValue = "Logging in";
            });
        })();
    </script>

</body>

</html>