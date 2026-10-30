<?php
session_start();
require_once 'db.php';

/* =========================
   ADMIN ACCESS CHECK
========================= */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin-login.php");
    exit();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

/* =========================
   APPROVE / REJECT SUBMITTED SCHOOL
========================= */
if (isset($_POST['review_school'])) {

    $school_id = intval($_POST['school_id'] ?? 0);
    $decision  = $_POST['decision'] ?? '';
    $note      = trim($_POST['note'] ?? '');

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = "Session expired. Please try again.";
    } elseif (!in_array($decision, ['approve', 'reject'], true) || $school_id <= 0) {
        $error = "Invalid request.";
    } elseif ($decision === 'reject' && $note === '') {
        $error = "Please give a reason when rejecting, so the institution knows what to fix.";
    } else {
        $new_approval = $decision === 'approve' ? 'approved' : 'rejected';
        $new_status   = $decision === 'approve' ? 'active' : 'inactive';
        $admin_id     = intval($_SESSION['user_id']);

        $up = $conn->prepare("
            UPDATE schools
            SET approval_status = ?, status = ?, review_note = ?,
                reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ? AND approval_status = 'pending'
        ");
        $up->bind_param("sssii", $new_approval, $new_status, $note, $admin_id, $school_id);
        $up->execute();

        if ($up->affected_rows > 0) {
            $success = "School " . $new_approval . ".";

            // Notify the institution (assumes notifications has user_id + message columns)
            $q = $conn->prepare("SELECT submitted_by, school_name FROM schools WHERE id = ?");
            $q->bind_param("i", $school_id);
            $q->execute();
            $sch = $q->get_result()->fetch_assoc();
            $q->close();

            if ($sch && $sch['submitted_by']) {
                $text = $new_approval === 'approved'
                    ? "Good news! \"" . $sch['school_name'] . "\" has been verified and is now visible to parents."
                    : "\"" . $sch['school_name'] . "\" was not approved. Reason: " . $note;
                $n = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                if ($n) {
                    $n->bind_param("is", $sch['submitted_by'], $text);
                    $n->execute();
                    $n->close();
                }
            }
        } else {
            $error = "That school was already reviewed.";
        }
        $up->close();
    }
}


/* =========================
   ADD SCHOOL
========================= */
if (isset($_POST['add_school'])) {

    $school_name = trim($_POST['school_name']);
    $location = trim($_POST['location']);
    $latitude = !empty($_POST['latitude']) ? (float) $_POST['latitude'] : null;
    $longitude = !empty($_POST['longitude']) ? (float) $_POST['longitude'] : null;
    $place_id = trim($_POST['place_id'] ?? '');
    $description = trim($_POST['description']);
    $school_type = trim($_POST['school_type']);
    $school_fees = floatval($_POST['school_fees']);
    $facilities = trim($_POST['facilities']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $status = $_POST['status'];

    $image = '';

    /* Image upload */
    if (!empty($_FILES['image']['name'])) {

        $upload_dir = "uploads/schools/";

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $image_name = time() . "_" . basename($_FILES['image']['name']);
        $image_path = $upload_dir . $image_name;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
            $image = $image_path;
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO schools
        (
            school_name, location, latitude, longitude, place_id,
            description, school_type, school_fees, facilities,
            phone, email, image, status
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssddsssdsssss",
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
        $image,
        $status
    );

    if ($stmt->execute()) {
        $success = "School added successfully.";
    } else {
        $error = "Failed to add school: " . $stmt->error;
    }

    $stmt->close();
}


/* =========================
   DELETE SCHOOL
========================= */
if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM schools WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $success = "School deleted successfully.";
    } else {
        $error = "Unable to delete school.";
    }

    $stmt->close();
}


