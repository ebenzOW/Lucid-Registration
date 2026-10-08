<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['user_email']);
    $password = trim($_POST['user_password']);

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=empty");
        exit;
    }

    // looks up the email in the login table
    $stmt = $conn->prepare("SELECT *
                            FROM LOGIN 
                            JOIN USER USING(User_ID)
                            WHERE User_Email = ? ");


    //Remove this later after successfully connecting the database to the login
    if (!$stmt) {
        die("Database error: " . $conn->error);
    }
    //Remove above later


    //binds the parameters and then fetchs the data
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    //checks if there is only 1 user in the DB with those credentials
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if ($user["isLock"]) {
            $conn->close();
            header("Location: login.php?error=locked");
            exit;
        } else {
            //will eventually switch to this commented code for hashing the passwords
            if(password_verify($password, $user["User_Password"])) {
            //above will be used later on

            if($password === $user["User_Password"]) {
                // Successful login and resets login attempts
                $_SESSION["User_ID"] = $user["User_ID"];
                $_SESSION["User_Type"] = $user["User_Type"];

                unset($user["User_Password"]); //Not storing password in session cookies so remove from array
                $_SESSION["User"] = $user; //can just do $_SESSION["User"]["User_Fname"] to access rest of info like User_Fname


                $resetStmt = $conn->prepare("UPDATE LOGIN
                                            SET No_Login_Attempts = 0
                                            WHERE User_ID = ?");
                $resetStmt->bind_param("i", $user["User_ID"]);
                $resetStmt->execute();

                $resetStmt->close();
                $conn->close();

                
                //temporary next location, will replace with different user dashboards
                if ($_SESSION["User_Type"] === "Student") {
                    header("Location: student_dashboard.php");
                    exit;
                }
                else if ($_SESSION["User_Type"] === "Faculty") {
                    header("Location: faculty_dashboard.php");
                    exit;
                }
                else if ($_SESSION["User_Type"] === "Admin") {
                    header("Location: admin_dashboard.php");
                    exit;
                }
                else if ($_SESSION["User_Type"] === "Stat-Dept") {
                    header("Location: stat_dashboard.php");
                    exit;
                }
            } else {

                //failed login attempt
                $attempts = $user["No_Login_Attempts"] + 1;

                if($attempts >= 10){
                    $lockStmt = $conn->prepare("UPDATE Login
                                                SET No_Login_Attempts = ?, isLock = 1
                                                WHERE User_ID = ?");
                    $lockStmt->bind_param("ii", $attempts, $user["User_ID"]);
                    $lockStmt->execute();
                    $lockStmt->close();

                    $conn->close();
                    header("Location: login.php?error=locked");
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
//incase connection isnt closed correctly
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}

?>