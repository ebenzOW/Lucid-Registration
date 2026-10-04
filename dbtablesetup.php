<?php

require_once 'dbconnect.php';


//USER_ID MUST BE RANDOMIZED 6 DIGIT!!!
//To simplify dealing with sql dropping leading zeros for an id like 000001 due to Int type
//we force id's to start at 100000
$sql = 
"CREATE TABLE IF NOT EXISTS USER (
    User_ID INT NOT NULL,       
    User_Type VARCHAR(20) NOT NULL,
    User_FName VARCHAR(50) NOT NULL,
    User_LName VARCHAR(50) NOT NULL,
    User_MName VARCHAR(50),
    User_Gender VARCHAR(15),
    User_DOB DATE,
    User_PhoneNo VARCHAR(15),
    Street_No VARCHAR(10),
    Street_Name VARCHAR(50),
    City VARCHAR(50),
    State VARCHAR(50),
    Country VARCHAR(50),
    Zip_Code VARCHAR(15),

    PRIMARY KEY (User_ID),
    CHECK (User_ID BETWEEN 100000 AND 9999999)
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS LOGIN (
    User_ID INT NOT NULL,
    User_Email VARCHAR(100) NOT NULL UNIQUE,
    User_Password VARCHAR(255) NOT NULL,
    No_Login_Attempts INT DEFAULT 0,
    isLock BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (User_ID),
    FOREIGN KEY (User_ID) REFERENCES USER (User_ID) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
)ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS PASSWORD_RESET (
    User_ID INT NOT NULL,
    Reset_Code INT NOT NULL,
    Time_Code_Sent DATETIME NOT NULL,
    PRIMARY KEY (User_ID),
    FOREIGN KEY (User_ID) REFERENCES USER (User_ID) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,


    CHECK (Reset_Code BETWEEN 1000000 AND 999999)

    
)ENGINE=InnoDB;
";

$conn->multi_query($sql);


?>