/* =========================
   UPDATE SCHOOL STATUS
========================= */
if (isset($_GET['toggle'])) {

    $id = intval($_GET['toggle']);

    $stmt = $conn->prepare("
        UPDATE schools
        SET status =
            CASE
                WHEN status = 'active' THEN 'inactive'
                ELSE 'active'
            END
        WHERE id = ?
        AND approval_status = 'approved'
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header("Location: admin-schools.php");
    exit();
}


/* =========================
   PENDING SUBMISSIONS (awaiting verification)
========================= */
$pending = $conn->query("
    SELECT s.*, u.full_name AS submitter, u.email AS submitter_email
    FROM schools s
    LEFT JOIN users u ON u.id = s.submitted_by
    WHERE s.approval_status = 'pending'
    ORDER BY s.created_at ASC
");


/* =========================
   GET ALL SCHOOLS
========================= */
$result = $conn->query("
    SELECT *
    FROM schools
    ORDER BY created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Manage Schools | SchoolConnect Admin</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f4f7fb;
            color: #1f2937;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #0f3d91, #06275f);
            color: white;
            padding: 25px 15px;
        }

        .logo {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 35px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            padding: 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            transition: 0.3s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: rgba(255, 255, 255, 0.15);
        }

        /* =========================
           MAIN
        ========================= */

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .header h1 {
            color: #123d82;
        }

        .header p {
            color: #6b7280;
            margin-top: 5px;
        }

        /* =========================
           ALERTS
        ========================= */

        .success,
        .error {
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* =========================
           FORM CARD
        ========================= */

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .card h2 {
            margin-bottom: 20px;
            color: #123d82;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            margin-bottom: 7px;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #2563eb;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            border: none;
            padding: 13px 20px;
            border-radius: 7px;
            cursor: pointer;
            font-weight: bold;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        /* =========================
           SCHOOL TABLE
        ========================= */

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        th {
            background: #eff6ff;
            color: #123d82;
        }

        tr:hover {
            background: #f9fafb;
        }

        .school-image {
            width: 65px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
        }

        .no-image {
            width: 65px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e5e7eb;
            border-radius: 6px;
            color: #6b7280;
            font-size: 12px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .action-btn {
            padding: 8px 10px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            font-size: 13px;
        }

        .toggle {
            background: #f59e0b;
        }

        .delete {
            background: #dc2626;
        }


        /* APPROVAL */
        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .pending-item {
            padding: 18px 0;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 18px;
            align-items: flex-start;
        }

        .pending-item:first-of-type {
            border-top: 0;
        }

        .pending-item .info {
            flex: 1;
            min-width: 0;
        }

        .pending-item h3 {
            color: #123d82;
            margin-bottom: 4px;
        }

        .pending-item .meta {
            color: #6b7280;
            font-size: 14px;
            margin: 2px 0;
        }

        .verify-links a {
            margin-right: 14px;
            font-size: 14px;
            font-weight: bold;
            color: #2563eb;
        }

        .review-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .review-form input {
            flex: 1;
            min-width: 220px;
        }

        .btn-approve {
            background: #16a34a;
            color: #fff;
        }

        .btn-reject {
            background: #dc2626;
            color: #fff;
        }

        .count-pill {
            background: #f59e0b;
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 14px;
            margin-left: 6px;
        }

        /* LEAFLET / OPENSTREETMAP MAP */
        .map-wrapper {
            width: 100%;
            margin-top: 10px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            overflow: hidden;
            background: #f8fafc;
        }

        #schoolMap {
            width: 100%;
            height: 400px;
            z-index: 1;
        }

        .location-search-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .location-search-row input {
            flex: 1;
            min-width: 0;
        }

        .map-help {
            display: block;
            margin-top: 8px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.5;
        }

        .location-status {
            margin-top: 8px;
            color: #166534;
            font-size: 13px;
        }

        .btn-search-location {
            background: #0f3d91;
            color: #fff;
            white-space: nowrap;
        }

        .btn-search-location:hover {
            background: #06275f;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 900px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .nav-links {
                display: flex;
                flex-wrap: wrap;
                justify-content: center;
            }

            .nav-links a {
                padding: 10px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>

</head>

<body>


    <!-- =========================
     SIDEBAR
========================= -->

    <div class="sidebar">

        <div class="logo">
            🎓 SchoolConnect Admin
        </div>

        <div class="nav-links">

            <a href="admin-dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                Dashboard
            </a>

            <a href="admin-schools.php" class="active">
                <i class="fa-solid fa-school"></i>
                Manage Schools
            </a>

            <a href="admin-bookings.php">
                <i class="fa-solid fa-calendar-check"></i>
                Bookings
            </a>

            <a href="admin-parents.php">
                <i class="fa-solid fa-users"></i>
                Parents
            </a>

            <a href="admin-reports.php">
                <i class="fa-solid fa-chart-pie"></i>
                Reports
            </a>

            <a href="logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                Logout
            </a>

        </div>

    </div>


    <!-- =========================
     MAIN CONTENT
========================= -->

    <div class="main">

        <div class="header">

            <div>
                <h1>Manage Schools</h1>
                <p>Add and manage schools available on SchoolConnect.</p>
            </div>

        </div>


        <?php if (isset($success)): ?>

            <div class="success">
                <i class="fa-solid fa-circle-check"></i>
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if (isset($error)): ?>

            <div class="error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <!-- =========================
         SCHOOLS AWAITING VERIFICATION
    ========================= -->

        <div class="card">

            <h2>
                <i class="fa-solid fa-user-check"></i>
                Awaiting Verification
                <span class="count-pill"><?= $pending ? (int) $pending->num_rows : 0 ?></span>
            </h2>

            <?php if ($pending && $pending->num_rows > 0): ?>
                <?php while ($p = $pending->fetch_assoc()):
                    $q = urlencode($p['school_name'] . ' ' . $p['location']); ?>

                    <div class="pending-item">

                        <?php if (!empty($p['image'])): ?>
                            <img src="<?= htmlspecialchars($p['image']) ?>" class="school-image" alt="School">
                        <?php else: ?>
                            <div class="no-image">No Image</div>
                        <?php endif; ?>

                        <div class="info">
                            <h3><?= htmlspecialchars($p['school_name']) ?>
                                <span class="badge pending">Pending</span>
                            </h3>
                            <div class="meta"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($p['location']) ?>
                                · <?= htmlspecialchars($p['school_type']) ?>
                                · GHS <?= number_format((float) $p['school_fees'], 2) ?></div>
                            <div class="meta"><i class="fa-solid fa-id-card"></i> Reg. no: <?= htmlspecialchars($p['registration_number'] ?: 'not provided') ?></div>
                            <div class="meta"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($p['email']) ?>
                                · <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($p['phone']) ?></div>
                            <?php if (!empty($p['website'])): ?>
                                <div class="meta"><i class="fa-solid fa-globe"></i>
                                    <a href="<?= htmlspecialchars($p['website']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($p['website']) ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($p['description'])): ?>
                                <div class="meta"><?= htmlspecialchars($p['description']) ?></div>
                            <?php endif; ?>
                            <div class="meta">Submitted by <?= htmlspecialchars($p['submitter'] ?: 'unknown') ?>
                                (<?= htmlspecialchars($p['submitter_email'] ?: '-') ?>)
                                on <?= date('d M Y', strtotime($p['created_at'])) ?></div>

                            <div class="verify-links" style="margin-top:8px">
                                <a href="https://www.google.com/search?q=<?= $q ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-magnifying-glass"></i> Search web</a>
                                <a href="https://www.google.com/maps/search/<?= $q ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map"></i> Find on Maps</a>
                                <?php if (!empty($p['latitude']) && !empty($p['longitude'])): ?>
                                    <a href="https://www.openstreetmap.org/?mlat=<?= urlencode($p['latitude']) ?>&mlon=<?= urlencode($p['longitude']) ?>#map=18/<?= urlencode($p['latitude']) ?>/<?= urlencode($p['longitude']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map-pin"></i> View submitted pin</a>
                                <?php endif; ?>
                            </div>

                            <form method="POST" class="review-form">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <input type="hidden" name="school_id" value="<?= (int) $p['id'] ?>">
                                <input type="text" name="note" maxlength="500" placeholder="Note (required if rejecting)">
                                <button type="submit" name="review_school" value="1" class="btn btn-approve"
                                    onclick="this.form.decision.value='approve'">
                                    <i class="fa-solid fa-check"></i> Approve
                                </button>
                                <button type="submit" name="review_school" value="1" class="btn btn-reject"
                                    onclick="this.form.decision.value='reject'">
                                    <i class="fa-solid fa-xmark"></i> Reject
                                </button>
                                <input type="hidden" name="decision" value="">
                            </form>
                        </div>

                    </div>

                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:#6b7280;">No schools are waiting for verification.</p>
            <?php endif; ?>

        </div>


        <!-- =========================
         ADD SCHOOL
    ========================= -->

        <div class="card">

            <h2>
                <i class="fa-solid fa-plus"></i>
                Add New School
            </h2>

            <form method="POST"
                enctype="multipart/form-data">

                <div class="form-grid">

                    <div class="form-group">

                        <label>School Name</label>

                        <input
                            type="text"
                            name="school_name"
                            placeholder="Enter school name"
                            required>

                    </div>


                    <div class="form-group full">

                        <label for="school_location">
                            <i class="fa-solid fa-location-dot"></i>
                            School Location
                        </label>

                        <div class="location-search-row">
                            <input
                                type="text"
                                id="school_location"
                                name="location"
                                placeholder="Enter an address or area, e.g. East Legon, Accra"
                                autocomplete="off"
                                required>
                            <button type="button" id="searchLocation" class="btn btn-search-location">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Search Location
                            </button>
                        </div>

                        <input type="hidden" id="latitude" name="latitude">
                        <input type="hidden" id="longitude" name="longitude">
                        <input type="hidden" id="place_id" name="place_id" value="">

                        <small class="map-help">
                            Search using the button, or click directly on the map to place the marker.
                            You can drag the marker to fine-tune the school's position. Select a location
                            carefully before saving.
                        </small>

                        <div class="map-wrapper">
                            <div id="schoolMap"></div>
                        </div>

                        <div id="locationStatus" class="location-status" aria-live="polite"></div>

                    </div>


                    <div class="form-group">

                        <label>School Type</label>

                        <select name="school_type" required>

                            <option value="">Select type</option>

                            <option value="Private">
                                Private
                            </option>

                            <option value="Public">
                                Public
                            </option>

                            <option value="International">
                                International
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label>School Fees (GHS)</label>

                        <input
                            type="number"
                            name="school_fees"
                            min="0"
                            step="0.01"
                            placeholder="e.g. 3500.00"
                            required>

                    </div>


                    <div class="form-group full">

                        <label>Facilities</label>

                        <textarea
                            name="facilities"
                            placeholder="e.g. Library, Computer Lab, Playground, Science Lab, Canteen, School Bus"></textarea>

                        <small>
                            Separate facilities using commas.
                        </small>

                    </div>


                    <div class="form-group full">

                        <label>Description</label>

                        <textarea
                            name="description"
                            placeholder="Enter school description"></textarea>

                    </div>


                    <div class="form-group">

                        <label>Phone</label>

                        <input
                            type="text"
                            name="phone"
                            placeholder="School phone number">

                    </div>


                    <div class="form-group">

                        <label>Email</label>

                        <input
                            type="email"
                            name="email"
                            placeholder="School email">

                    </div>


                    <div class="form-group">

                        <label>School Image</label>

                        <input
                            type="file"
                            name="image"
                            accept="image/*">

                    </div>


                    <div class="form-group">

                        <label>Status</label>

                        <select name="status">

                            <option value="active">
                                Active
                            </option>

                            <option value="inactive">
                                Inactive
                            </option>

                        </select>

                    </div>


                    <div class="form-group full">

                        <button
                            type="submit"
                            name="add_school"
                            class="btn btn-primary">

                            <i class="fa-solid fa-plus"></i>
                            Add School

                        </button>

                    </div>

                </div>

            </form>

        </div>


        <!-- =========================
         SCHOOL LIST
    ========================= -->

        <div class="card">

            <h2>
                <i class="fa-solid fa-school"></i>
                Registered Schools
            </h2>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>

                            <th>Image</th>
                            <th>School</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Fees</th>
                            <th>Facilities</th>
                            <th>Status</th>
                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if ($result && $result->num_rows > 0): ?>

                            <?php while ($school = $result->fetch_assoc()): ?>

                                <tr>

                                    <td>

                                        <?php if (!empty($school['image'])): ?>

                                            <img
                                                src="<?= htmlspecialchars($school['image']) ?>"
                                                class="school-image"
                                                alt="School">

                                        <?php else: ?>

                                            <div class="no-image">
                                                No Image
                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($school['school_name']) ?>
                                        </strong>

                                    </td>


                                    <td>
                                        <?= htmlspecialchars($school['location']) ?>
                                    </td>


                                    <td>
                                        <?= htmlspecialchars($school['school_type']) ?>
                                    </td>


                                    <td>

                                        GHS
                                        <?= number_format(
                                            (float)$school['school_fees'],
                                            2
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $school['facilities'] ?? 'Not specified'
                                        ) ?>

                                    </td>


                                    <td>

                                        <span class="badge <?= $school['status'] ?>">

                                            <?= ucfirst(
                                                htmlspecialchars($school['status'])
                                            ) ?>

                                        </span>

                                        <?php if (($school['approval_status'] ?? 'approved') !== 'approved'): ?>
                                            <span class="badge <?= htmlspecialchars($school['approval_status']) ?>">
                                                <?= ucfirst(htmlspecialchars($school['approval_status'])) ?>
                                            </span>
                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <div class="actions">

                                            <a
                                                href="admin-schools.php?toggle=<?= $school['id'] ?>"
                                                class="action-btn toggle">

                                                <i class="fa-solid fa-power-off"></i>

                                                Toggle

                                            </a>


                                            <a
                                                href="admin-schools.php?delete=<?= $school['id'] ?>"
                                                class="action-btn delete"
                                                onclick="return confirm('Are you sure you want to delete this school?');">

                                                <i class="fa-solid fa-trash"></i>

                                                Delete

                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8"
                                    style="text-align:center; padding:30px;">

                                    No schools have been added yet.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>


    <!-- Leaflet library (free, open-source map interface) -->
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="">

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin="">
    </script>

    <script>
        let schoolMap;
        let schoolMarker;

        const locationInput = document.getElementById("school_location");
        const latitudeInput = document.getElementById("latitude");
        const longitudeInput = document.getElementById("longitude");
        const placeIdInput = document.getElementById("place_id");
        const locationStatus = document.getElementById("locationStatus");

        function setSchoolPosition(lat, lng, zoom = 16) {
            const position = [lat, lng];

            schoolMarker.setLatLng(position);
            schoolMap.setView(position, zoom);

            latitudeInput.value = Number(lat).toFixed(8);
            longitudeInput.value = Number(lng).toFixed(8);
            // OpenStreetMap/Nominatim does not use Google's place_id.
            placeIdInput.value = "";

            locationStatus.textContent = "✓ Map marker position selected.";
        }

        document.addEventListener("DOMContentLoaded", function() {
            const defaultLocation = [5.6037, -0.1870]; // Accra, Ghana

            schoolMap = L.map("schoolMap").setView(defaultLocation, 12);

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
            }).addTo(schoolMap);

            schoolMarker = L.marker(defaultLocation, {
                draggable: true,
                autoPan: true
            }).addTo(schoolMap);

            schoolMarker.bindPopup("Drag this marker to the school's exact location.");

            schoolMap.on("click", function(event) {
                setSchoolPosition(event.latlng.lat, event.latlng.lng);
                schoolMarker.openPopup();
            });

            schoolMarker.on("dragend", function(event) {
                const position = event.target.getLatLng();
                latitudeInput.value = position.lat.toFixed(8);
                longitudeInput.value = position.lng.toFixed(8);
                locationStatus.textContent = "✓ Marker position updated.";
            });

            document.getElementById("searchLocation").addEventListener("click", searchLocation);

            locationInput.addEventListener("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    searchLocation();
                }
            });

            // Ensure Leaflet calculates the correct size once the form is rendered.
            setTimeout(function() {
                schoolMap.invalidateSize();
            }, 200);
        });

        async function searchLocation() {
            const query = locationInput.value.trim();

            if (!query) {
                locationStatus.textContent = "Enter a location to search.";
                return;
            }

            const searchButton = document.getElementById("searchLocation");
            searchButton.disabled = true;
            locationStatus.textContent = "Searching for location...";

            try {
                // Nominatim is called only after a user submits a search, not on each keystroke.
                const url = "https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=" +
                    encodeURIComponent(query);

                const response = await fetch(url, {
                    headers: {
                        "Accept": "application/json"
                    }
                });

                if (!response.ok) {
                    throw new Error("The location search service is temporarily unavailable.");
                }

                const results = await response.json();

                if (!results.length) {
                    locationStatus.textContent =
                        "No matching location found. Try a nearby landmark or area.";
                    return;
                }

                const result = results[0];
                const lat = Number(result.lat);
                const lng = Number(result.lon);

                setSchoolPosition(lat, lng, 16);
                locationInput.value = result.display_name || query;
                schoolMarker.bindPopup(
                    "<strong>Selected location</strong><br>" +
                    String(result.display_name || query).replace(/[&<>"]/g, function(char) {
                        return {
                            "&": "&amp;",
                            "<": "&lt;",
                            ">": "&gt;",
                            '"': "&quot;"
                        } [char];
                    })
                ).openPopup();

                locationStatus.textContent =
                    "✓ Location found. Check the marker and adjust it if needed.";
            } catch (error) {
                locationStatus.textContent =
                    error.message || "Could not search for this location. Please try again.";
            } finally {
                searchButton.disabled = false;
            }
        }
    </script>

</body>

</html>