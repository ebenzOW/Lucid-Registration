<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['user_email']);
    $password = trim($_POST['user_password']);

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=empty");
        exit;
    }

    $stmt = $conn->prepare("SELECT *
                            FROM LOGIN 
                            JOIN `USER` USING(User_ID)
                            WHERE User_Email = ?");

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (!empty($user["IsLock"])) {
            $conn->close();
            header("Location: login.php?error=locked");
            exit;
        } else {
            if (password_verify($password, $user["User_Password"])) {
                session_regenerate_id(true);

                $_SESSION["User_ID"] = $user["User_ID"];
                $_SESSION["User_Type"] = $user["User_Type"];

                unset($user["User_Password"]);
                $_SESSION["User"] = $user;

                if ($user["User_Type"] === "Admin") {
                    $secStmt = $conn->prepare("SELECT Security_Level FROM ADMIN WHERE Admin_ID = ?");
                    $secStmt->bind_param("i", $user["User_ID"]);
                    $secStmt->execute();
                    $secRes = $secStmt->get_result()->fetch_assoc();
                    $secStmt->close();
                    $_SESSION["Security_Level"] = (int)($secRes["Security_Level"] ?? 1);
                }

                $resetStmt = $conn->prepare("UPDATE LOGIN
                                            SET No_Login_Attempts = 0
                                            WHERE User_ID = ?");
                $resetStmt->bind_param("i", $user["User_ID"]);
                $resetStmt->execute();
                $resetStmt->close();
                $conn->close();

                if ($_SESSION["User_Type"] === "Student") {
                    header("Location: student/dashboard.php");
                    exit;
                } else if ($_SESSION["User_Type"] === "Faculty") {
                    header("Location: faculty/dashboard.php");
                    exit;
                } else if ($_SESSION["User_Type"] === "Admin") {
                    header("Location: admin/dashboard.php");
                    exit;
                } else if ($_SESSION["User_Type"] === "Stat-Dept") {
                    header("Location: stat_dept/dashboard.php");
                    exit;
                }
            } else {
                $attempts = (int)$user["No_Login_Attempts"] + 1;

                if ($attempts >= 3) {
                    $lockStmt = $conn->prepare("UPDATE LOGIN
                                                SET No_Login_Attempts = ?, IsLock = 1
                                                WHERE User_ID = ?");
                    $lockStmt->bind_param("ii", $attempts, $user["User_ID"]);
                    $lockStmt->execute();
                    $lockStmt->close();

                    require_once 'email_functions.php';
                    sendPasswordResetCode($conn, $email);

                    $conn->close();
                    header("Location: reset_password.php?error=locked");
                    exit;
                } else {
                    $updateStmt = $conn->prepare("UPDATE LOGIN 
                                                SET No_Login_Attempts = ? 
                                                WHERE User_ID = ?");
                    $updateStmt->bind_param("ii", $attempts, $user["User_ID"]);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $conn->close();
                    header("Location: login.php?error=invalid");
                    exit;
                }
            }
        }
    } else {
        $conn->close();
        header("Location: login.php?error=invalid");
        exit;
    }
}
