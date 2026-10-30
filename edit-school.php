<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"] ?? "", ["institution", "admin"], true)) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "";
$school_id = (int) ($_GET["id"] ?? $_POST["school_id"] ?? 0);

if ($school_id <= 0) {
    header("Location: " . ($role === "institution" ? "institution-dashboard.php" : "admin-schools.php"));
    exit;
}

$is_institution = $role === "institution";
$message = "";
$message_type = "";

// Institution can edit only schools it submitted. Admin can edit any school.
if ($is_institution) {
    $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ? AND submitted_by = ? LIMIT 1");
    $stmt->bind_param("ii", $school_id, $user_id);
} else {
    $stmt = $conn->prepare("SELECT * FROM schools WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $school_id);
}

$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$stmt->close();

if (!$school) {
    header("Location: " . ($is_institution ? "institution-dashboard.php" : "admin-schools.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_school"])) {
    $school_name = trim($_POST["school_name"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $latitude_raw = trim($_POST["latitude"] ?? "");
    $longitude_raw = trim($_POST["longitude"] ?? "");
    $place_id = trim($_POST["place_id"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $school_type = trim($_POST["school_type"] ?? "");
    $school_fees = (float) ($_POST["school_fees"] ?? 0);
    $facilities = trim($_POST["facilities"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $status = $is_institution ? ($school["status"] ?? "inactive") : trim($_POST["status"] ?? "active");

    $latitude = $latitude_raw !== "" ? (float) $latitude_raw : null;
    $longitude = $longitude_raw !== "" ? (float) $longitude_raw : null;

    if ($school_name === "" || $location === "" || $school_type === "") {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif ($school_fees < 0) {
        $message = "School fees cannot be negative.";
        $message_type = "error";
    }

    $image_name = $school["image"] ?? "";

    if ($message === "" && isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $message = "There was a problem uploading the image.";
            $message_type = "error";
        } else {
            $allowed = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];
            $mime = mime_content_type($_FILES["image"]["tmp_name"]);

            if (!isset($allowed[$mime])) {
                $message = "Only JPG, PNG and WEBP images are allowed.";
                $message_type = "error";
            } else {
                $upload_dir = __DIR__ . "/uploads/schools/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_name = "school_" . bin2hex(random_bytes(8)) . "." . $allowed[$mime];
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $destination)) {
                    // Delete old image only when it is stored in our uploads folder.
                    if (!empty($school["image"])) {
                        $old_relative = $school["image"];
                        if (strpos($old_relative, "uploads/") === 0) {
                            $old_path = __DIR__ . "/" . $old_relative;
                        } else {
                            $old_path = __DIR__ . "/uploads/schools/" . basename($old_relative);
                        }
                        if (is_file($old_path) && realpath($old_path) !== realpath($destination)) {
                            @unlink($old_path);
                        }
                    }
                    $image_name = "uploads/schools/" . $new_name;
                } else {
                    $message = "Unable to save the new image.";
                    $message_type = "error";
                }
            }
        }
    }

    if ($message === "") {
        if ($is_institution) {
            // Any institution edit must be reviewed again before the changed details are shown as verified.
            $new_approval = "pending";
            $new_status = "inactive";

            $sql = "UPDATE schools SET
                        school_name = ?, location = ?, latitude = ?, longitude = ?, place_id = ?,
                        description = ?, school_type = ?, school_fees = ?, facilities = ?,
                        phone = ?, email = ?, image = ?, status = ?, approval_status = ?,
                        review_note = NULL, reviewed_by = NULL, reviewed_at = NULL
                    WHERE id = ? AND submitted_by = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "ssddsssdssssssii",
                    $school_name,
                    $location,
                    $latitude,
                    $longitude,
                    $place_id,
                    $description,
                    $school_type,
                    $school_fees,
                    $facilities,
                    $phone,
                    $email,
                    $image_name,
                    $new_status,
                    $new_approval,
                    $school_id,
                    $user_id
                );
            }
        } else {
            $sql = "UPDATE schools SET
                        school_name = ?, location = ?, latitude = ?, longitude = ?, place_id = ?,
                        description = ?, school_type = ?, school_fees = ?, facilities = ?,
                        phone = ?, email = ?, image = ?, status = ?
                    WHERE id = ?";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param(
                    "ssddsssdsssss i",
                    $school_name,
                    $location,
                    $latitude,
                    $longitude,
                    $place_id,
                    $description,
                    $school_type,
                    $school_fees,
                    $facilities,
                    $phone,
                    $email,
                    $image_name,
                    $status,
                    $school_id
                );
            }
        }

        // Fix admin bind type string spacing if present.
        if (!$is_institution && isset($stmt) && $stmt instanceof mysqli_stmt) {
            $stmt->close();
            $stmt = $conn->prepare("UPDATE schools SET
                school_name = ?, location = ?, latitude = ?, longitude = ?, place_id = ?,
                description = ?, school_type = ?, school_fees = ?, facilities = ?,
                phone = ?, email = ?, image = ?, status = ?
                WHERE id = ?");
            $stmt->bind_param(
                "ssddsssdsssssi",
                $school_name,
                $location,
                $latitude,
                $longitude,
                $place_id,
                $description,
                $school_type,
                $school_fees,
                $facilities,
                $phone,
                $email,
                $image_name,
                $status,
                $school_id
            );
        }

        if (!$stmt) {
            $message = "Database error: " . $conn->error;
            $message_type = "error";
        } elseif ($stmt->execute()) {
            $message = $is_institution
                ? "School updated successfully. The changes have been sent to an administrator for verification."
                : "School updated successfully.";
            $message_type = "success";

            $school["school_name"] = $school_name;
            $school["location"] = $location;
            $school["latitude"] = $latitude;
            $school["longitude"] = $longitude;
            $school["place_id"] = $place_id;
            $school["description"] = $description;
            $school["school_type"] = $school_type;
            $school["school_fees"] = $school_fees;
            $school["facilities"] = $facilities;
            $school["phone"] = $phone;
            $school["email"] = $email;
            $school["image"] = $image_name;
            $school["status"] = $is_institution ? $new_status : $status;
            if ($is_institution) {
                $school["approval_status"] = "pending";
            }
        } else {
            $message = "Error updating school: " . $stmt->error;
            $message_type = "error";
        }

        if ($stmt) $stmt->close();
    }
}

function e($value)
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

$image = $school["image"] ?? "";
if ($image !== "" && strpos($image, "uploads/") !== 0) {
    $image_url = "uploads/schools/" . basename($image);
} else {
    $image_url = $image;
}

$back_url = $is_institution ? "institution-dashboard.php" : "admin-schools.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit School | SchoolConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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
            --body: "DM Sans", "Segoe UI", Arial, sans-serif
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: var(--body);
            background: var(--chalk);
            color: var(--ink);
            line-height: 1.5
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(11, 27, 58, .96);
            backdrop-filter: blur(10px);
            min-height: 68px;
            padding: 0 6%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px
        }

        .logo {
            font-family: var(--display);
            font-size: 20px;
            font-weight: 800;
            color: #fff
        }

        .logo span {
            color: var(--sun)
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap
        }

        .nav-links a {
            color: #cbd7ee;
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 500;
            transition: .2s
        }

        .nav-links a:hover,
        .nav-links .active {
            color: #fff;
            background: rgba(255, 255, 255, .1)
        }

        .nav-links .logout {
            background: var(--red);
            color: #fff
        }

        .container {
            width: 92%;
            max-width: 1100px;
            margin: 32px auto 60px
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 22px
        }

        .top h1 {
            font-family: var(--display);
            font-size: 32px
        }

        .top p {
            color: var(--muted);
            margin-top: 5px
        }

        .back {
            background: #fff;
            color: var(--blue);
            text-decoration: none;
            padding: 11px 16px;
            border-radius: 10px;
            font-weight: 700;
            border: 1px solid var(--line)
        }

        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca
        }

        .notice {
            background: #fff7df;
            border: 1px solid #fde68a;
            color: #92400e
        }

        .card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 12px 30px -20px rgba(11, 27, 58, .25);
            margin-bottom: 22px
        }

        .card h2 {
            font-family: var(--display);
            font-size: 20px;
            margin-bottom: 6px
        }

        .card .lead {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 22px
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 19px
        }

        .group {
            display: flex;
            flex-direction: column;
            gap: 7px
        }

        .full {
            grid-column: 1/-1
        }

        label {
            font-size: 13px;
            font-weight: 700
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 1px solid #d5dce5;
            border-radius: 10px;
            padding: 12px 13px;
            font: inherit;
            font-size: 14px;
            outline: none;
            background: #fff
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(7, 86, 184, .08)
        }

        textarea {
            min-height: 125px;
            resize: vertical
        }

        .hint {
            font-size: 12px;
            color: var(--muted)
        }

        .map-wrap {
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden
        }

        .map {
            height: 360px
        }

        .map-tools {
            display: flex;
            gap: 10px;
            margin-bottom: 10px
        }

        .map-tools input {
            flex: 1
        }

        .map-btn {
            border: 0;
            background: var(--ink);
            color: #fff;
            padding: 11px 15px;
            border-radius: 9px;
            font-weight: 700;
            cursor: pointer
        }

        .coords {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px
        }

        .image-box {
            display: flex;
            gap: 18px;
            align-items: center;
            flex-wrap: wrap
        }

        .image-box img,
        .placeholder {
            width: 190px;
            height: 125px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid var(--line)
        }

        .placeholder {
            display: grid;
            place-items: center;
            background: var(--sky);
            font-size: 44px
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap
        }

        .primary {
            border: 0;
            background: var(--blue);
            color: #fff;
            padding: 13px 21px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer
        }

        .primary:hover {
            background: #06458f
        }

        .secondary {
            background: #e2e8f0;
            color: #334155;
            text-decoration: none;
            padding: 13px 21px;
            border-radius: 10px;
            font-weight: 700
        }

        .status {
            display: inline-flex;
            padding: 5px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize
        }

        .pending {
            background: #fef3c7;
            color: #92400e
        }

        .approved {
            background: #dcfce7;
            color: #166534
        }

        .rejected {
            background: #fee2e2;
            color: #b91c1c
        }

        .footer {
            background: var(--ink);
            padding: 25px;
            text-align: center;
            color: #8fa1c4;
            font-size: 13px
        }

        @media(max-width:750px) {
            .navbar {
                padding: 14px 5%;
                flex-direction: column
            }

            .nav-links {
                justify-content: center
            }

            .top {
                flex-direction: column;
                align-items: flex-start
            }

            .form-grid {
                grid-template-columns: 1fr
            }

            .full {
                grid-column: auto
            }

            .map {
                height: 300px
            }

            .coords {
                grid-template-columns: 1fr
            }

            .card {
                padding: 20px
            }

            .container {
                width: 94%
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <div class="logo">🎓 School<span>Connect</span></div>
        <div class="nav-links">
            <a href="<?= e($back_url) ?>" class="active">← Back to Dashboard</a>
            <?php if ($is_institution): ?><a href="submit-school.php">➕ List a school</a><?php else: ?><a href="admin-schools.php">🏫 Manage Schools</a><?php endif; ?>
            <a href="logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <main class="container">
        <div class="top">
            <div>
                <h1>✏️ Edit School</h1>
                <p>Update the information for <strong><?= e($school["school_name"]) ?></strong>.</p>
            </div>
            <a class="back" href="<?= e($back_url) ?>">← Back</a>
        </div>

        <?php if ($message !== ""): ?><div class="alert <?= e($message_type) ?>">✓ <?= e($message) ?></div><?php endif; ?>

        <?php if ($is_institution): ?>
            <div class="alert notice">ℹ️ <strong>Verification:</strong> When you change your school information, the school will be sent back to <strong>Pending</strong> status for administrator verification. It will not be visible to parents until it is approved again.</div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="school_id" value="<?= $school_id ?>">
            <input type="hidden" name="update_school" value="1">

            <section class="card">
                <h2>🏫 Basic School Information</h2>
                <p class="lead">Update the information parents will use when comparing schools.</p>
                <div class="form-grid">
                    <div class="group"><label>School Name *</label><input type="text" name="school_name" value="<?= e($school["school_name"]) ?>" required></div>
                    <div class="group"><label>School Type *</label><select name="school_type" required>
                            <option value="">Select type</option>
                            <?php foreach (["Public", "Private", "International", "Faith-Based"] as $type): ?><option value="<?= e($type) ?>" <?= ($school["school_type"] ?? "") === $type ? "selected" : "" ?>><?= e($type) ?></option><?php endforeach; ?>
                        </select></div>
                    <div class="group"><label>School Fees (GHS) *</label><input type="number" min="0" step="0.01" name="school_fees" value="<?= e($school["school_fees"] ?? 0) ?>" required><span class="hint">Enter the applicable school fee amount.</span></div>
                    <div class="group"><label>Phone Number</label><input type="text" name="phone" value="<?= e($school["phone"] ?? "") ?>"></div>
                    <div class="group"><label>Email Address</label><input type="email" name="email" value="<?= e($school["email"] ?? "") ?>"></div>
                    <?php if (!$is_institution): ?><div class="group"><label>School Status</label><select name="status">
                                <option value="active" <?= ($school["status"] ?? "") === "active" ? "selected" : "" ?>>Active</option>
                                <option value="inactive" <?= ($school["status"] ?? "") === "inactive" ? "selected" : "" ?>>Inactive</option>
                            </select></div><?php endif; ?>
                    <div class="group full"><label>Facilities</label><textarea name="facilities" placeholder="e.g. ICT laboratory, library, science laboratory, playground, school bus..."><?= e($school["facilities"] ?? "") ?></textarea><span class="hint">Separate facilities with commas for easier reading.</span></div>
                    <div class="group full"><label>Description</label><textarea name="description" placeholder="Describe the school, its programmes and what makes it different."><?= e($school["description"] ?? "") ?></textarea></div>
                </div>
            </section>

            <section class="card">
                <h2>📍 School Location</h2>
                <p class="lead">Search for the school and then click or drag the marker to the exact location.</p>
                <div class="map-tools"><input id="locationSearch" type="text" value="<?= e($school["location"] ?? "") ?>" placeholder="Search school location..." autocomplete="off"><button type="button" class="map-btn" id="searchLocation">🔎 Search</button></div>
                <div class="map-wrap">
                    <div id="map" class="map"></div>
                </div>
                <div class="coords">
                    <div class="group"><label>Latitude</label><input id="latitude" name="latitude" type="text" value="<?= e($school["latitude"] ?? "") ?>" readonly></div>
                    <div class="group"><label>Longitude</label><input id="longitude" name="longitude" type="text" value="<?= e($school["longitude"] ?? "") ?>" readonly></div>
                </div>
                <input type="hidden" id="location" name="location" value="<?= e($school["location"] ?? "") ?>">
                <input type="hidden" id="place_id" name="place_id" value="<?= e($school["place_id"] ?? "") ?>">
                <p class="hint" style="margin-top:10px">Current location: <strong id="locationText"><?= e($school["location"] ?? "Not selected") ?></strong></p>
            </section>

            <section class="card">
                <h2>🖼️ School Image</h2>
                <p class="lead">Replace the current school image if necessary.</p>
                <div class="image-box">
                    <?php if ($image_url !== ""): ?><img src="<?= e($image_url) ?>" alt="School image"><?php else: ?><div class="placeholder">🏫</div><?php endif; ?>
                    <div class="group"><label>Change Image</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"><span class="hint">JPG, PNG or WEBP.</span></div>
                </div>
                <div class="buttons">
                    <button type="submit" class="primary">💾 Save School Changes</button>
                    <a href="<?= e($back_url) ?>" class="secondary">Cancel</a>
                </div>
            </section>
        </form>
    </main>
    <footer class="footer">© 2026 SchoolConnect. Basic School Booking System.</footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const savedLat = parseFloat(<?= json_encode($school["latitude"] ?? null) ?>);
        const savedLng = parseFloat(<?= json_encode($school["longitude"] ?? null) ?>);
        const defaultLat = Number.isFinite(savedLat) ? savedLat : 5.6037;
        const defaultLng = Number.isFinite(savedLng) ? savedLng : -0.1870;
        const map = L.map('map').setView([defaultLat, defaultLng], Number.isFinite(savedLat) ? 16 : 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        let marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);
        const latInput = document.getElementById('latitude'),
            lngInput = document.getElementById('longitude'),
            locationInput = document.getElementById('location'),
            placeInput = document.getElementById('place_id'),
            locationText = document.getElementById('locationText');

        function setMarker(lat, lng, label, placeId = '') {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 16);
            latInput.value = Number(lat).toFixed(7);
            lngInput.value = Number(lng).toFixed(7);
            if (label) {
                locationInput.value = label;
                locationText.textContent = label;
            }
            if (placeId) placeInput.value = placeId;
        }
        marker.on('dragend', async function() {
            const p = marker.getLatLng();
            setMarker(p.lat, p.lng);
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${p.lat}&lon=${p.lng}`);
                const d = await r.json();
                if (d.display_name) {
                    setMarker(p.lat, p.lng, d.display_name, d.place_id || '');
                }
            } catch (e) {}
        });
        map.on('click', async function(e) {
            setMarker(e.latlng.lat, e.latlng.lng);
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${e.latlng.lat}&lon=${e.latlng.lng}`);
                const d = await r.json();
                if (d.display_name) {
                    setMarker(e.latlng.lat, e.latlng.lng, d.display_name, d.place_id || '');
                }
            } catch (e) {}
        });
        async function searchLocation() {
            const q = document.getElementById('locationSearch').value.trim();
            if (!q) return;
            const btn = document.getElementById('searchLocation');
            btn.disabled = true;
            btn.textContent = 'Searching...';
            try {
                const r = await fetch(`https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=${encodeURIComponent(q)}`);
                const data = await r.json();
                if (data.length) {
                    const d = data[0];
                    setMarker(parseFloat(d.lat), parseFloat(d.lon), d.display_name, d.place_id || '');
                } else alert('Location not found. Try a more specific search.');
            } catch (e) {
                alert('Could not search for the location. Check your internet connection.');
            } finally {
                btn.disabled = false;
                btn.textContent = '🔎 Search';
            }
        }
        document.getElementById('searchLocation').addEventListener('click', searchLocation);
        document.getElementById('locationSearch').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchLocation();
            }
        });
        if (Number.isFinite(savedLat) && Number.isFinite(savedLng)) {
            latInput.value = savedLat.toFixed(7);
            lngInput.value = savedLng.toFixed(7);
        } else {
            latInput.value = '';
            lngInput.value = '';
        }
    </script>
</body>

</html>