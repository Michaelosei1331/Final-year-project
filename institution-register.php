<?php
session_start();
require_once 'db.php';

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $full_name = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    // Check required fields
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
        $message = "Password must be at least 6 characters.";
        $message_type = "error";
    }

    // Check passwords
    elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } else {

        // Check if email already exists
        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ? LIMIT 1"
        );

        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {

            $message = "An account with this email already exists.";
            $message_type = "error";
        } else {

            // Hash password
            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Create institution account
            $stmt = $conn->prepare(
                "INSERT INTO users
                (full_name, email, phone, password, role)
                VALUES (?, ?, ?, ?, 'institution')"
            );

            $stmt->bind_param(
                "ssss",
                $full_name,
                $email,
                $phone,
                $hashed_password
            );

            if ($stmt->execute()) {

                $message = "Institution account created successfully!";
                $message_type = "success";

                // Clear form
                $full_name = "";
                $email = "";
                $phone = "";
            } else {

                $message = "Something went wrong. Please try again.";
                $message_type = "error";
            }

            $stmt->close();
        }

        $check->close();
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

    <title>Institution Registration - SchoolConnect</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg,
                    #0f172a,
                    #1d4ed8);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .container {
            width: 100%;
            max-width: 500px;
        }

        .card {
            background: white;
            border-radius: 18px;
            padding: 35px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
        }

        .logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .logo-icon {
            width: 65px;
            height: 65px;
            background: #2563eb;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
        }

        h1 {
            text-align: center;
            color: #0f172a;
            font-size: 27px;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 28px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .input-box {
            position: relative;
        }

        .input-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        input {
            width: 100%;
            padding: 13px 14px 13px 43px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            outline: none;
            transition: 0.2s;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 5px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 13px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .message.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .login-link {
            text-align: center;
            margin-top: 22px;
            color: #64748b;
            font-size: 14px;
        }

        .login-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-link {
            text-align: center;
            margin-top: 18px;
        }

        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
            color: #2563eb;
        }

        @media (max-width: 500px) {

            .card {
                padding: 25px 20px;
            }

            h1 {
                font-size: 23px;
            }
        }
    </style>

</head>

<body>

    <div class="container">

        <div class="card">

            <div class="logo">

                <div class="logo-icon">
                    <i class="fas fa-school"></i>
                </div>

            </div>

            <h1>Register Your Institution</h1>

            <p class="subtitle">
                Create an institution account to submit and manage
                your school's listing on SchoolConnect.
            </p>

            <?php if (!empty($message)): ?>

                <div class="message <?php echo $message_type; ?>">

                    <?php echo htmlspecialchars($message); ?>

                </div>

            <?php endif; ?>


            <form method="POST">

                <div class="form-group">

                    <label for="full_name">
                        Institution Representative Name
                    </label>

                    <div class="input-box">

                        <i class="fas fa-user"></i>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            placeholder="Enter representative name"
                            value="<?php echo htmlspecialchars($full_name ?? ''); ?>"
                            required>

                    </div>

                </div>


                <div class="form-group">

                    <label for="email">
                        Institution Email
                    </label>

                    <div class="input-box">

                        <i class="fas fa-envelope"></i>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="institution@example.com"
                            value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            required>

                    </div>

                </div>


                <div class="form-group">

                    <label for="phone">
                        Phone Number
                    </label>

                    <div class="input-box">

                        <i class="fas fa-phone"></i>

                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="Enter phone number"
                            value="<?php echo htmlspecialchars($phone ?? ''); ?>"
                            required>

                    </div>

                </div>


                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <div class="input-box">

                        <i class="fas fa-lock"></i>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Create a password"
                            required>

                    </div>

                </div>


                <div class="form-group">

                    <label for="confirm_password">
                        Confirm Password
                    </label>

                    <div class="input-box">

                        <i class="fas fa-lock"></i>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Confirm your password"
                            required>

                    </div>

                </div>


                <button type="submit" class="btn">

                    <i class="fas fa-user-plus"></i>
                    Create Institution Account

                </button>

            </form>


            <div class="login-link">

                Already have an institution account?

                <a href="institution-login.php">
                    Login here
                </a>

            </div>


            <div class="back-link">

                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to SchoolConnect
                </a>

            </div>

        </div>

    </div>

</body>

</html>