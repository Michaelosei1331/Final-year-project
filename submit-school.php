<?php
session_start();
require_once "db.php";

// ---------- Login + role check (institutions only) ----------
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "institution") {
    header("Location: login.php");
    exit;
}
$user_id = intval($_SESSION["user_id"]);
$inst_name = $_SESSION["full_name"] ?? "Institution";

if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";
$fields = ["school_name", "location", "latitude", "longitude", "school_type", "school_fees", "facilities", "description", "phone", "email", "website", "registration_number"];
$old = array_fill_keys($fields, "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {
        $error = "Session expired. Please try again.";
    } else {
        foreach ($fields as $f) {
            $old[$f] = trim($_POST[$f] ?? "");
        }
        $fees = (float) $old["school_fees"];
        $lat  = is_numeric($old["latitude"])  ? (float) $old["latitude"]  : null;
        $lng  = is_numeric($old["longitude"]) ? (float) $old["longitude"] : null;

        if ($old["school_name"] === "" || $old["location"] === "" || $old["phone"] === "" || $old["email"] === "") {
            $error = "School name, location, phone and email are required.";
        } elseif ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $error = "Please mark your school's exact position on the map.";
        } elseif (!in_array($old["school_type"], ["Private", "Public", "International"], true)) {
            $error = "Please choose a school type.";
        } elseif (!filter_var($old["email"], FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email.";
        } elseif ($old["website"] !== "" && !filter_var($old["website"], FILTER_VALIDATE_URL)) {
            $error = "Website must be a full link, e.g. https://myschool.com";
        } else {
            // Duplicate check (ignores previously rejected entries)
            $dup = $conn->prepare(
                "SELECT id FROM schools
                 WHERE LOWER(school_name) = LOWER(?) AND LOWER(location) = LOWER(?)
                 AND approval_status <> 'rejected' LIMIT 1"
            );
            $dup->bind_param("ss", $old["school_name"], $old["location"]);
            $dup->execute();
            $exists = $dup->get_result()->num_rows > 0;
            $dup->close();

            // Optional image (validated as a real image, random file name)
            $image = "";
            if (!$exists && !empty($_FILES["image"]["name"])) {
                $info = @getimagesize($_FILES["image"]["tmp_name"]);
                $allowed = [IMAGETYPE_JPEG => "jpg", IMAGETYPE_PNG => "png", IMAGETYPE_WEBP => "webp"];
                if (!$info || !isset($allowed[$info[2]]) || $_FILES["image"]["size"] > 3 * 1024 * 1024) {
                    $error = "Image must be a JPG, PNG or WEBP under 3MB.";
                } else {
                    $dir = "uploads/schools/";
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    $path = $dir . bin2hex(random_bytes(8)) . "." . $allowed[$info[2]];
                    if (move_uploaded_file($_FILES["image"]["tmp_name"], $path)) {
                        $image = $path;
                    }
                }
            }

            if ($error === "") {
                if ($exists) {
                    $error = "A school with this name and location is already listed or awaiting approval.";
                } else {
                    $place_id = "";
                    $ins = $conn->prepare(
                        "INSERT INTO schools
                            (school_name, location, latitude, longitude, place_id, school_type, school_fees,
                             facilities, description, phone, email, website, registration_number, image,
                             status, approval_status, submitted_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inactive', 'pending', ?)"
                    );
                    $ins->bind_param(
                        "ssddssdsssssssi",
                        $old["school_name"],
                        $old["location"],
                        $lat,
                        $lng,
                        $place_id,
                        $old["school_type"],
                        $fees,
                        $old["facilities"],
                        $old["description"],
                        $old["phone"],
                        $old["email"],
                        $old["website"],
                        $old["registration_number"],
                        $image,
                        $user_id
                    );
                    if ($ins->execute()) {
                        $message = "Submitted! An admin will verify your school before it becomes visible to parents.";
                        $old = array_fill_keys($fields, "");
                    } else {
                        $error = "Could not save the school. Please try again.";
                    }
                    $ins->close();
                }
            }
        }
    }
}

