<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST"){
    $email = trim($_POST['user_email']);
    $password = trim($_POST['user_password']);

    if (empty($email) || empty($password)) {
        header("Location: login.php?error=empty");
        exit;
    }

    // looks up the email in the login table
    $stmt = $conn->prepare("SELECT User_ID, User_Password, No_Login_Attempts, isLock
                FROM Login
                WHERE User_Email = ?");


    //Remove this later after successfully connecting the database to the login
    if(!$stmt) {
        die("Database error: " . $conn->error);
    }
    //Remove above later


    //binds the parameters and then fetchs the data
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    //checks if there is only 1 user in the DB with those credentials
    if($result->num_rows === 1){
        $user = $result->fetch_assoc();

        if($user["isLock"]){
            $conn->close();
            header("Location: login.php?error=locked");
            exit;
        } else {
            //will eventually switch to this commented code for hashing the passwords
            //if(password_verify($password, $user["User_Password"])) {
            //above will be used later on

            if($password === $user["User_Password"]) {
                // Successful login and resets login attempts
                $_SESSION["User_ID"] = $user["User_ID"];
                $resetStmt = $conn->prepare("UPDATE Login
                                            SET No_Login_Attempts = 0
                                            WHERE User_ID = ?");
                $resetStmt->bind_param("i", $user["User_ID"]);
                $resetStmt->execute();

                $resetStmt->close();
                $conn->close();
                
                //temporary next location, will replace with different user dashboards
                header("Location: student_dashboard.php");
                exit;
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
                    
                    $updateStmt = $conn->prepare("UPDATE Login 
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