$list = $conn->prepare(
    "SELECT school_name, location, approval_status, review_note, created_at
     FROM schools WHERE submitted_by = ? ORDER BY created_at DESC"
);
$list->bind_param("i", $user_id);
$list->execute();
$mine = $list->get_result();
function v($a, $k)
{
    return htmlspecialchars($a[$k]);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List your school | SchoolConnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        :root {
            --ink: #0b1b3a;
            --blue: #0756b8;
            --blue-d: #05449a;
            --sky: #e8f1fd;
            --chalk: #f4f6fb;
            --sun: #f5b301;
            --muted: #62708a;
            --line: #e5eaf3;
            --green: #16a34a;
            --red: #dc2626;
            --display: "Bricolage Grotesque", "Segoe UI", Arial, sans-serif;
            --body: "DM Sans", "Segoe UI", Arial, sans-serif
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box
        }

        body {
            font-family: var(--body);
            background: var(--chalk);
            color: var(--ink);
            line-height: 1.55
        }

        a {
            color: inherit
        }

        /* NAV */
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: rgba(255, 255, 255, .92);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
            padding: 14px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px
        }

        .logo {
            font-family: var(--display);
            font-weight: 800;
            font-size: 21px
        }

        .logo span {
            color: var(--blue)
        }

        .nav-links {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap
        }

        .nav-links a {
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            padding: 9px 14px;
            border-radius: 10px;
            color: var(--muted);
            transition: .2s
        }

        .nav-links a:hover {
            background: var(--sky);
            color: var(--blue)
        }

        .nav-links a.active {
            background: var(--sky);
            color: var(--blue)
        }

        /* HERO */
        .hero {
            background: linear-gradient(135deg, #06275f 0%, #0756b8 60%, #2a7de1 100%);
            color: #fff;
            padding: 46px 5% 90px;
            position: relative;
            overflow: hidden
        }

        .hero::before,
        .hero::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, .07)
        }

        .hero::before {
            width: 380px;
            height: 380px;
            right: -90px;
            top: -140px
        }

        .hero::after {
            width: 240px;
            height: 240px;
            right: 200px;
            bottom: -150px;
            background: rgba(245, 179, 1, .15)
        }

        .hero-inner {
            max-width: 1120px;
            margin: 0 auto;
            position: relative;
            z-index: 1
        }

        .eyebrow {
            display: inline-block;
            background: rgba(255, 255, 255, .14);
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 14px
        }

        .hero h1 {
            font-family: var(--display);
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.12;
            margin-bottom: 10px
        }

        .hero p {
            max-width: 620px;
            opacity: .88;
            font-size: 16px
        }

        .steps {
            display: flex;
            gap: 14px;
            margin-top: 26px;
            flex-wrap: wrap
        }

        .step {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, .12);
            padding: 10px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700
        }

        .step b {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--sun);
            color: var(--ink);
            display: grid;
            place-items: center;
            font-size: 13px
        }

        /* LAYOUT */
        .wrap {
            max-width: 1120px;
            margin: -56px auto 60px;
            padding: 0 5%;
            position: relative;
            z-index: 2
        }

        .layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
            align-items: start
        }

        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 22px;
            box-shadow: 0 18px 40px -26px rgba(11, 27, 58, .35)
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px
        }

        .card-title .num {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            background: var(--sky);
            color: var(--blue);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-family: var(--display)
        }

        .card-title h2 {
            font-family: var(--display);
            font-size: 20px;
            line-height: 1.2
        }

        .card-title small {
            display: block;
            color: var(--muted);
            font-weight: 400;
            font-size: 13px;
            font-family: var(--body)
        }

        /* FORM */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px
        }

        .full {
            grid-column: 1/-1
        }

        label {
            display: block;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 6px
        }

        label small {
            color: var(--muted);
            font-weight: 400
        }

        .req {
            color: var(--red)
        }

        .input-wrap {
            position: relative
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
            pointer-events: none
        }

        .input-wrap input,
        .input-wrap select {
            padding-left: 40px
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--line);
            border-radius: 12px;
            font: inherit;
            background: #fff;
            color: var(--ink);
            transition: .2s
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px var(--sky)
        }

        textarea {
            min-height: 96px;
            resize: vertical
        }

        input[type=file] {
            padding: 10px;
            background: var(--chalk);
            border-style: dashed;
            cursor: pointer
        }

        /* MAP */
        .map-search {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .map-search .input-wrap {
            flex: 1;
            min-width: 220px
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--blue);
            color: #fff;
            border: 0;
            padding: 12px 20px;
            border-radius: 12px;
            font: 700 15px var(--body);
            cursor: pointer;
            text-decoration: none;
            transition: .2s
        }

        .btn:hover {
            background: var(--blue-d);
            transform: translateY(-1px)
        }

        .btn:disabled {
            opacity: .6;
            cursor: wait;
            transform: none
        }

        .btn.ghost {
            background: var(--sky);
            color: var(--blue)
        }

        .btn.ghost:hover {
            background: #d5e6fb
        }

        .btn.big {
            width: 100%;
            padding: 16px;
            font-size: 17px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--blue), #2a7de1);
            box-shadow: 0 12px 24px -12px rgba(7, 86, 184, .7)
        }

        .map-box {
            margin-top: 14px;
            border: 1.5px solid var(--line);
            border-radius: 16px;
            overflow: hidden
        }

        #schoolMap {
            height: 380px;
            width: 100%;
            z-index: 1
        }

        .map-foot {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
            font-size: 13px;
            color: var(--muted)
        }

        .chip {
            background: var(--sky);
            color: var(--blue);
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px
        }

        #locationStatus {
            color: var(--muted)
        }

        #locationStatus.ok {
            color: var(--green);
            font-weight: 700
        }

        #locationStatus.bad {
            color: var(--red);
            font-weight: 700
        }

        /* ALERTS */
        .alert {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            font-weight: 500
        }

        .alert.ok {
            background: #dcfce7;
            color: #166534
        }

        .alert.err {
            background: #fee2e2;
            color: #991b1b
        }

        .alert.warn {
            background: #fef3c7;
            color: #92400e
        }

        /* SIDEBAR */
        .side {
            position: sticky;
            top: 84px
        }

        .side .card {
            padding: 22px
        }

        .side h3 {
            font-family: var(--display);
            font-size: 17px;
            margin-bottom: 12px
        }

        .check {
            list-style: none
        }

        .check li {
            display: flex;
            gap: 10px;
            margin-bottom: 11px;
            font-size: 14px;
            color: #334155
        }

        .check i {
            color: var(--green);
            margin-top: 4px
        }

        .help {
            background: linear-gradient(135deg, #fff8e1, #fff3c4);
            border-color: #fde9b0
        }

        .help p {
            font-size: 14px;
            color: #7a5b00
        }

        /* SUBMISSIONS */
        .sub-item {
            padding: 16px 0;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap
        }

        .sub-item:first-of-type {
            border-top: 0;
            padding-top: 0
        }

        .sub-item h4 {
            font-size: 16px
        }

        .meta {
            color: var(--muted);
            font-size: 14px
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 13px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            height: fit-content
        }

        .pill.pending {
            background: #fef3c7;
            color: #92400e
        }

        .pill.approved {
            background: #dcfce7;
            color: #166534
        }

        .pill.rejected {
            background: #fee2e2;
            color: #991b1b
        }

        .empty {
            text-align: center;
            color: var(--muted);
            padding: 18px 0
        }

        @media(max-width:900px) {
            .layout {
                grid-template-columns: 1fr
            }

            .side {
                position: static
            }
        }

        @media(max-width:600px) {
            .grid {
                grid-template-columns: 1fr
            }

            .card {
                padding: 20px
            }

            .hero {
                padding-bottom: 80px
            }
        }
    </style>
</head>

<body>

    <nav class="navbar">
        <div class="logo">🎓 School<span>Connect</span></div>
        <div class="nav-links">
            <a href="institution-dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="submit-school.php" class="active"><i class="fa-solid fa-plus"></i> List a school</a>
            <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </nav>

    <header class="hero">
        <div class="hero-inner">
            <span class="eyebrow"><i class="fa-solid fa-building-columns"></i> For institutions</span>
            <h1>List your school and reach<br>parents looking for the right fit</h1>
            <p>Share your school's details and pin it on the map. Our team verifies every listing so parents can trust what they see.</p>
            <div class="steps">
                <div class="step"><b>1</b> Submit details</div>
                <div class="step"><b>2</b> Admin verification</div>
                <div class="step"><b>3</b> Go live for parents</div>
            </div>
        </div>
    </header>

    <main class="wrap">
        <?php if ($message): ?><div class="alert ok"><i class="fa-solid fa-circle-check"></i><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert err"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="layout">
            <form id="schoolForm" method="post" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION["csrf"]) ?>">
                <input type="hidden" id="latitude" name="latitude" value="<?= v($old, "latitude") ?>">
                <input type="hidden" id="longitude" name="longitude" value="<?= v($old, "longitude") ?>">

                <!-- 1. BASIC INFO -->
                <section class="card">
                    <div class="card-title">
                        <div class="num">1</div>
                        <h2>Basic information<small>Tell parents who you are</small></h2>
                    </div>
                    <div class="grid">
                        <div class="full"><label>School name <span class="req">*</span></label>
                            <div class="input-wrap"><i class="fa-solid fa-school"></i>
                                <input name="school_name" required placeholder="e.g. Bright Future Academy" value="<?= v($old, "school_name") ?>">
                            </div>
                        </div>
                        <div><label>School type <span class="req">*</span></label>
                            <div class="input-wrap"><i class="fa-solid fa-layer-group"></i>
                                <select name="school_type" required>
                                    <option value="">Select type</option>
                                    <?php foreach (["Private", "Public", "International"] as $t): ?>
                                        <option value="<?= $t ?>" <?= $old["school_type"] === $t ? "selected" : "" ?>><?= $t ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div><label>School fees (GHS) <span class="req">*</span></label>
                            <div class="input-wrap"><i class="fa-solid fa-coins"></i>
                                <input type="number" name="school_fees" min="0" step="0.01" required placeholder="e.g. 3500.00" value="<?= v($old, "school_fees") ?>">
                            </div>
                        </div>
                        <div class="full"><label>About the school</label>
                            <textarea name="description" placeholder="Curriculum, classes offered, school values…"><?= v($old, "description") ?></textarea>
                        </div>
                        <div class="full"><label>Facilities <small>(separate with commas)</small></label>
                            <textarea name="facilities" placeholder="Library, Computer Lab, Playground, Science Lab, School Bus"><?= v($old, "facilities") ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- 2. LOCATION -->
                <section class="card">
                    <div class="card-title">
                        <div class="num">2</div>
                        <h2>Location<small>Search an address, click the map, or drag the pin</small></h2>
                    </div>
                    <label>School address <span class="req">*</span></label>
                    <div class="map-search">
                        <div class="input-wrap"><i class="fa-solid fa-location-dot"></i>
                            <input id="school_location" name="location" autocomplete="off" required
                                placeholder="e.g. East Legon, Accra" value="<?= v($old, "location") ?>">
                        </div>
                        <button type="button" id="searchLocation" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
                        <button type="button" id="useMyLocation" class="btn ghost"><i class="fa-solid fa-crosshairs"></i> Use my location</button>
                    </div>
                    <div class="map-box">
                        <div id="schoolMap"></div>
                    </div>
                    <div class="map-foot">
                        <span id="locationStatus" aria-live="polite">Place the pin exactly where your school is.</span>
                        <span class="chip" id="coordChip"><i class="fa-solid fa-map-pin"></i> No pin yet</span>
                    </div>
                </section>

                <!-- 3. CONTACT -->
                <section class="card">
                    <div class="card-title">
                        <div class="num">3</div>
                        <h2>Contact &amp; verification<small>Used by our team to confirm your school exists</small></h2>
                    </div>
                    <div class="grid">
                        <div><label>Phone <span class="req">*</span></label>
                            <div class="input-wrap"><i class="fa-solid fa-phone"></i>
                                <input name="phone" required placeholder="e.g. 024 000 0000" value="<?= v($old, "phone") ?>">
                            </div>
                        </div>
                        <div><label>Email <span class="req">*</span></label>
                            <div class="input-wrap"><i class="fa-solid fa-envelope"></i>
                                <input type="email" name="email" required placeholder="info@yourschool.com" value="<?= v($old, "email") ?>">
                            </div>
                        </div>
                        <div><label>Registration / licence no. <small>(recommended)</small></label>
                            <div class="input-wrap"><i class="fa-solid fa-id-card"></i>
                                <input name="registration_number" placeholder="e.g. GES/REG/12345" value="<?= v($old, "registration_number") ?>">
                            </div>
                        </div>
                        <div><label>Website <small>(optional)</small></label>
                            <div class="input-wrap"><i class="fa-solid fa-globe"></i>
                                <input name="website" placeholder="https://" value="<?= v($old, "website") ?>">
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. PHOTO -->
                <section class="card">
                    <div class="card-title">
                        <div class="num">4</div>
                        <h2>School photo<small>Optional · JPG, PNG or WEBP · max 3MB</small></h2>
                    </div>
                    <input type="file" name="image" accept="image/*">
                </section>

                <button type="submit" class="btn big"><i class="fa-solid fa-paper-plane"></i> Submit for verification</button>
            </form>

            <aside class="side">
                <div class="card">
                    <h3><i class="fa-solid fa-clipboard-check" style="color:var(--blue)"></i> Before you submit</h3>
                    <ul class="check">
                        <li><i class="fa-solid fa-circle-check"></i> Use your school's official name</li>
                        <li><i class="fa-solid fa-circle-check"></i> Pin the exact entrance on the map</li>
                        <li><i class="fa-solid fa-circle-check"></i> Add a working phone and email</li>
                        <li><i class="fa-solid fa-circle-check"></i> Include your registration number to speed up approval</li>
                    </ul>
                </div>
                <div class="card help">
                    <h3><i class="fa-solid fa-hourglass-half" style="color:#b45309"></i> What happens next?</h3>
                    <p>An administrator checks that your school exists. You'll see the result on your dashboard, and approved schools appear to parents right away.</p>
                </div>
            </aside>
        </div>

        <section class="card" style="margin-top:6px">
            <div class="card-title">
                <div class="num"><i class="fa-solid fa-list" style="font-size:15px"></i></div>
                <h2>Your submissions<small>Track the status of every school you've listed</small></h2>
            </div>
            <?php if ($mine->num_rows === 0): ?>
                <p class="empty">You haven't submitted any school yet.</p>
                <?php else: while ($s = $mine->fetch_assoc()): ?>
                    <div class="sub-item">
                        <div>
                            <h4><?= htmlspecialchars($s["school_name"]) ?></h4>
                            <div class="meta"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($s["location"]) ?> · Submitted <?= date("d M Y", strtotime($s["created_at"])) ?></div>
                            <?php if ($s["approval_status"] === "rejected" && $s["review_note"]): ?>
                                <div class="alert warn" style="margin:10px 0 0"><i class="fa-solid fa-triangle-exclamation"></i>Admin note: <?= htmlspecialchars($s["review_note"]) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="pill <?= htmlspecialchars($s["approval_status"]) ?>"><?= htmlspecialchars($s["approval_status"]) ?></span>
                    </div>
            <?php endwhile;
            endif; ?>
        </section>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const latInput = document.getElementById("latitude");
        const lngInput = document.getElementById("longitude");
        const locInput = document.getElementById("school_location");
        const statusEl = document.getElementById("locationStatus");
        const chip = document.getElementById("coordChip");
        let map, marker, autoFilled = !locInput.value.trim();

        function setStatus(text, cls) {
            statusEl.textContent = text;
            statusEl.className = cls || "";
        }

        function setPosition(lat, lng, zoom) {
            marker.setLatLng([lat, lng]);
            if (zoom) map.setView([lat, lng], zoom);
            latInput.value = Number(lat).toFixed(8);
            lngInput.value = Number(lng).toFixed(8);
            chip.innerHTML = '<i class="fa-solid fa-map-pin"></i> ' + Number(lat).toFixed(5) + ", " + Number(lng).toFixed(5);
        }

        // Fill the address box from the pin (only if the user hasn't typed their own)
        async function reverseGeocode(lat, lng) {
            try {
                const r = await fetch("https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" + lat + "&lon=" + lng, {
                    headers: {
                        "Accept": "application/json"
                    }
                });
                if (!r.ok) return;
                const d = await r.json();
                if (d.display_name && (autoFilled || !locInput.value.trim())) {
                    locInput.value = d.display_name;
                    autoFilled = true;
                }
            } catch (e) {
                /* the pin is still saved */ }
        }

        function pinMoved(lat, lng, zoom) {
            setPosition(lat, lng, zoom);
            setStatus("✓ Pin placed. Drag it to fine-tune the position.", "ok");
            reverseGeocode(lat, lng);
        }

        document.addEventListener("DOMContentLoaded", function() {
            const hasPin = latInput.value !== "" && lngInput.value !== "";
            const start = hasPin ? [parseFloat(latInput.value), parseFloat(lngInput.value)] : [5.6037, -0.1870]; // Accra

            map = L.map("schoolMap").setView(start, hasPin ? 16 : 12);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
            }).addTo(map);

            marker = L.marker(start, {
                draggable: true,
                autoPan: true
            }).addTo(map);
            if (hasPin) {
                setPosition(start[0], start[1]);
                setStatus("✓ Pin placed. Drag it to fine-tune the position.", "ok");
            }

            map.on("click", e => pinMoved(e.latlng.lat, e.latlng.lng));
            marker.on("dragend", e => {
                const p = e.target.getLatLng();
                pinMoved(p.lat, p.lng);
            });
            locInput.addEventListener("input", () => {
                autoFilled = false;
            });

            document.getElementById("searchLocation").addEventListener("click", searchLocation);
            locInput.addEventListener("keydown", e => {
                if (e.key === "Enter") {
                    e.preventDefault();
                    searchLocation();
                }
            });
            document.getElementById("useMyLocation").addEventListener("click", useMyLocation);

            setTimeout(() => map.invalidateSize(), 250);

            // Require a pin before submitting
            document.getElementById("schoolForm").addEventListener("submit", function(e) {
                if (!latInput.value || !lngInput.value) {
                    e.preventDefault();
                    setStatus("Please place the pin on the map before submitting.", "bad");
                    document.getElementById("schoolMap").scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });
                }
            });
        });

        async function searchLocation() {
            const q = locInput.value.trim();
            if (!q) {
                setStatus("Enter an address or area to search.", "bad");
                return;
            }
            const btn = document.getElementById("searchLocation");
            btn.disabled = true;
            setStatus("Searching…");
            try {
                const r = await fetch("https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=gh&q=" + encodeURIComponent(q), {
                    headers: {
                        "Accept": "application/json"
                    }
                });
                if (!r.ok) throw new Error("Search is temporarily unavailable.");
                const res = await r.json();
                if (!res.length) {
                    setStatus("No match found. Try a nearby landmark, or click the map directly.", "bad");
                    return;
                }
                setPosition(Number(res[0].lat), Number(res[0].lon), 17);
                autoFilled = false;
                setStatus("✓ Location found. Check the pin and adjust it if needed.", "ok");
            } catch (err) {
                setStatus(err.message || "Search failed. Please try again.", "bad");
            } finally {
                btn.disabled = false;
            }
        }

        function useMyLocation() {
            if (!navigator.geolocation) {
                setStatus("Your browser doesn't support location.", "bad");
                return;
            }
            setStatus("Getting your location…");
            navigator.geolocation.getCurrentPosition(
                p => pinMoved(p.coords.latitude, p.coords.longitude, 17),
                () => setStatus("Couldn't get your location. Search or click the map instead.", "bad"), {
                    enableHighAccuracy: true,
                    timeout: 10000
                }
            );
        }
    </script>
</body>

</html>